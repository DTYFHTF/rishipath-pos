<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use App\Models\Customer;
use App\Models\PosSession;
use App\Models\PaymentSplit;
use App\Models\CustomerLedgerEntry;
use App\Services\BarcodeService;
use App\Services\LoyaltyService;
use App\Services\CustomerLedgerService;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnhancedPOS extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static string $view = 'filament.pages.enhanced-p-o-s';
    protected static ?string $navigationLabel = 'POS';
    protected static ?string $title = 'Point of Sale';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('access_pos_billing') ?? false;
    }

    // Multi-session properties
    public $sessions = [];
    public $activeSessionKey = null;
    public $quickSearchInput = '';
    public $barcodeInput = '';
    public $customerSearch = '';
    
    // Split payment
    public $showSplitPayment = false;
    public $splitPayments = [];
    
    // Keyboard shortcuts enabled
    public $shortcutsEnabled = true;

    protected $listeners = [
        'switchSession' => 'switchToSession',
        'createNewSession' => 'createSession',
        'parkCurrentSession' => 'parkSession',
    ];

    /**
     * Get product search results as you type
     * Searches: Product name (priority), other names (Hindi/Sanskrit/Nepali), description, SKU, barcode
     */
    public function getSearchResultsProperty()
    {
        $search = trim($this->quickSearchInput);
        
        if (strlen($search) < 2) {
            return collect([]);
        }

        // Use a single DRY query with weighted relevance
        return ProductVariant::query()
            ->with('product')
            ->where('active', true)
            ->whereHas('product', fn($q) => $q->where('active', true))
            ->where(function($query) use ($search) {
                $query
                    // Product name fields
                    ->whereHas('product', function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('name_hindi', 'like', "%{$search}%")
                          ->orWhere('name_nepali', 'like', "%{$search}%")
                          ->orWhere('name_sanskrit', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%");
                    })
                    // Variant fields
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            })
            // Order by relevance: exact name match first, then partial matches
            ->orderByRaw("
                CASE 
                    WHEN EXISTS (SELECT 1 FROM products WHERE products.id = product_variants.product_id AND products.name LIKE ?) THEN 1
                    WHEN EXISTS (SELECT 1 FROM products WHERE products.id = product_variants.product_id AND products.name LIKE ?) THEN 2
                    WHEN sku LIKE ? OR barcode LIKE ? THEN 3
                    ELSE 4
                END
            ", ["{$search}%", "%{$search}%", "{$search}%", "{$search}%"])
            ->limit(10)
            ->get()
            ->map(function($variant) {
                return [
                    'id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_name' => $variant->pack_size . ' ' . $variant->unit,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'price' => $variant->selling_price_nepal,
                    'image' => $variant->product->image_url,
                    'other_names' => collect([
                        $variant->product->name_hindi,
                        $variant->product->name_nepali,
                        $variant->product->name_sanskrit,
                    ])->filter()->implode(' / '),
                ];
            });
    }

    public function mount(): void
    {
        $this->loadActiveSessions();
        
        if (empty($this->sessions)) {
            $this->createSession();
        } else {
            $this->activeSessionKey = array_key_first($this->sessions);
        }
    }

    /**
     * Load all active sessions for current cashier
     */
    public function loadActiveSessions(): void
    {
        $dbSessions = PosSession::forCashier(auth()->id())
            ->whereIn('status', ['active', 'parked'])
            ->orderBy('display_order')
            ->get();

        $this->sessions = [];
        foreach ($dbSessions as $session) {
            $this->sessions[$session->session_key] = [
                'id' => $session->id,
                'key' => $session->session_key,
                'name' => $session->session_name,
                'customer_id' => $session->customer_id,
                'customer_name' => $session->customer?->name,
                'cart' => $session->cart_items ?? [],
                'subtotal' => $session->subtotal,
                'discount' => $session->discount_amount,
                'tax' => $session->tax_amount,
                'total' => $session->total_amount,
                'status' => $session->status,
                'parked_at' => $session->parked_at,
                'payment_method' => 'cash',
                'amount_received' => 0,
                'notes' => $session->notes,
            ];
        }
    }

    /**
     * Select a customer for current session
     */
    public function selectCustomer($customerId): void
    {
        if (!$this->activeSessionKey) {
            return;
        }

        if ($customerId) {
            $customer = Customer::find($customerId);
            $this->sessions[$this->activeSessionKey]['customer_id'] = $customerId;
            $this->sessions[$this->activeSessionKey]['customer_name'] = $customer?->name;
        } else {
            $this->sessions[$this->activeSessionKey]['customer_id'] = null;
            $this->sessions[$this->activeSessionKey]['customer_name'] = null;
        }
        
        $this->customerSearch = '';
    }

    /**
     * Clear customer from current session
     */
    public function clearCustomer(): void
    {
        if (!$this->activeSessionKey) {
            return;
        }
        
        $this->sessions[$this->activeSessionKey]['customer_id'] = null;
        $this->sessions[$this->activeSessionKey]['customer_name'] = null;
        $this->customerSearch = '';
    }

    /**
     * Open customer creation - redirect to customer resource
     */
    public function openCustomerModal(): void
    {
        $this->redirect(route('filament.admin.resources.customers.create'));
    }

    /**
     * Create a new POS session
     */
    public function createSession(): void
    {
        $sessionCount = count($this->sessions) + 1;
        
        if ($sessionCount > 5) {
            Notification::make()
                ->warning()
                ->title('Maximum Sessions Reached')
                ->body('You can have up to 5 active sessions. Please park or complete existing sessions.')
                ->send();
            return;
        }

        $session = PosSession::createNew([
            'organization_id' => auth()->user()->organization_id,
            'store_id' => (!empty(auth()->user()->stores) ? auth()->user()->stores[0] : \App\Models\Store::first()?->id),
            'cashier_id' => auth()->id(),
            'session_name' => "Cart #{$sessionCount}",
        ]);

        $this->sessions[$session->session_key] = [
            'id' => $session->id,
            'key' => $session->session_key,
            'name' => $session->session_name,
            'customer_id' => null,
            'customer_name' => null,
            'cart' => [],
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => 0,
            'status' => 'active',
            'parked_at' => null,
            'payment_method' => 'cash',
            'amount_received' => 0,
            'notes' => '',
        ];

        $this->activeSessionKey = $session->session_key;

        Notification::make()
            ->success()
            ->title('New Session Created')
            ->body("Cart #{$sessionCount} is ready")
            ->send();
    }

    /**
     * Switch to a different session
     */
    public function switchToSession($sessionKey): void
    {
        if (!isset($this->sessions[$sessionKey])) {
            return;
        }

        // Save current session first
        if ($this->activeSessionKey) {
            $this->saveCurrentSession();
        }

        $this->activeSessionKey = $sessionKey;
        
        // Resume if parked
        if ($this->sessions[$sessionKey]['status'] === 'parked') {
            $session = PosSession::where('session_key', $sessionKey)->first();
            $session?->resume();
            $this->sessions[$sessionKey]['status'] = 'active';
        }
    }

    /**
     * Park current session
     */
    public function parkSession(): void
    {
        if (!$this->activeSessionKey) {
            return;
        }

        $this->saveCurrentSession();
        
        $session = PosSession::where('session_key', $this->activeSessionKey)->first();
        $session?->park();
        
        $this->sessions[$this->activeSessionKey]['status'] = 'parked';
        $this->sessions[$this->activeSessionKey]['parked_at'] = now();

        // Switch to next active session or create new
        $nextSession = collect($this->sessions)
            ->where('status', 'active')
            ->where('key', '!=', $this->activeSessionKey)
            ->first();

        if ($nextSession) {
            $this->activeSessionKey = $nextSession['key'];
        } else {
            $this->createSession();
        }

        Notification::make()
            ->success()
            ->title('Session Parked')
            ->body('You can resume this session anytime')
            ->send();
    }

    /**
     * Close/delete a session
     */
    public function closeSession($sessionKey): void
    {
        if (!isset($this->sessions[$sessionKey])) {
            return;
        }

        // Don't allow closing if there are items in cart
        if (!empty($this->sessions[$sessionKey]['cart'])) {
            Notification::make()
                ->warning()
                ->title('Cannot Close Session')
                ->body('Please complete or clear the cart first')
                ->send();
            return;
        }

        $session = PosSession::where('session_key', $sessionKey)->first();
        $session?->delete();

        unset($this->sessions[$sessionKey]);

        if ($this->activeSessionKey === $sessionKey) {
            $this->activeSessionKey = count($this->sessions) > 0 ? array_key_first($this->sessions) : null;
            
            if (!$this->activeSessionKey) {
                $this->createSession();
            }
        }
    }

    /**
     * Save current session to database
     */
    protected function saveCurrentSession(): void
    {
        if (!$this->activeSessionKey || !isset($this->sessions[$this->activeSessionKey])) {
            return;
        }

        $sessionData = $this->sessions[$this->activeSessionKey];
        $session = PosSession::where('session_key', $this->activeSessionKey)->first();
        
        if ($session) {
            $session->updateCart($sessionData['cart']);
            $session->update([
                'customer_id' => $sessionData['customer_id'],
                'notes' => $sessionData['notes'] ?? null,
            ]);
        }
    }

    /**
     * Get current active session
     */
    protected function getCurrentSession(): ?array
    {
        return $this->activeSessionKey ? $this->sessions[$this->activeSessionKey] ?? null : null;
    }

    /**
     * Add product to cart
     */
    public function addToCart($variantId, $quantity = 1): void
    {
        $session = $this->getCurrentSession();
        if (!$session) {
            return;
        }

        $variant = ProductVariant::with('product')->find($variantId);
        
        if (!$variant) {
            Notification::make()
                ->danger()
                ->title('Product not found')
                ->send();
            return;
        }

        // Check stock - get user's first store or system default
        $userStores = auth()->user()->stores ?? [];
        $storeId = !empty($userStores) ? $userStores[0] : Store::first()?->id;
        
        $stockLevel = StockLevel::where('product_variant_id', $variantId)
            ->where('store_id', $storeId)
            ->first();

        if (!$stockLevel || $stockLevel->quantity < $quantity) {
            Notification::make()
                ->warning()
                ->title('Insufficient Stock')
                ->body('Available: ' . ($stockLevel->quantity ?? 0) . ' in stock')
                ->send();
            return;
        }

        // Check if already in cart
        $existingIndex = collect($session['cart'])->search(function ($item) use ($variantId) {
            return $item['variant_id'] == $variantId;
        });

        if ($existingIndex !== false) {
            $this->sessions[$this->activeSessionKey]['cart'][$existingIndex]['quantity'] += $quantity;
        } else {
            $this->sessions[$this->activeSessionKey]['cart'][] = [
                'variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->pack_size . $variant->unit,
                'price' => $variant->mrp_india ?? $variant->base_price ?? 0,
                'quantity' => $quantity,
                'discount' => 0,
                'tax_rate' => 12, // Default 12% GST
                'image' => $variant->image_1 ?? $variant->product->image_1 ?? null,
            ];
        }

        $this->recalculateCart();
        $this->saveCurrentSession();

        // Clear search
        $this->quickSearchInput = '';
    }

    /**
     * Handle quick search and barcode input (Enter key)
     * Uses same DRY search logic as getSearchResultsProperty
     */
    public function handleQuickInput(): void
    {
        $input = trim($this->quickSearchInput);
        
        if (empty($input)) {
            return;
        }

        // Try exact barcode/SKU match first (for barcode scanners)
        $variant = ProductVariant::where('barcode', $input)
            ->orWhere('sku', $input)
            ->first();

        if ($variant) {
            $this->addToCart($variant->id);
            $this->quickSearchInput = '';
            return;
        }

        // Use the search results if available (first match)
        $results = $this->searchResults;
        if ($results->isNotEmpty()) {
            $this->addToCart($results->first()['id']);
            $this->quickSearchInput = '';
        } else {
            Notification::make()
                ->warning()
                ->title('Product Not Found')
                ->body('No products found for "' . $input . '"')
                ->send();
        }
    }

    /**
     * Update item quantity
     */
    public function updateQuantity($index, $quantity): void
    {
        if (!$this->activeSessionKey || $quantity < 1) {
            return;
        }

        $this->sessions[$this->activeSessionKey]['cart'][$index]['quantity'] = $quantity;
        $this->recalculateCart();
        $this->saveCurrentSession();
    }

    /**
     * Remove item from cart
     */
    public function removeItem($index): void
    {
        if (!$this->activeSessionKey) {
            return;
        }

        unset($this->sessions[$this->activeSessionKey]['cart'][$index]);
        $this->sessions[$this->activeSessionKey]['cart'] = array_values($this->sessions[$this->activeSessionKey]['cart']);
        
        $this->recalculateCart();
        $this->saveCurrentSession();
    }

    /**
     * Apply discount to item
     */
    public function applyItemDiscount($index, $discount): void
    {
        if (!$this->activeSessionKey) {
            return;
        }

        $this->sessions[$this->activeSessionKey]['cart'][$index]['discount'] = $discount;
        $this->recalculateCart();
        $this->saveCurrentSession();
    }

    /**
     * Recalculate cart totals
     */
    protected function recalculateCart(): void
    {
        if (!$this->activeSessionKey) {
            return;
        }

        $cart = $this->sessions[$this->activeSessionKey]['cart'];
        
        $subtotal = 0;
        $totalDiscount = 0;
        $totalTax = 0;

        foreach ($cart as $item) {
            $lineTotal = $item['price'] * $item['quantity'];
            $discount = $item['discount'] ?? 0;
            $taxableAmount = $lineTotal - $discount;
            $tax = $taxableAmount * ($item['tax'] / 100);

            $subtotal += $lineTotal;
            $totalDiscount += $discount;
            $totalTax += $tax;
        }

        $this->sessions[$this->activeSessionKey]['subtotal'] = $subtotal;
        $this->sessions[$this->activeSessionKey]['discount'] = $totalDiscount;
        $this->sessions[$this->activeSessionKey]['tax'] = $totalTax;
        $this->sessions[$this->activeSessionKey]['total'] = $subtotal - $totalDiscount + $totalTax;
    }

    /**
     * Complete sale with split payment support
     */
    public function completeSale(): void
    {
        $session = $this->getCurrentSession();
        
        if (!$session || empty($session['cart'])) {
            Notification::make()
                ->warning()
                ->title('Cart is Empty')
                ->send();
            return;
        }

        DB::beginTransaction();
        
        try {
            // Create sale
            $sale = Sale::create([
                'organization_id' => auth()->user()->organization_id,
                'store_id' => (!empty(auth()->user()->stores) ? auth()->user()->stores[0] : \App\Models\Store::first()?->id),
                'cashier_id' => auth()->id(),
                'customer_id' => $session['customer_id'],
                'invoice_number' => 'INV-' . time(),
                'date' => now()->toDateString(),
                'time' => now()->toTimeString(),
                'subtotal' => $session['subtotal'],
                'discount_amount' => $session['discount'],
                'tax_amount' => $session['tax'],
                'total_amount' => $session['total'],
                'payment_method' => $this->showSplitPayment ? 'split' : $session['payment_method'],
                'payment_status' => 'paid',
                'amount_paid' => $session['amount_received'] ?: $session['total'],
                'amount_change' => max(0, ($session['amount_received'] ?: $session['total']) - $session['total']),
                'notes' => $session['notes'],
                'status' => 'completed',
            ]);

            // Create sale items
            foreach ($session['cart'] as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'subtotal' => $item['price'] * $item['quantity'],
                    'discount_amount' => $item['discount'] ?? 0,
                    'tax_rate' => $item['tax'] ?? 0,
                    'tax_amount' => ($item['price'] * $item['quantity'] - ($item['discount'] ?? 0)) * ($item['tax'] / 100),
                    'total' => ($item['price'] * $item['quantity'] - ($item['discount'] ?? 0)) * (1 + $item['tax'] / 100),
                ]);

                // Update stock
                StockLevel::where('product_variant_id', $item['variant_id'])
                    ->where('store_id', (!empty(auth()->user()->stores) ? auth()->user()->stores[0] : \App\Models\Store::first()?->id))
                    ->decrement('quantity', $item['quantity']);
            }

            // Save split payments
            if ($this->showSplitPayment && !empty($this->splitPayments)) {
                foreach ($this->splitPayments as $split) {
                    PaymentSplit::create([
                        'sale_id' => $sale->id,
                        'payment_method' => $split['method'],
                        'amount' => $split['amount'],
                        'reference_number' => $split['reference'] ?? null,
                    ]);
                }
            }

            // Create ledger entry if customer and credit sale
            if ($session['customer_id'] && (!$this->showSplitPayment && $session['payment_method'] === 'credit')) {
                CustomerLedgerEntry::createSaleEntry($sale);
            }

            // Apply loyalty points
            if ($session['customer_id']) {
                $loyaltyService = new LoyaltyService();
                $loyaltyService->awardPointsForSale($sale);
            }

            DB::commit();

            // Complete session
            $dbSession = PosSession::where('session_key', $this->activeSessionKey)->first();
            $dbSession?->complete();

            unset($this->sessions[$this->activeSessionKey]);

            Notification::make()
                ->success()
                ->title('Sale Completed')
                ->body("Invoice: {$sale->invoice_number}")
                ->send();

            // Create new session or switch
            if (empty($this->sessions)) {
                $this->createSession();
            } else {
                $this->activeSessionKey = array_key_first($this->sessions);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            Notification::make()
                ->danger()
                ->title('Error')
                ->body($e->getMessage())
                ->send();
        }
    }

    /**
     * Open split payment modal
     */
    public function openSplitPayment(): void
    {
        $session = $this->getCurrentSession();
        
        if (!$session) {
            return;
        }

        $this->showSplitPayment = true;
        $this->splitPayments = [
            ['method' => 'cash', 'amount' => 0, 'reference' => ''],
        ];
    }

    /**
     * Add payment method to split
     */
    public function addPaymentMethod(): void
    {
        $this->splitPayments[] = ['method' => 'card', 'amount' => 0, 'reference' => ''];
    }

    /**
     * Remove payment method from split
     */
    public function removePaymentMethod($index): void
    {
        unset($this->splitPayments[$index]);
        $this->splitPayments = array_values($this->splitPayments);
    }
}

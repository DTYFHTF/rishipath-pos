<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\PaymentSplit;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Terminal;
use App\Services\InventoryService;
use App\Services\LoyaltyService;
use App\Services\OrganizationContext;
use App\Services\StoreContext;
use App\Services\WhatsAppService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnhancedPOS extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string $view = 'filament.pages.enhanced-p-o-s';

    protected static ?string $navigationLabel = 'POS';

    protected static ?int $navigationSort = 1;

    public function getTitle(): string
    {
        $store = StoreContext::getCurrentStore() ?? $this->currentTerminal?->store;
        $storeName = $store?->name ?? 'POS';

        return "Point of Sale - {$storeName}";
    }

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

    // WhatsApp receipt
    public $sendWhatsApp = false;

    // Prevent duplicate sale submissions
    public $processingSale = false;

    // Keyboard shortcuts enabled
    public $shortcutsEnabled = true;

    // Available stores for selector
    public $stores = [];

    // Terminal tied to this POS instance (by device or active terminal)
    public $currentTerminal = null;

    protected $listeners = [
        'switchSession' => 'switchToSession',
        'createNewSession' => 'createSession',
        'parkCurrentSession' => 'parkSession',
        'store-switched' => 'handleStoreSwitch',
        'organization-switched' => 'handleOrganizationSwitch',
    ];

    /**
     * Get product search results as you type
     * Searches: Product name (priority), other names (Hindi/Sanskrit/Nepali), description, SKU, barcode
     */
    public function getSearchResultsProperty()
    {
        $search = trim($this->quickSearchInput);

        if (strlen($search) < 1) {
            return collect([]);
        }

        // Use a single DRY query with weighted relevance
        return ProductVariant::query()
            ->with(['product', 'storePricing'])
            ->where('active', true)
            ->whereHas('product', fn ($q) => $q->where('active', true))
            ->where(function ($query) use ($search) {
                $query
                    // Product name fields
                    ->whereHas('product', function ($q) use ($search) {
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
            ->orderByRaw('
                CASE 
                    WHEN EXISTS (SELECT 1 FROM products WHERE products.id = product_variants.product_id AND products.name LIKE ?) THEN 1
                    WHEN EXISTS (SELECT 1 FROM products WHERE products.id = product_variants.product_id AND products.name LIKE ?) THEN 2
                    WHEN sku LIKE ? OR barcode LIKE ? THEN 3
                    ELSE 4
                END
            ', ["{$search}%", "%{$search}%", "{$search}%", "{$search}%"])
            ->limit(10)
            ->get()
            ->map(function ($variant) {
                $storeId = $this->resolveStoreId();
                $storePricing = $variant->storePricing->firstWhere('store_id', $storeId);
                $price = $storePricing?->custom_price ?? $variant->selling_price_nepal ?? $variant->base_price ?? 0;

                // Get available stock for this store
                $stockLevel = \App\Models\StockLevel::where('product_variant_id', $variant->id)
                    ->where('store_id', $storeId)
                    ->first();
                $availableStock = $stockLevel ? (int) $stockLevel->quantity : 0;

                return [
                    'id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_name' => $variant->pack_size.' '.$variant->unit,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'price' => $price,
                    'image' => $variant->product->image_url,
                    'available_stock' => $availableStock,
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

        // Check if a customer was just created and auto-select them
        if (session()->has('new_customer_id')) {
            $customerId = session()->pull('new_customer_id');
            $customer = Customer::find($customerId);

            if ($customer && $this->activeSessionKey) {
                $this->sessions[$this->activeSessionKey]['customer_id'] = $customerId;
                $this->sessions[$this->activeSessionKey]['customer_name'] = $customer->name;

                Notification::make()
                    ->success()
                    ->title('Customer Selected')
                    ->body("{$customer->name} has been added to the current cart")
                    ->send();
            }
        }

        // Load stores for current user's organization
        $orgId = OrganizationContext::getCurrentOrganizationId() ?? auth()->user()->organization_id;
        $this->stores = Store::where('organization_id', $orgId)
            ->orderBy('name')
            ->get();

        // Determine current terminal: prefer device-specific env, else any active terminal
        $deviceId = env('POS_DEVICE_ID');
        $this->currentTerminal = null;
        if ($deviceId) {
            $this->currentTerminal = Terminal::where('device_id', $deviceId)->first();
        }
        if (! $this->currentTerminal) {
            $this->currentTerminal = Terminal::where('active', true)->first();
        }
    }

    /**
     * Resolve the store id to use for POS actions: prefer global store context, then terminal->store_id, fall back to user's first store or first store in system.
     */
    protected function resolveStoreId(): ?int
    {
        // First check global store context
        $contextStoreId = StoreContext::getCurrentStoreId();
        if ($contextStoreId) {
            return $contextStoreId;
        }

        if ($this->currentTerminal?->store_id) {
            return $this->currentTerminal->store_id;
        }

        $userStores = auth()->user()->stores ?? [];
        if (! empty($userStores)) {
            return $userStores[0];
        }

        return Store::first()?->id;
    }

    /**
     * Handle store switch event from global store selector
     */
    public function handleStoreSwitch($storeId): void
    {
        // Ensure global store context is set for other components
        StoreContext::setCurrentStoreId($storeId);

        // Update current terminal if needed
        $store = Store::find($storeId);
        if ($store) {
            // Find an active terminal for this store, or keep current one
            $terminal = Terminal::where('store_id', $storeId)->where('active', true)->first();
            if ($terminal) {
                $this->currentTerminal = $terminal;
            }
        }

        // Reload sessions for new store context
        $this->loadActiveSessions();

        // If we have sessions, switch to first one, otherwise create new session
        if (! empty($this->sessions)) {
            $this->activeSessionKey = array_key_first($this->sessions);
        } else {
            $this->createSession();
        }
    }

    /**
     * Handle organization switch event from global organization selector
     */
    public function handleOrganizationSwitch($organizationId): void
    {
        // Reload stores for new organization
        $this->stores = Store::where('organization_id', $organizationId)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        // Clear current sessions and reload
        $this->sessions = [];
        $this->activeSessionKey = null;
        $this->loadActiveSessions();

        // Create a new session if needed
        if (empty($this->sessions)) {
            $this->createSession();
        } else {
            $this->activeSessionKey = array_key_first($this->sessions);
        }
    }

    /**
        $this->dispatch('$refresh');
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
                'store_id' => $session->store_id,
                'store_name' => $session->store?->name,
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
     * Get customers for searchable dropdown
     * Shows top 5 customers by purchase count, or filters by search term
     */
    #[\Livewire\Attributes\Computed]
    public function getCustomersProperty()
    {
        $query = Customer::query()
            ->where('organization_id', OrganizationContext::getCurrentOrganizationId() ?? auth()->user()->organization_id)
            ->where('active', true);

        if (! empty($this->customerSearch)) {
            // Search by name, phone, email or code
            $search = $this->customerSearch;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%");
            });

            // When searching, show more results and prioritize recent matches
            return $query->orderByDesc('created_at')
                ->limit(10)
                ->get();
        }

        // When not searching, show top customers by purchase count
        return $query->orderByDesc('total_purchases')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    /**
     * Select a customer for current session
     */
    public function selectCustomer($customerId): void
    {
        if (! $this->activeSessionKey) {
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
        if (! $this->activeSessionKey) {
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
        // Store the current session for later
        session()->put('pos_return_session', $this->activeSessionKey);
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
            'organization_id' => OrganizationContext::getCurrentOrganizationId() ?? auth()->user()->organization_id,
            'store_id' => $this->resolveStoreId(),
            'cashier_id' => auth()->id(),
            'session_name' => "Cart #{$sessionCount}",
        ]);

        $this->sessions[$session->session_key] = [
            'id' => $session->id,
            'key' => $session->session_key,
            'name' => $session->session_name,
            'store_id' => $session->store_id,
            'store_name' => $session->store?->name,
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
     * Computed property for the active store name (used by the view)
     */
    public function getActiveStoreNameProperty(): ?string
    {
        $session = $this->getCurrentSession();

        return $session ? ($session['store_name'] ?? null) : null;
    }

    /**
     * Switch to a different session
     */
    public function switchToSession($sessionKey): void
    {
        if (! isset($this->sessions[$sessionKey])) {
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
     * Change the store associated with a POS session.
     * Only users with `switch_store` permission or super-admin may change stores.
     */
    public function changeSessionStore(string $sessionKey, $storeId): void
    {
        $user = auth()->user();

        if (! ($user?->hasPermission('switch_store') || $user?->isSuperAdmin())) {
            Notification::make()->warning()->title('Not allowed')->body('You do not have permission to change store.')->send();

            return;
        }

        if (! isset($this->sessions[$sessionKey])) {
            return;
        }

        // Prevent switching if cart has items
        if (! empty($this->sessions[$sessionKey]['cart'])) {
            Notification::make()->warning()->title('Cannot change store')->body('Please empty the cart before changing the store.')->send();

            return;
        }

        $session = PosSession::where('session_key', $sessionKey)->first();
        if (! $session) {
            Notification::make()->danger()->title('Session not found')->send();

            return;
        }

        $oldStoreId = $session->store_id;
        $newStoreId = (int) $storeId;

        $session->update(['store_id' => $newStoreId]);

        // Update local session cache
        $this->sessions[$sessionKey]['store_id'] = $newStoreId;
        $this->sessions[$sessionKey]['store_name'] = Store::find($newStoreId)?->name;

        Log::info('POS session store changed', [
            'session_key' => $sessionKey,
            'old_store_id' => $oldStoreId,
            'new_store_id' => $newStoreId,
            'user_id' => $user?->id,
        ]);

        Notification::make()->success()->title('Store Updated')->body('Session store updated successfully')->send();
    }

    /**
     * Park current session
     */
    public function parkSession(): void
    {
        if (! $this->activeSessionKey) {
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
        if (! isset($this->sessions[$sessionKey])) {
            return;
        }

        // Don't allow closing if there are items in cart
        if (! empty($this->sessions[$sessionKey]['cart'])) {
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

            if (! $this->activeSessionKey) {
                $this->createSession();
            }
        }
    }

    /**
     * Save current session to database
     */
    protected function saveCurrentSession(): void
    {
        if (! $this->activeSessionKey || ! isset($this->sessions[$this->activeSessionKey])) {
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
        if (! $session) {
            return;
        }

        $variant = ProductVariant::with('product')->find($variantId);

        if (! $variant) {
            Notification::make()
                ->danger()
                ->title('Product not found')
                ->send();

            return;
        }

        // Check stock - get user's first store or system default
        $userStores = auth()->user()->stores ?? [];
        $storeId = ! empty($userStores) ? $userStores[0] : Store::first()?->id;

        $stockLevel = StockLevel::where('product_variant_id', $variantId)
            ->where('store_id', $storeId)
            ->first();

        if (! $stockLevel || $stockLevel->quantity < $quantity) {
            Notification::make()
                ->warning()
                ->title('Insufficient Stock')
                ->body('Available: '.($stockLevel->quantity ?? 0).' in stock')
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
                'product_id' => $variant->product->id,
                'variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->pack_size.$variant->unit,
                'sku' => $variant->sku,
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
                ->body('No products found for "'.$input.'"')
                ->send();
        }
    }

    /**
     * Update item quantity
     */
    public function updateQuantity($index, $quantity): void
    {
        if (! $this->activeSessionKey || $quantity < 1) {
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
        if (! $this->activeSessionKey) {
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
        if (! $this->activeSessionKey) {
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
        if (! $this->activeSessionKey) {
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
            $tax = $taxableAmount * (($item['tax_rate'] ?? 0) / 100);

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
        // Prevent duplicate submissions from double-clicks or concurrent requests
        if ($this->processingSale) {
            Notification::make()
                ->warning()
                ->title('Processing')
                ->body('Sale is already being processed. Please wait.')
                ->send();

            return;
        }

        $this->processingSale = true;

        $session = $this->getCurrentSession();

        if (! $session || empty($session['cart'])) {
            Notification::make()
                ->warning()
                ->title('Cart is Empty')
                ->send();

            return;
        }

        DB::beginTransaction();

        try {
            $storeId = $this->resolveStoreId();
            $terminal = \App\Models\Terminal::where('store_id', $storeId)->where('active', true)->first();

            // Create sale
            $receiptNumber = 'RCPT-'.strtoupper(substr(md5(uniqid()), 0, 8));

            // Normalize payment method to match DB enum: ['cash','upi','card','esewa','khalti','other']
            $allowedPaymentMethods = ['cash', 'upi', 'card', 'esewa', 'khalti'];
            $paymentMethod = $session['payment_method'] ?? 'other';
            if ($this->showSplitPayment) {
                $paymentMethod = 'other';
            } else {
                $paymentMethod = in_array($paymentMethod, $allowedPaymentMethods, true) ? $paymentMethod : 'other';
            }

            $sale = Sale::create([
                'organization_id' => OrganizationContext::getCurrentOrganizationId() ?? auth()->user()->organization_id,
                'store_id' => $storeId,
                'terminal_id' => $terminal?->id ?? \App\Models\Terminal::where('active', true)->first()?->id,
                'receipt_number' => $receiptNumber,
                'cashier_id' => auth()->id(),
                'customer_id' => $session['customer_id'],
                'invoice_number' => 'INV-'.time(),
                'date' => now()->toDateString(),
                'time' => now()->toTimeString(),
                'subtotal' => $session['subtotal'],
                'discount_amount' => $session['discount'],
                'tax_amount' => $session['tax'],
                'total_amount' => $session['total'],
                'payment_method' => $paymentMethod,
                'payment_status' => 'paid',
                'amount_paid' => $session['amount_received'] ?: $session['total'],
                'amount_change' => max(0, ($session['amount_received'] ?: $session['total']) - $session['total']),
                'notes' => $session['notes'],
                'status' => 'completed',
            ]);

            // Create sale items (match columns defined in migration)
            foreach ($session['cart'] as $item) {
                $price = (float) ($item['price'] ?? 0);
                $quantity = (float) ($item['quantity'] ?? 1);
                $discount = (float) ($item['discount'] ?? 0);
                $taxRate = (float) ($item['tax_rate'] ?? 0);
                $subtotal = round($price * $quantity, 2);
                $taxAmount = round((($subtotal - $discount) * ($taxRate / 100)), 2);
                $total = round(($subtotal - $discount) + $taxAmount, 2);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_variant_id' => $item['variant_id'],
                    'product_name' => $item['product_name'] ?? null,
                    'product_sku' => $item['sku'] ?? '',
                    'quantity' => $quantity,
                    'unit' => $item['unit'] ?? 'pcs',
                    'price_per_unit' => $price,
                    'cost_price' => $item['cost_price'] ?? 0,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                ]);

                // Update stock with audit trail
                InventoryService::decreaseStock(
                    $item['variant_id'],
                    $this->resolveStoreId(),
                    $item['quantity'],
                    'sale',
                    'Sale',
                    $sale->id,
                    $item['cost_price'] ?? null,
                    "Sale {$sale->invoice_number}"
                );
            }

            // Save split payments
            if ($this->showSplitPayment && ! empty($this->splitPayments)) {
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
            if ($session['customer_id'] && (! $this->showSplitPayment && $session['payment_method'] === 'credit')) {
                CustomerLedgerEntry::createSaleEntry($sale);
            }

            // Apply loyalty points
            if ($session['customer_id']) {
                $loyaltyService = new LoyaltyService;
                $loyaltyService->awardPointsForSale($sale);
            }

            DB::commit();

            // Complete session
            $dbSession = PosSession::where('session_key', $this->activeSessionKey)->first();
            $dbSession?->complete();
            // Send WhatsApp receipt if requested and customer has phone
            if ($this->sendWhatsApp && $sale->customer_phone) {
                $this->sendWhatsAppReceipt($sale);
            }

            Notification::make()
                ->success()
                ->title('Sale Completed')
                ->body("Invoice: {$sale->invoice_number}".($this->sendWhatsApp && $sale->customer_phone ? ' (WhatsApp sent)' : ''))
                ->send();

            // Reset WhatsApp toggle
            $this->sendWhatsApp = false;

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
        } finally {
            // Always reset processing flag so UI can continue
            $this->processingSale = false;
        }
    }

    /**
     * Send receipt via WhatsApp
     */
    protected function sendWhatsAppReceipt(Sale $sale): void
    {
        try {
            $whatsappService = app(WhatsAppService::class);

            if (! $whatsappService->isConfigured()) {
                Notification::make()
                    ->warning()
                    ->title('WhatsApp Not Configured')
                    ->body('WhatsApp credentials not set. Receipt logged only.')
                    ->send();

                // Still call the service to log the receipt
                $whatsappService->sendReceipt($sale, $sale->customer_phone);

                return;
            }

            $success = $whatsappService->sendReceipt($sale, $sale->customer_phone);

            if (! $success) {
                Notification::make()
                    ->warning()
                    ->title('WhatsApp Send Failed')
                    ->body('Could not send receipt. Check logs.')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('WhatsApp Error')
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

        if (! $session) {
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

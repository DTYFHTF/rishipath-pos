<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use App\Models\Customer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class POSBilling extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string $view = 'filament.pages.p-o-s-billing';

    protected static ?string $navigationLabel = 'POS Billing';

    protected static ?string $title = 'Point of Sale';

    protected static ?int $navigationSort = 0;

    public $searchQuery = '';
    public $cart = [];
    public $selectedCustomer = null;
    public $customerName = '';
    public $customerPhone = '';
    public $paymentMethod = 'cash';
    public $amountReceived = 0;
    public $notes = '';

    public function mount(): void
    {
        $this->cart = [];
    }

    public function searchProducts()
    {
        if (empty($this->searchQuery)) {
            return [];
        }

        return ProductVariant::with(['product.category'])
            ->whereHas('product', function ($query) {
                $query->where('active', true)
                    ->where(function ($q) {
                        $q->where('name', 'like', '%' . $this->searchQuery . '%')
                            ->orWhere('name_sanskrit', 'like', '%' . $this->searchQuery . '%')
                            ->orWhere('name_hindi', 'like', '%' . $this->searchQuery . '%')
                            ->orWhere('sku', 'like', '%' . $this->searchQuery . '%');
                    });
            })
            ->orWhere('sku', 'like', '%' . $this->searchQuery . '%')
            ->orWhere('barcode', $this->searchQuery)
            ->where('active', true)
            ->limit(10)
            ->get();
    }

    public function addToCart($variantId)
    {
        $variant = ProductVariant::with('product')->find($variantId);
        
        if (!$variant) {
            Notification::make()
                ->danger()
                ->title('Product not found')
                ->send();
            return;
        }

        // Check stock
        $stock = StockLevel::where('product_variant_id', $variantId)
            ->where('store_id', Auth::user()->stores[0] ?? 1)
            ->first();

        if (!$stock || $stock->quantity <= 0) {
            Notification::make()
                ->danger()
                ->title('Out of stock')
                ->send();
            return;
        }

        $cartKey = 'variant_' . $variantId;

        if (isset($this->cart[$cartKey])) {
            $this->cart[$cartKey]['quantity']++;
        } else {
            $this->cart[$cartKey] = [
                'variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'sku' => $variant->sku,
                'quantity' => 1,
                'unit' => $variant->unit,
                'price' => $variant->mrp_india ?? $variant->base_price,
                'tax_rate' => $this->getTaxRate($variant->product->tax_category),
            ];
        }

        $this->searchQuery = '';
        
        Notification::make()
            ->success()
            ->title('Added to cart')
            ->send();
    }

    public function removeFromCart($cartKey)
    {
        unset($this->cart[$cartKey]);
    }

    public function updateQuantity($cartKey, $quantity)
    {
        if ($quantity <= 0) {
            $this->removeFromCart($cartKey);
        } else {
            $this->cart[$cartKey]['quantity'] = $quantity;
        }
    }

    public function getSubtotal()
    {
        $subtotal = 0;
        foreach ($this->cart as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        return $subtotal;
    }

    public function getTaxAmount()
    {
        $tax = 0;
        foreach ($this->cart as $item) {
            $itemSubtotal = $item['price'] * $item['quantity'];
            $tax += $itemSubtotal * ($item['tax_rate'] / 100);
        }
        return $tax;
    }

    public function getTotal()
    {
        return $this->getSubtotal() + $this->getTaxAmount();
    }

    public function getChangeAmount()
    {
        return max(0, $this->amountReceived - $this->getTotal());
    }

    private function getTaxRate($taxCategory)
    {
        return match($taxCategory) {
            'essential' => 5,
            'standard' => 12,
            'luxury' => 18,
            default => 12,
        };
    }

    public function completeSale()
    {
        if (empty($this->cart)) {
            Notification::make()
                ->danger()
                ->title('Cart is empty')
                ->send();
            return;
        }

        if ($this->paymentMethod === 'cash' && $this->amountReceived < $this->getTotal()) {
            Notification::make()
                ->danger()
                ->title('Insufficient payment')
                ->send();
            return;
        }

        DB::beginTransaction();
        
        try {
            $user = Auth::user();
            $storeId = $user->stores[0] ?? 1;
            $terminalId = 1; // TODO: Get from user's assigned terminal

            // Generate receipt number
            $receiptNumber = 'RSH-' . now()->format('Ymd') . '-' . str_pad(Sale::whereDate('date', today())->count() + 1, 4, '0', STR_PAD_LEFT);

            // Create sale
            $sale = Sale::create([
                'organization_id' => $user->organization_id,
                'store_id' => $storeId,
                'terminal_id' => $terminalId,
                'receipt_number' => $receiptNumber,
                'date' => now()->toDateString(),
                'time' => now()->toTimeString(),
                'cashier_id' => $user->id,
                'customer_id' => $this->selectedCustomer,
                'customer_name' => $this->customerName,
                'customer_phone' => $this->customerPhone,
                'subtotal' => $this->getSubtotal(),
                'discount_amount' => 0,
                'tax_amount' => $this->getTaxAmount(),
                'total_amount' => $this->getTotal(),
                'payment_method' => $this->paymentMethod,
                'payment_status' => 'paid',
                'amount_paid' => $this->paymentMethod === 'cash' ? $this->amountReceived : $this->getTotal(),
                'amount_change' => $this->paymentMethod === 'cash' ? $this->getChangeAmount() : 0,
                'notes' => $this->notes,
                'status' => 'completed',
            ]);

            // Create sale items and update stock
            foreach ($this->cart as $item) {
                $itemSubtotal = $item['price'] * $item['quantity'];
                $itemTax = $itemSubtotal * ($item['tax_rate'] / 100);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_variant_id' => $item['variant_id'],
                    'product_name' => $item['product_name'],
                    'product_sku' => $item['sku'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'price_per_unit' => $item['price'],
                    'subtotal' => $itemSubtotal,
                    'tax_rate' => $item['tax_rate'],
                    'tax_amount' => $itemTax,
                    'total' => $itemSubtotal + $itemTax,
                ]);

                // Update stock
                $stock = StockLevel::where('product_variant_id', $item['variant_id'])
                    ->where('store_id', $storeId)
                    ->first();

                if ($stock) {
                    $stock->quantity -= $item['quantity'];
                    $stock->last_movement_at = now();
                    $stock->save();
                }
            }

            DB::commit();

            Notification::make()
                ->success()
                ->title('Sale completed!')
                ->body("Receipt #: {$receiptNumber}")
                ->send();

            // Reset form
            $this->cart = [];
            $this->customerName = '';
            $this->customerPhone = '';
            $this->selectedCustomer = null;
            $this->amountReceived = 0;
            $this->notes = '';

        } catch (\Exception $e) {
            DB::rollBack();
            
            Notification::make()
                ->danger()
                ->title('Error processing sale')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function clearCart()
    {
        $this->cart = [];
        $this->searchQuery = '';
    }
}

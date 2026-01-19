<?php

namespace App\Filament\Pages;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Services\InventoryService;
use App\Services\StoreContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockAdjustment extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string $view = 'filament.pages.stock-adjustment';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Adjustment';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('adjust_stock') ?? false;
    }

    public $productVariantId;

    public $storeId;

    public $adjustmentType = 'increase';

    public $quantity;

    public $reason;

    public $notes;

    public $currentStock = 0;
    
    // Filters for audit view
    public $filterProductId = null;
    public $filterDays = 30;

    protected $listeners = ['store-switched' => 'handleStoreSwitch'];

    public function mount(): void
    {
        $this->storeId = StoreContext::getCurrentStoreId();
    }

    public function handleStoreSwitch($storeId): void
    {
        $this->storeId = $storeId;
        $this->reset(['productVariantId', 'quantity', 'reason', 'notes', 'currentStock']);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('storeId')
                    ->label('Store')
                    ->options(\App\Models\Store::pluck('name', 'id'))
                    ->required()
                    ->default(function () {
                        $stores = Auth::user()->stores ?? [];

                        return ! empty($stores) ? $stores[0] : 1;
                    })
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateCurrentStock()),

                Select::make('productVariantId')
                    ->label('Product Variant')
                    ->options(function () {
                        return ProductVariant::with('product')
                            ->get()
                            ->mapWithKeys(fn ($variant) => [
                                $variant->id => "{$variant->product->name} - {$variant->pack_size}{$variant->unit} ({$variant->sku})",
                            ]);
                    })
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateCurrentStock()),

                Select::make('adjustmentType')
                    ->label('Adjustment Type')
                    ->options([
                        'increase' => 'Increase Stock',
                        'decrease' => 'Decrease Stock',
                        'set' => 'Set Exact Stock',
                    ])
                    ->required()
                    ->default('increase')
                    ->live(),

                TextInput::make('quantity')
                    ->label(fn ($get) => $get('adjustmentType') === 'set' ? 'New Stock Level' : 'Adjustment Quantity')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(1)
                    ->live()
                    ->helperText(fn () => "Current stock: {$this->currentStock}"),

                Select::make('reason')
                    ->label('Reason')
                    ->options([
                        'damage' => 'Damaged Goods',
                        'theft' => 'Theft/Loss',
                        'return' => 'Customer Return',
                        'transfer' => 'Store Transfer',
                        'recount' => 'Physical Count Adjustment',
                        'error' => 'System Error Correction',
                        'other' => 'Other',
                    ])
                    ->required(),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->placeholder('Add any additional details...'),
            ]);
    }

    public function updateCurrentStock(): void
    {
        if ($this->productVariantId && $this->storeId) {
            $stock = StockLevel::where('product_variant_id', $this->productVariantId)
                ->where('store_id', $this->storeId)
                ->first();

            $this->currentStock = $stock ? $stock->quantity : 0;
        }
    }

    public function getNewStockLevel(): float
    {
        if (! $this->quantity) {
            return $this->currentStock;
        }

        return match ($this->adjustmentType) {
            'increase' => $this->currentStock + $this->quantity,
            'decrease' => max(0, $this->currentStock - $this->quantity),
            'set' => $this->quantity,
            default => $this->currentStock,
        };
    }

    public function submitAdjustment(): void
    {
        $this->validate([
            'productVariantId' => 'required',
            'storeId' => 'required',
            'adjustmentType' => 'required',
            'quantity' => 'required|integer|min:0',
            'reason' => 'required',
        ]);

        DB::beginTransaction();

        try {
            $inventoryService = app(InventoryService::class);
            
            // Get current stock level
            $currentStock = $inventoryService->getStock($this->productVariantId, $this->storeId);
            $newQuantity = $this->getNewStockLevel();
            $quantityDiff = $newQuantity - $currentStock;

            // Use InventoryService to adjust stock
            if ($quantityDiff > 0) {
                $inventoryService->increaseStock(
                    productVariantId: $this->productVariantId,
                    storeId: $this->storeId,
                    quantity: $quantityDiff,
                    type: 'adjustment',
                    referenceType: 'StockAdjustment',
                    referenceId: null,
                    notes: "[{$this->reason}] {$this->notes}",
                    userId: Auth::id()
                );
            } elseif ($quantityDiff < 0) {
                $inventoryService->decreaseStock(
                    productVariantId: $this->productVariantId,
                    storeId: $this->storeId,
                    quantity: abs($quantityDiff),
                    type: 'adjustment',
                    referenceType: 'StockAdjustment',
                    referenceId: null,
                    notes: "[{$this->reason}] {$this->notes}",
                    userId: Auth::id()
                );
            }

            DB::commit();

            Notification::make()
                ->success()
                ->title('Stock adjusted successfully')
                ->body("Stock updated from {$currentStock} to {$newQuantity}")
                ->send();

            // Reset form
            $this->productVariantId = null;
            $this->quantity = null;
            $this->reason = null;
            $this->notes = null;
            $this->currentStock = 0;

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->danger()
                ->title('Error adjusting stock')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function getRecentAdjustments()
    {
        $query = InventoryMovement::with(['productVariant.product', 'user', 'store'])
            ->where('type', 'adjustment')
            ->where('created_at', '>=', now()->subDays($this->filterDays));
            
        if ($this->filterProductId) {
            $query->where('product_variant_id', $this->filterProductId);
        }
            
        return $query->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    }
}

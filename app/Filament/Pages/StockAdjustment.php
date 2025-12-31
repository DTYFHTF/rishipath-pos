<?php

namespace App\Filament\Pages;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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

    public function mount(): void
    {
        $stores = Auth::user()->stores ?? [];
        $this->storeId = !empty($stores) ? $stores[0] : 1;
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
                        return !empty($stores) ? $stores[0] : 1;
                    })
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateCurrentStock()),
                
                Select::make('productVariantId')
                    ->label('Product Variant')
                    ->options(function () {
                        return ProductVariant::with('product')
                            ->get()
                            ->mapWithKeys(fn ($variant) => [
                                $variant->id => "{$variant->product->name} - {$variant->pack_size}{$variant->unit} ({$variant->sku})"
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
                    ->step(0.001)
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
        if (!$this->quantity) {
            return $this->currentStock;
        }

        return match($this->adjustmentType) {
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
            'quantity' => 'required|numeric|min:0',
            'reason' => 'required',
        ]);

        DB::beginTransaction();

        try {
            $stock = StockLevel::firstOrCreate(
                [
                    'product_variant_id' => $this->productVariantId,
                    'store_id' => $this->storeId,
                ],
                [
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'reorder_level' => 10,
                ]
            );

            $oldQuantity = $stock->quantity;
            $newQuantity = $this->getNewStockLevel();
            
            $stock->quantity = $newQuantity;
            $stock->last_movement_at = now();
            $stock->save();

            // Record movement
            InventoryMovement::create([
                'organization_id' => Auth::user()->organization_id,
                'store_id' => $this->storeId,
                'product_variant_id' => $this->productVariantId,
                'type' => 'adjustment',
                'quantity' => abs($newQuantity - $oldQuantity),
                'unit' => ProductVariant::find($this->productVariantId)->unit,
                'from_quantity' => $oldQuantity,
                'to_quantity' => $newQuantity,
                'reference_type' => 'StockAdjustment',
                'user_id' => Auth::id(),
                'notes' => "[{$this->reason}] {$this->notes}",
            ]);

            DB::commit();

            Notification::make()
                ->success()
                ->title('Stock adjusted successfully')
                ->body("Stock updated from {$oldQuantity} to {$newQuantity}")
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
        return InventoryMovement::with(['productVariant.product', 'user', 'store'])
            ->where('type', 'adjustment')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\InventoryService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class InventoryList extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static string $view = 'filament.pages.inventory-list';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Inventory';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('view_inventory') ?? false;
    }

    public $storeId;
    public $categoryId;
    public $search = '';
    public $showLowStock = false;
    public $showOutOfStock = false;

    // Stock In/Out modal
    public $showStockModal = false;
    public $stockModalType = 'in'; // 'in' or 'out'
    public $stockModalVariantId;
    public $stockModalQuantity;
    public $stockModalReason = 'adjustment';
    public $stockModalNotes;

    // Timeline modal
    public $showTimelineModal = false;
    public $timelineVariantId;

    public function mount(): void
    {
        $stores = Auth::user()->stores ?? [];
        $this->storeId = !empty($stores) ? $stores[0] : Store::first()?->id;
    }

    public function getInventory()
    {
        $query = StockLevel::with(['productVariant.product.category', 'store'])
            ->where('store_id', $this->storeId);

        if ($this->showLowStock) {
            $query->whereColumn('quantity', '<=', 'reorder_level')
                  ->where('quantity', '>', 0);
        }

        if ($this->showOutOfStock) {
            $query->where('quantity', '<=', 0);
        }

        if ($this->categoryId) {
            $query->whereHas('productVariant.product', function ($q) {
                $q->where('category_id', $this->categoryId);
            });
        }

        if ($this->search) {
            $query->whereHas('productVariant', function ($q) {
                $q->where('sku', 'like', "%{$this->search}%")
                  ->orWhereHas('product', function ($q2) {
                      $q2->where('name', 'like', "%{$this->search}%");
                  });
            });
        }

        return $query->orderBy('quantity', 'asc')->paginate(20);
    }

    public function getMetrics(): array
    {
        $baseQuery = StockLevel::where('store_id', $this->storeId);
        
        $totalItems = (clone $baseQuery)->count();
        $lowStock = (clone $baseQuery)->whereColumn('quantity', '<=', 'reorder_level')->where('quantity', '>', 0)->count();
        $outOfStock = (clone $baseQuery)->where('quantity', '<=', 0)->count();
        $positiveStock = (clone $baseQuery)->where('quantity', '>', 0)->count();
        
        // Stock value calculations
        $stockValue = StockLevel::where('store_id', $this->storeId)
            ->join('product_variants', 'stock_levels.product_variant_id', '=', 'product_variants.id')
            ->selectRaw('SUM(stock_levels.quantity * COALESCE(product_variants.cost_price, 0)) as cost_value')
            ->selectRaw('SUM(stock_levels.quantity * COALESCE(product_variants.selling_price, 0)) as sale_value')
            ->first();

        return [
            'total_items' => $totalItems,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'positive_stock' => $positiveStock,
            'cost_value' => $stockValue->cost_value ?? 0,
            'sale_value' => $stockValue->sale_value ?? 0,
        ];
    }

    public function openStockIn($variantId): void
    {
        $this->stockModalType = 'in';
        $this->stockModalVariantId = $variantId;
        $this->stockModalQuantity = null;
        $this->stockModalReason = 'adjustment';
        $this->stockModalNotes = null;
        $this->showStockModal = true;
    }

    public function openStockOut($variantId): void
    {
        $this->stockModalType = 'out';
        $this->stockModalVariantId = $variantId;
        $this->stockModalQuantity = null;
        $this->stockModalReason = 'adjustment';
        $this->stockModalNotes = null;
        $this->showStockModal = true;
    }

    public function submitStockChange(): void
    {
        $this->validate([
            'stockModalVariantId' => 'required',
            'stockModalQuantity' => 'required|numeric|min:0.001',
            'stockModalReason' => 'required',
        ]);

        try {
            $type = $this->stockModalReason === 'damage' ? 'damage' : 'adjustment';
            $notes = "[{$this->stockModalReason}] " . ($this->stockModalNotes ?? '');

            if ($this->stockModalType === 'in') {
                InventoryService::increaseStock(
                    $this->stockModalVariantId,
                    $this->storeId,
                    $this->stockModalQuantity,
                    $type,
                    'ManualStockIn',
                    null,
                    null,
                    $notes
                );
                $message = "Added {$this->stockModalQuantity} units";
            } else {
                InventoryService::decreaseStock(
                    $this->stockModalVariantId,
                    $this->storeId,
                    $this->stockModalQuantity,
                    $type,
                    'ManualStockOut',
                    null,
                    null,
                    $notes
                );
                $message = "Removed {$this->stockModalQuantity} units";
            }

            Notification::make()
                ->success()
                ->title('Stock Updated')
                ->body($message)
                ->send();

            $this->showStockModal = false;

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function openTimeline($variantId): void
    {
        $this->timelineVariantId = $variantId;
        $this->showTimelineModal = true;
    }

    public function getTimelineMovements()
    {
        if (!$this->timelineVariantId) {
            return collect();
        }

        return InventoryMovement::where('product_variant_id', $this->timelineVariantId)
            ->where('store_id', $this->storeId)
            ->with(['user'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    public function getVariantDetails()
    {
        if (!$this->timelineVariantId) {
            return null;
        }

        return ProductVariant::with('product')->find($this->timelineVariantId);
    }

    public function closeModals(): void
    {
        $this->showStockModal = false;
        $this->showTimelineModal = false;
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\InventoryService;
use App\Services\PricingService;
use App\Services\StoreContext;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

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
    // Manual stock adjustment modal removed / deprecated

    // Timeline modal
    public $showTimelineModal = false;

    public $timelineVariantId;

    // Details modal
    public $showDetailsModal = false;

    public $detailsProductId;

    protected $listeners = [
        'store-switched' => 'handleStoreSwitch',
        'organization-switched' => 'handleOrganizationSwitch',
    ];

    public function mount(): void
    {
        $this->storeId = StoreContext::getCurrentStoreId() ?? Store::first()?->id;
    }

    public function handleStoreSwitch($storeId): void
    {
        $this->storeId = $storeId;
        $this->reset(['search', 'categoryId', 'showLowStock', 'showOutOfStock']);
    }

    public function handleOrganizationSwitch($organizationId): void
    {
        $this->storeId = StoreContext::getCurrentStoreId() ?? Store::first()?->id;
        $this->reset(['search', 'categoryId', 'showLowStock', 'showOutOfStock']);
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

        // Stock value calculations — use PricingService to get correct price field
        $organization = auth()->user()?->organization;
        $priceField = PricingService::getPriceFieldName($organization);

        $stockValue = StockLevel::where('store_id', $this->storeId)
            ->join('product_variants', 'stock_levels.product_variant_id', '=', 'product_variants.id')
            ->selectRaw('SUM(stock_levels.quantity * COALESCE(product_variants.cost_price, 0)) as cost_value')
            ->selectRaw("SUM(stock_levels.quantity * COALESCE(product_variants.{$priceField}, product_variants.base_price, 0)) as sale_value")
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

    // Deprecated: manual stock in/out via modal is disabled.
    // Keep a disabled submit method for compatibility that informs users to use purchase/return flows.
    public function submitStockChange(): void
    {
        Notification::make()
            ->warning()
            ->title('Manual Adjustments Disabled')
            ->body('Manual stock adjustments have been disabled. Use Purchases / Returns or dedicated stock workflows to update inventory.')
            ->send();
    }

    public function openTimeline($variantId): void
    {
        $this->timelineVariantId = $variantId;
        $this->showTimelineModal = true;
    }

    public function openDetails($variantId): void
    {
        $variant = ProductVariant::find($variantId);
        if ($variant && $variant->product_id) {
            $this->detailsProductId = $variant->product_id;
            $this->showDetailsModal = true;
        }
    }

    public function getTimelineMovements()
    {
        if (! $this->timelineVariantId) {
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
        if (! $this->timelineVariantId) {
            return null;
        }

        return ProductVariant::with('product')->find($this->timelineVariantId);
    }

    public function getDetailsProduct()
    {
        if (! $this->detailsProductId) {
            return null;
        }

        return \App\Models\Product::with(['variants.stockLevels', 'variants.batches'])->find($this->detailsProductId);
    }

    public function closeModals(): void
    {
        $this->showTimelineModal = false;
        $this->showDetailsModal = false;
    }
}

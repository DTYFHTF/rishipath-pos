<?php

namespace App\Filament\Pages;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\StoreContext;
use Filament\Pages\Page;

class StockValuationReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-currency-rupee';

    protected static string $view = 'filament.pages.stock-valuation-report';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Stock Valuation';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('view_reports') ?? false;
    }

    public $storeId;

    public $categoryId;
    
    public $asOfDate = null;

    protected $listeners = ['store-switched' => 'handleStoreSwitch'];

    public function mount(): void
    {
        $this->storeId = StoreContext::getCurrentStoreId() ?? Store::first()?->id;
        $this->asOfDate = now()->toDateString();
    }

    public function handleStoreSwitch($storeId): void
    {
        $this->storeId = $storeId;
        $this->dispatch('$refresh');
    }

    public function getValuationSummary(): array
    {
        $query = StockLevel::query()
            ->join('product_variants', 'stock_levels.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id');

        if ($this->storeId) {
            $query->where('stock_levels.store_id', $this->storeId);
        }

        if ($this->categoryId) {
            $query->where('products.category_id', $this->categoryId);
        }

        $result = $query->selectRaw('
            COUNT(DISTINCT stock_levels.id) as total_items,
            SUM(CASE WHEN stock_levels.quantity > 0 THEN 1 ELSE 0 END) as items_in_stock,
            SUM(stock_levels.quantity) as total_quantity,
            SUM(stock_levels.quantity * COALESCE(product_variants.cost_price, 0)) as total_cost_value,
            SUM(stock_levels.quantity * COALESCE(product_variants.selling_price, 0)) as total_sale_value
        ')->first();

        $potentialProfit = ($result->total_sale_value ?? 0) - ($result->total_cost_value ?? 0);
        $marginPercent = ($result->total_sale_value ?? 0) > 0
            ? (($potentialProfit / $result->total_sale_value) * 100)
            : 0;

        return [
            'total_items' => $result->total_items ?? 0,
            'items_in_stock' => $result->items_in_stock ?? 0,
            'total_quantity' => $result->total_quantity ?? 0,
            'total_cost_value' => $result->total_cost_value ?? 0,
            'total_sale_value' => $result->total_sale_value ?? 0,
            'potential_profit' => $potentialProfit,
            'margin_percent' => $marginPercent,
        ];
    }

    public function getCategoryBreakdown(): array
    {
        $query = StockLevel::query()
            ->join('product_variants', 'stock_levels.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id');

        if ($this->storeId) {
            $query->where('stock_levels.store_id', $this->storeId);
        }

        return $query->groupBy('categories.id', 'categories.name')
            ->selectRaw('
                categories.id,
                categories.name,
                COUNT(DISTINCT stock_levels.id) as item_count,
                SUM(stock_levels.quantity) as total_quantity,
                SUM(stock_levels.quantity * COALESCE(product_variants.cost_price, 0)) as cost_value,
                SUM(stock_levels.quantity * COALESCE(product_variants.selling_price, 0)) as sale_value
            ')
            ->orderByDesc('cost_value')
            ->get()
            ->toArray();
    }

    public function getTopValueItems(int $limit = 10): array
    {
        $query = StockLevel::query()
            ->with(['productVariant.product.category'])
            ->join('product_variants', 'stock_levels.product_variant_id', '=', 'product_variants.id');

        if ($this->storeId) {
            $query->where('stock_levels.store_id', $this->storeId);
        }

        return $query->where('stock_levels.quantity', '>', 0)
            ->selectRaw('
                stock_levels.*,
                (stock_levels.quantity * COALESCE(product_variants.cost_price, 0)) as stock_value
            ')
            ->orderByDesc('stock_value')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $variant = ProductVariant::with('product')->find($item->product_variant_id);

                return [
                    'product_name' => $variant->product->name ?? 'Unknown',
                    'variant' => $variant->pack_size.$variant->unit,
                    'sku' => $variant->sku,
                    'quantity' => $item->quantity,
                    'cost_price' => $variant->cost_price ?? 0,
                    'sale_price' => $variant->selling_price ?? 0,
                    'cost_value' => $item->quantity * ($variant->cost_price ?? 0),
                    'sale_value' => $item->quantity * ($variant->selling_price ?? 0),
                ];
            })
            ->toArray();
    }

    public function getDeadStock(int $days = 30): array
    {
        $cutoffDate = now()->subDays($days);

        $query = StockLevel::query()
            ->with(['productVariant.product'])
            ->where('quantity', '>', 0);

        if ($this->storeId) {
            $query->where('store_id', $this->storeId);
        }

        // Get variants that have not had any sale movements recently
        $recentlySoldVariants = InventoryMovement::where('type', 'sale')
            ->where('created_at', '>=', $cutoffDate)
            ->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId))
            ->distinct()
            ->pluck('product_variant_id');

        return $query->whereNotIn('product_variant_id', $recentlySoldVariants)
            ->get()
            ->map(function ($item) {
                $variant = $item->productVariant;

                return [
                    'product_name' => $variant->product->name ?? 'Unknown',
                    'variant' => $variant->pack_size.$variant->unit,
                    'sku' => $variant->sku,
                    'quantity' => $item->quantity,
                    'cost_value' => $item->quantity * ($variant->cost_price ?? 0),
                    'last_movement' => $item->last_movement_at,
                ];
            })
            ->sortByDesc('cost_value')
            ->take(20)
            ->values()
            ->toArray();
    }
    
    public function exportCSV()
    {
        $items = $this->getDetailedBreakdown();
        $summary = $this->getValuationSummary();
        
        $filename = 'stock-valuation-' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];
        
        $callback = function() use ($items, $summary) {
            $file = fopen('php://output', 'w');
            
            // Summary section
            fputcsv($file, ['Stock Valuation Report']);
            fputcsv($file, ['Generated', now()->format('Y-m-d H:i:s')]);
            fputcsv($file, ['Store', Store::find($this->storeId)?->name ?? 'All Stores']);
            fputcsv($file, []);
            
            fputcsv($file, ['Summary']);
            fputcsv($file, ['Total Items', $summary['total_items']]);
            fputcsv($file, ['Items in Stock', $summary['items_in_stock']]);
            fputcsv($file, ['Total Quantity', number_format($summary['total_quantity'], 2)]);
            fputcsv($file, ['Total Cost Value (₹)', number_format($summary['total_cost_value'], 2)]);
            fputcsv($file, ['Total Sale Value (₹)', number_format($summary['total_sale_value'], 2)]);
            fputcsv($file, ['Potential Profit (₹)', number_format($summary['potential_profit'], 2)]);
            fputcsv($file, ['Margin %', number_format($summary['margin_percent'], 2) . '%']);
            fputcsv($file, []);
            
            // Detailed breakdown
            fputcsv($file, ['Product', 'Variant', 'SKU', 'Quantity', 'Cost Price', 'Sale Price', 'Cost Value', 'Sale Value']);
            foreach ($items as $item) {
                fputcsv($file, [
                    $item['product_name'],
                    $item['variant'],
                    $item['sku'],
                    $item['quantity'],
                    $item['cost_price'],
                    $item['sale_price'],
                    $item['cost_value'],
                    $item['sale_value'],
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
}

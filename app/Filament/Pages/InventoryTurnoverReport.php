<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\SaleItem;
use App\Services\ExportService;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class InventoryTurnoverReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Inventory Turnover';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 32;

    protected static string $view = 'filament.pages.inventory-turnover-report';

    public $startDate;

    public $endDate;

    public $storeId = '';

    public $categoryId = '';

    public function mount(): void
    {
        $this->startDate = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->endDate = Carbon::now()->format('Y-m-d');
    }

    /**
     * Get overall turnover metrics
     */
    public function getTurnoverMetrics(): array
    {
        $query = SaleItem::query()
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->with(['sale', 'productVariant.product.category']);

        if ($this->storeId) {
            $query->whereHas('sale', fn ($q) => $q->where('store_id', $this->storeId));
        }

        // Calculate COGS (Cost of Goods Sold)
        $cogs = $query->sum(DB::raw('quantity * cost_price'));

        // Calculate average inventory value
        $avgInventory = ProductBatch::query()
            ->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId))
            ->where('quantity_remaining', '>', 0)
            ->avg(DB::raw('quantity_remaining * COALESCE(purchase_price, 0)'));

        $turnoverRate = $avgInventory > 0 ? $cogs / $avgInventory : 0;
        $daysToSell = $turnoverRate > 0 ? 365 / $turnoverRate : 0;

        $totalProducts = Product::when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))->count();
        $activeProducts = SaleItem::query()
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->when($this->storeId, fn ($q) => $q->whereHas('sale', fn ($sq) => $sq->where('store_id', $this->storeId)))
            ->distinct('product_variant_id')
            ->count();

        return [
            'turnover_rate' => round($turnoverRate, 2),
            'days_to_sell' => round($daysToSell, 1),
            'cogs' => $cogs,
            'avg_inventory_value' => $avgInventory ?? 0,
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'inactive_products' => $totalProducts - $activeProducts,
        ];
    }

    /**
     * Get product turnover details with ABC classification
     */
    public function getProductTurnover(): array
    {
        $query = SaleItem::query()
            ->select([
                'product_variant_id',
                DB::raw('SUM(quantity) as total_sold'),
                DB::raw('SUM(quantity * COALESCE(cost_price, 0)) as cogs'),
                DB::raw('SUM(quantity * price_per_unit) as revenue'),
            ])
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->when($this->storeId, fn ($q) => $q->whereHas('sale', fn ($sq) => $sq->where('store_id', $this->storeId))
            )
            ->groupBy('product_variant_id');

        $sales = $query->get();

        $products = [];
        foreach ($sales as $sale) {
            $variant = ProductVariant::with('product.category')->find($sale->product_variant_id);
            if (! $variant) {
                continue;
            }

            // Get current inventory
            $currentStock = ProductBatch::where('product_variant_id', $variant->id)
                ->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId))
                ->sum('quantity_remaining');

            // Get average inventory during period
            $avgStock = ProductBatch::where('product_variant_id', $variant->id)
                ->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId))
                ->where('created_at', '<=', $this->endDate)
                ->avg('quantity_remaining') ?? 0;

            $turnoverRate = $avgStock > 0 ? $sale->total_sold / $avgStock : 0;
            $daysToSell = $turnoverRate > 0 ? (Carbon::parse($this->endDate)->diffInDays(Carbon::parse($this->startDate)) / $turnoverRate) : 0;

            $products[] = [
                'variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->name,
                'category' => $variant->product->category?->name ?? 'Uncategorized',
                'total_sold' => $sale->total_sold,
                'cogs' => $sale->cogs,
                'revenue' => $sale->revenue,
                'current_stock' => $currentStock,
                'average_stock' => round($avgStock, 2),
                'turnover_rate' => round($turnoverRate, 2),
                'days_to_sell' => round($daysToSell, 1),
            ];
        }

        // Sort by revenue for ABC classification
        usort($products, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        // Calculate cumulative revenue percentage
        $totalRevenue = array_sum(array_column($products, 'revenue'));
        $cumulative = 0;

        foreach ($products as &$product) {
            $cumulative += $product['revenue'];
            $cumulativePercent = ($totalRevenue > 0) ? ($cumulative / $totalRevenue) * 100 : 0;

            // ABC Classification
            if ($cumulativePercent <= 80) {
                $product['abc_class'] = 'A'; // Top 80% revenue
            } elseif ($cumulativePercent <= 95) {
                $product['abc_class'] = 'B'; // Next 15% revenue
            } else {
                $product['abc_class'] = 'C'; // Bottom 5% revenue
            }

            $product['revenue_contribution'] = round(($product['revenue'] / $totalRevenue) * 100, 2);
        }

        return $products;
    }

    /**
     * Get fast moving products (high turnover)
     */
    public function getFastMovingProducts(int $limit = 10): array
    {
        $products = $this->getProductTurnover();
        usort($products, fn ($a, $b) => $b['turnover_rate'] <=> $a['turnover_rate']);

        return array_slice($products, 0, $limit);
    }

    /**
     * Get slow moving products (low turnover)
     */
    public function getSlowMovingProducts(int $limit = 10): array
    {
        $products = $this->getProductTurnover();
        usort($products, fn ($a, $b) => $a['turnover_rate'] <=> $b['turnover_rate']);

        return array_slice($products, 0, $limit);
    }

    /**
     * Get ABC analysis summary
     */
    public function getAbcAnalysis(): array
    {
        $products = $this->getProductTurnover();

        $analysis = [
            'A' => ['count' => 0, 'revenue' => 0, 'stock_value' => 0],
            'B' => ['count' => 0, 'revenue' => 0, 'stock_value' => 0],
            'C' => ['count' => 0, 'revenue' => 0, 'stock_value' => 0],
        ];

        foreach ($products as $product) {
            $class = $product['abc_class'];
            $analysis[$class]['count']++;
            $analysis[$class]['revenue'] += $product['revenue'];

            // Estimate current stock value using average cost
            $avgCost = $product['cogs'] / max($product['total_sold'], 1);
            $analysis[$class]['stock_value'] += $product['current_stock'] * $avgCost;
        }

        return $analysis;
    }

    /**
     * Export report to Excel
     */
    public function exportToExcel()
    {
        $metrics = $this->getTurnoverMetrics();
        $products = $this->getProductTurnover();

        $data = [];

        // Add summary
        $data[] = ['INVENTORY TURNOVER REPORT'];
        $data[] = ['Period', $this->startDate.' to '.$this->endDate];
        $data[] = [''];
        $data[] = ['SUMMARY METRICS'];
        $data[] = ['Turnover Rate', $metrics['turnover_rate'].'x'];
        $data[] = ['Average Days to Sell', $metrics['days_to_sell'].' days'];
        $data[] = ['COGS', '₹'.number_format($metrics['cogs'], 2)];
        $data[] = ['Avg Inventory Value', '₹'.number_format($metrics['avg_inventory_value'], 2)];
        $data[] = [''];

        // Add product details
        $data[] = ['Product', 'Variant', 'Category', 'Total Sold', 'Revenue', 'Current Stock', 'Turnover Rate', 'Days to Sell', 'ABC Class'];

        foreach ($products as $product) {
            $data[] = [
                $product['product_name'],
                $product['variant_name'],
                $product['category'],
                $product['total_sold'],
                '₹'.number_format($product['revenue'], 2),
                $product['current_stock'],
                $product['turnover_rate'].'x',
                round($product['days_to_sell'], 1).' days',
                $product['abc_class'],
            ];
        }

        $filename = 'inventory_turnover_'.$this->startDate.'_to_'.$this->endDate;

        return app(ExportService::class)->downloadExcel($data, $filename);
    }
}

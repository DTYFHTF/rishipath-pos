<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\ExportService;
use App\Services\OrganizationContext;
use App\Services\PricingService;
use App\Services\StoreContext;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class ProfitReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string $view = 'filament.pages.profit-report';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Profit Analysis';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('view_profit_reports') ?? false;
    }

    public $startDate;

    public $endDate;

    public $storeId = null;

    public $categoryId = null;

    protected $listeners = ['store-switched' => 'handleStoreSwitch', 'organization-switched' => 'handleOrganizationSwitch'];

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->addDay()->format('Y-m-d');
        $this->storeId = StoreContext::getCurrentStoreId();
    }

    public function handleStoreSwitch($storeId): void
    {
        $this->storeId = $storeId;
        $this->dispatch('$refresh');
    }

    public function handleOrganizationSwitch($organizationId): void
    {
        $this->dispatch('$refresh');
    }

    /**
     * Get overall profit summary
     */
    public function getProfitSummary(): array
    {
        $query = Sale::where('organization_id', OrganizationContext::getCurrentOrganizationId() ?? auth()->user()->organization_id)
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->where('status', 'completed');

        if ($this->storeId) {
            $query->where('store_id', $this->storeId);
        }

        $sales = $query->get();

        $totalRevenue = $sales->sum('total_amount');
        $totalCost = 0;
        $totalTax = $sales->sum('tax_amount');

        // Calculate cost from sale items
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $costPrice = $item->productVariant->cost_price ?? 0;
                $totalCost += ($costPrice * $item->quantity);
            }
        }

        $totalProfit = $totalRevenue - $totalCost - $totalTax;
        $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        return [
            'total_revenue' => $totalRevenue,
            'total_cost' => $totalCost,
            'total_tax' => $totalTax,
            'total_profit' => $totalProfit,
            'profit_margin' => round($profitMargin, 2),
            'transaction_count' => $sales->count(),
            'average_profit_per_sale' => $sales->count() > 0 ? $totalProfit / $sales->count() : 0,
        ];
    }

    /**
     * Get profit by category
     */
    public function getProfitByCategory(): array
    {
        $query = SaleItem::whereHas('sale', function ($q) {
            $q->whereBetween('created_at', [$this->startDate, $this->endDate])
                ->where('status', 'completed');

            if ($this->storeId) {
                $q->where('store_id', $this->storeId);
            }
        })->with(['productVariant.product.category', 'sale']);

        $items = $query->get();

        $categoryData = [];

        foreach ($items as $item) {
            $category = $item->productVariant->product->category;
            $categoryName = $category ? $category->name : 'Uncategorized';

            if (! isset($categoryData[$categoryName])) {
                $categoryData[$categoryName] = [
                    'revenue' => 0,
                    'cost' => 0,
                    'profit' => 0,
                    'quantity_sold' => 0,
                ];
            }

            $unitPrice = $item->price > 0
                ? $item->price
                : ($item->productVariant->selling_price_nepal ?? $item->productVariant->selling_price ?? $item->productVariant->base_price ?? 0);

            $revenue = $unitPrice * $item->quantity;
            $cost = ($item->productVariant->cost_price ?? 0) * $item->quantity;
            $profit = $revenue - $cost;

            $categoryData[$categoryName]['revenue'] += $revenue;
            $categoryData[$categoryName]['cost'] += $cost;
            $categoryData[$categoryName]['profit'] += $profit;
            $categoryData[$categoryName]['quantity_sold'] += $item->quantity;
        }

        // Calculate margins and sort by profit
        foreach ($categoryData as $category => &$data) {
            $data['profit_margin'] = $data['revenue'] > 0 ? ($data['profit'] / $data['revenue']) * 100 : 0;
        }

        uasort($categoryData, function ($a, $b) {
            return $b['profit'] <=> $a['profit'];
        });

        return $categoryData;
    }

    /**
     * Get top profitable products
     */
    public function getTopProfitableProducts(int $limit = 10): array
    {
        $query = SaleItem::whereHas('sale', function ($q) {
            $q->whereBetween('created_at', [$this->startDate, $this->endDate])
                ->where('status', 'completed');

            if ($this->storeId) {
                $q->where('store_id', $this->storeId);
            }
        })->with(['productVariant.product']);

        $items = $query->get();

        $productData = [];

        foreach ($items as $item) {
            $productKey = $item->product_variant_id;
            $productName = $item->productVariant->product->name.' - '.$item->productVariant->pack_size.$item->productVariant->unit;

            if (! isset($productData[$productKey])) {
                $productData[$productKey] = [
                    'name' => $productName,
                    'revenue' => 0,
                    'cost' => 0,
                    'profit' => 0,
                    'quantity_sold' => 0,
                    'selling_price' => $item->price,
                    'cost_price' => $item->productVariant->cost_price ?? 0,
                ];
            }

            $unitPrice = $item->price > 0
                ? $item->price
                : PricingService::getSellingPrice($item->productVariant, \Illuminate\Support\Facades\Auth::user()?->organization);

            $revenue = $unitPrice * $item->quantity;
            $cost = ($item->productVariant->cost_price ?? 0) * $item->quantity;

            $productData[$productKey]['revenue'] += $revenue;
            $productData[$productKey]['cost'] += $cost;
            $productData[$productKey]['profit'] += ($revenue - $cost);
            $productData[$productKey]['quantity_sold'] += $item->quantity;
        }

        // Calculate margins
        foreach ($productData as &$data) {
            $data['profit_margin'] = $data['revenue'] > 0 ? ($data['profit'] / $data['revenue']) * 100 : 0;
        }

        // Sort by profit and take top N
        uasort($productData, function ($a, $b) {
            return $b['profit'] <=> $a['profit'];
        });

        return array_slice($productData, 0, $limit, true);
    }

    /**
     * Get least profitable products
     */
    public function getLeastProfitableProducts(int $limit = 10): array
    {
        $allProducts = $this->getTopProfitableProducts(PHP_INT_MAX);

        // Sort by profit (ascending) and take bottom N
        uasort($allProducts, function ($a, $b) {
            return $a['profit'] <=> $b['profit'];
        });

        return array_slice($allProducts, 0, $limit, true);
    }

    /**
     * Get daily profit trend
     */
    public function getDailyProfitTrend(): array
    {
        $query = Sale::whereBetween('created_at', [$this->startDate, $this->endDate])
            ->where('status', 'completed');

        if ($this->storeId) {
            $query->where('store_id', $this->storeId);
        }

        $sales = $query->get()->groupBy(function ($sale) {
            return $sale->created_at->format('Y-m-d');
        });

        $dailyData = [];

        foreach ($sales as $date => $daySales) {
            $revenue = $daySales->sum('total_amount');
            $cost = 0;

            foreach ($daySales as $sale) {
                foreach ($sale->items as $item) {
                    $cost += ($item->productVariant->cost_price ?? 0) * $item->quantity;
                }
            }

            $profit = $revenue - $cost;

            $dailyData[] = [
                'date' => $date,
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => $profit,
                'profit_margin' => $revenue > 0 ? ($profit / $revenue) * 100 : 0,
                'transactions' => $daySales->count(),
            ];
        }

        // Sort by date
        usort($dailyData, function ($a, $b) {
            return $a['date'] <=> $b['date'];
        });

        return $dailyData;
    }

    /**
     * Export profit report to Excel
     */
    public function exportToExcel()
    {
        $exportService = new ExportService;
        $profitData = $this->getTopProfitableProducts(PHP_INT_MAX);

        $data = collect($profitData)->map(function ($item) {
            return [
                'Product' => $item['name'],
                'Quantity Sold' => $item['quantity_sold'],
                'Revenue' => number_format($item['revenue'], 2),
                'Cost' => number_format($item['cost'], 2),
                'Profit' => number_format($item['profit'], 2),
                'Profit Margin %' => round($item['profit_margin'], 2),
            ];
        });

        $headers = ['Product', 'Quantity Sold', 'Revenue', 'Cost', 'Profit', 'Profit Margin %'];
        $filename = $exportService->generateFilename('profit_report');

        return $exportService->downloadExcel($data, $headers, $filename);
    }
}

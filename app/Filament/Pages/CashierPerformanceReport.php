<?php

namespace App\Filament\Pages;

use App\Models\Sale;
use App\Models\User;
use App\Services\ExportService;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class CashierPerformanceReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Cashier Performance';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?int $navigationSort = 34;
    protected static string $view = 'filament.pages.cashier-performance-report';

    public $startDate;
    public $endDate;
    public $storeId = '';
    public $cashierId = '';

    public function mount(): void
    {
        $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->endDate = Carbon::now()->format('Y-m-d');
    }

    /**
     * Get overall performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $query = Sale::query()
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->when($this->storeId, fn($q) => $q->where('store_id', $this->storeId))
            ->when($this->cashierId, fn($q) => $q->where('cashier_id', $this->cashierId));

        $totalSales = $query->count();
        $totalRevenue = $query->sum('total_amount');
        $avgSaleValue = $totalSales > 0 ? $totalRevenue / $totalSales : 0;

        $totalItems = $query->withCount('items')->get()->sum('items_count');
        $avgItemsPerSale = $totalSales > 0 ? $totalItems / $totalSales : 0;

        $activeCashiers = $query->distinct('cashier_id')->count('cashier_id');

        $startDateTime = Carbon::parse($this->startDate);
        $endDateTime = Carbon::parse($this->endDate)->endOfDay();
        $totalHours = $startDateTime->diffInHours($endDateTime);
        $avgSalesPerHour = $totalHours > 0 ? $totalSales / $totalHours : 0;

        return [
            'total_sales' => $totalSales,
            'total_revenue' => $totalRevenue,
            'avg_sale_value' => $avgSaleValue,
            'total_items' => $totalItems,
            'avg_items_per_sale' => $avgItemsPerSale,
            'active_cashiers' => $activeCashiers,
            'avg_sales_per_hour' => $avgSalesPerHour,
        ];
    }

    /**
     * Get cashier performance details
     */
    public function getCashierPerformance(): array
    {
        $query = Sale::query()
            ->select([
                'cashier_id',
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(total_amount) as total_revenue'),
                DB::raw('AVG(total_amount) as avg_sale_value'),
                DB::raw('SUM(discount_amount) as total_discounts'),
                DB::raw('MIN(created_at) as first_sale'),
                DB::raw('MAX(created_at) as last_sale'),
            ])
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->when($this->storeId, fn($q) => $q->where('store_id', $this->storeId))
            ->groupBy('cashier_id');

        $sales = $query->get();

        $performance = [];
        foreach ($sales as $sale) {
            $cashier = User::find($sale->cashier_id);
            if (!$cashier) continue;

            // Get items count for this cashier
            $itemsCount = Sale::where('cashier_id', $sale->cashier_id)
                ->whereBetween('created_at', [$this->startDate, $this->endDate])
                ->when($this->storeId, fn($q) => $q->where('store_id', $this->storeId))
                ->withCount('items')
                ->get()
                ->sum('items_count');

            $avgItemsPerSale = $sale->total_sales > 0 ? $itemsCount / $sale->total_sales : 0;

            // Calculate working hours (time between first and last sale)
            $firstSale = Carbon::parse($sale->first_sale);
            $lastSale = Carbon::parse($sale->last_sale);
            $workingHours = $firstSale->diffInHours($lastSale);
            if ($workingHours === 0) $workingHours = 1; // Minimum 1 hour

            $salesPerHour = $workingHours > 0 ? $sale->total_sales / $workingHours : 0;
            $revenuePerHour = $workingHours > 0 ? $sale->total_revenue / $workingHours : 0;

            // Calculate efficiency score (0-100)
            // Based on: sales per hour (40%), avg sale value (30%), items per sale (30%)
            $avgSalesPerHour = $this->getPerformanceMetrics()['avg_sales_per_hour'];
            $avgSaleValue = $this->getPerformanceMetrics()['avg_sale_value'];
            $avgItemsPerSaleOverall = $this->getPerformanceMetrics()['avg_items_per_sale'];

            $salesPerHourScore = $avgSalesPerHour > 0 ? min(($salesPerHour / $avgSalesPerHour) * 100, 100) : 0;
            $avgSaleValueScore = $avgSaleValue > 0 ? min(($sale->avg_sale_value / $avgSaleValue) * 100, 100) : 0;
            $itemsPerSaleScore = $avgItemsPerSaleOverall > 0 ? min(($avgItemsPerSale / $avgItemsPerSaleOverall) * 100, 100) : 0;

            $efficiencyScore = ($salesPerHourScore * 0.4) + ($avgSaleValueScore * 0.3) + ($itemsPerSaleScore * 0.3);

            $performance[] = [
                'cashier_id' => $cashier->id,
                'cashier_name' => $cashier->name,
                'total_sales' => $sale->total_sales,
                'total_revenue' => $sale->total_revenue,
                'avg_sale_value' => $sale->avg_sale_value,
                'total_items' => $itemsCount,
                'avg_items_per_sale' => round($avgItemsPerSale, 2),
                'total_discounts' => $sale->total_discounts,
                'working_hours' => $workingHours,
                'sales_per_hour' => round($salesPerHour, 2),
                'revenue_per_hour' => round($revenuePerHour, 2),
                'efficiency_score' => round($efficiencyScore, 1),
            ];
        }

        // Sort by total revenue
        usort($performance, fn($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);

        return $performance;
    }

    /**
     * Get top performing cashiers
     */
    public function getTopCashiers(int $limit = 5): array
    {
        $performance = $this->getCashierPerformance();
        usort($performance, fn($a, $b) => $b['efficiency_score'] <=> $a['efficiency_score']);
        return array_slice($performance, 0, $limit);
    }

    /**
     * Get cashier performance by hour
     */
    public function getHourlyPerformance(): array
    {
        if (!$this->cashierId) {
            return [];
        }

        $sales = Sale::query()
            ->select([
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(total_amount) as total_revenue'),
                DB::raw('AVG(total_amount) as avg_sale_value'),
            ])
            ->where('cashier_id', $this->cashierId)
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->when($this->storeId, fn($q) => $q->where('store_id', $this->storeId))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $hourlyData = [];
        foreach ($sales as $sale) {
            $hourlyData[] = [
                'hour' => sprintf('%02d:00', $sale->hour),
                'total_sales' => $sale->total_sales,
                'total_revenue' => $sale->total_revenue,
                'avg_sale_value' => $sale->avg_sale_value,
            ];
        }

        return $hourlyData;
    }

    /**
     * Get daily performance trend
     */
    public function getDailyPerformance(): array
    {
        $query = Sale::query()
            ->select([
                DB::raw('DATE(created_at) as date'),
                'cashier_id',
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(total_amount) as total_revenue'),
            ])
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->when($this->storeId, fn($q) => $q->where('store_id', $this->storeId))
            ->when($this->cashierId, fn($q) => $q->where('cashier_id', $this->cashierId))
            ->groupBy('date', 'cashier_id')
            ->orderBy('date')
            ->get();

        $dailyData = [];
        foreach ($query as $sale) {
            $date = $sale->date;
            if (!isset($dailyData[$date])) {
                $dailyData[$date] = [
                    'date' => $date,
                    'cashiers' => [],
                    'total_sales' => 0,
                    'total_revenue' => 0,
                ];
            }

            $cashier = User::find($sale->cashier_id);
            $dailyData[$date]['cashiers'][] = [
                'name' => $cashier?->name ?? 'Unknown',
                'sales' => $sale->total_sales,
                'revenue' => $sale->total_revenue,
            ];

            $dailyData[$date]['total_sales'] += $sale->total_sales;
            $dailyData[$date]['total_revenue'] += $sale->total_revenue;
        }

        return array_values($dailyData);
    }

    /**
     * Get payment method distribution per cashier
     */
    public function getPaymentMethodDistribution(): array
    {
        if (!$this->cashierId) {
            return [];
        }

        $query = Sale::query()
            ->select([
                'payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_amount) as total'),
            ])
            ->where('cashier_id', $this->cashierId)
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->when($this->storeId, fn($q) => $q->where('store_id', $this->storeId))
            ->groupBy('payment_method')
            ->get();

        $distribution = [];
        foreach ($query as $payment) {
            $distribution[$payment->payment_method] = [
                'count' => $payment->count,
                'total' => $payment->total,
            ];
        }

        return $distribution;
    }

    /**
     * Export report to Excel
     */
    public function exportToExcel()
    {
        $metrics = $this->getPerformanceMetrics();
        $performance = $this->getCashierPerformance();

        $data = [];
        
        // Add summary
        $data[] = ['CASHIER PERFORMANCE REPORT'];
        $data[] = ['Period', $this->startDate . ' to ' . $this->endDate];
        $data[] = [''];
        $data[] = ['SUMMARY METRICS'];
        $data[] = ['Total Sales', $metrics['total_sales']];
        $data[] = ['Total Revenue', '₹' . number_format($metrics['total_revenue'], 2)];
        $data[] = ['Avg Sale Value', '₹' . number_format($metrics['avg_sale_value'], 2)];
        $data[] = ['Total Items Sold', $metrics['total_items']];
        $data[] = ['Avg Items Per Sale', number_format($metrics['avg_items_per_sale'], 2)];
        $data[] = ['Active Cashiers', $metrics['active_cashiers']];
        $data[] = [''];
        
        // Add cashier details
        $data[] = ['Cashier', 'Total Sales', 'Total Revenue', 'Avg Sale Value', 'Total Items', 'Avg Items/Sale', 'Working Hours', 'Sales/Hour', 'Revenue/Hour', 'Efficiency Score'];
        
        foreach ($performance as $cashier) {
            $data[] = [
                $cashier['cashier_name'],
                $cashier['total_sales'],
                '₹' . number_format($cashier['total_revenue'], 2),
                '₹' . number_format($cashier['avg_sale_value'], 2),
                $cashier['total_items'],
                $cashier['avg_items_per_sale'],
                $cashier['working_hours'] . ' hrs',
                $cashier['sales_per_hour'],
                '₹' . number_format($cashier['revenue_per_hour'], 2),
                $cashier['efficiency_score'] . '%',
            ];
        }

        $filename = 'cashier_performance_' . $this->startDate . '_to_' . $this->endDate;
        
        return app(ExportService::class)->downloadExcel($data, $filename);
    }
}

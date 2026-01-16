<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\Sale;
use App\Services\ExportService;
use Carbon\Carbon;
use Filament\Pages\Page;

class CustomerAnalyticsReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Customer Analytics';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 33;

    protected static string $view = 'filament.pages.customer-analytics-report';

    public $startDate;

    public $endDate;

    public $storeId = '';

    public function mount(): void
    {
        $this->startDate = Carbon::now()->subMonths(6)->format('Y-m-d');
        $this->endDate = Carbon::now()->format('Y-m-d');
    }

    /**
     * Get overall customer metrics
     */
    public function getCustomerMetrics(): array
    {
        $query = Sale::query()
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId));

        $totalCustomers = Customer::count();
        $activeCustomers = $query->distinct('customer_id')->whereNotNull('customer_id')->count();
        $newCustomers = Customer::whereBetween('created_at', [$this->startDate, $this->endDate])->count();

        $totalRevenue = $query->sum('total_amount');
        $totalTransactions = $query->count();
        $avgTransactionValue = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

        $customerRevenue = $query->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(total_amount) as total')
            ->get();

        $avgCustomerLifetimeValue = $customerRevenue->avg('total') ?? 0;

        return [
            'total_customers' => $totalCustomers,
            'active_customers' => $activeCustomers,
            'new_customers' => $newCustomers,
            'inactive_customers' => $totalCustomers - $activeCustomers,
            'total_revenue' => $totalRevenue,
            'total_transactions' => $totalTransactions,
            'avg_transaction_value' => $avgTransactionValue,
            'avg_lifetime_value' => $avgCustomerLifetimeValue,
        ];
    }

    /**
     * Get RFM (Recency, Frequency, Monetary) Analysis
     */
    public function getRfmAnalysis(): array
    {
        $customers = Customer::with(['sales' => function ($query) {
            $query->whereBetween('created_at', [$this->startDate, $this->endDate])
                ->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId));
        }])->get();

        $rfmData = [];

        foreach ($customers as $customer) {
            if ($customer->sales->isEmpty()) {
                continue;
            }

            // Recency: Days since last purchase
            $lastPurchase = $customer->sales->max('created_at');
            $recencyDays = Carbon::parse($lastPurchase)->diffInDays(Carbon::now());

            // Frequency: Number of purchases
            $frequency = $customer->sales->count();

            // Monetary: Total spending
            $monetary = $customer->sales->sum('total_amount');

            $rfmData[] = [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'recency_days' => $recencyDays,
                'frequency' => $frequency,
                'monetary' => $monetary,
            ];
        }

        if (empty($rfmData)) {
            return [];
        }

        // Calculate RFM scores (1-5, where 5 is best)
        $recencyScores = $this->calculateScores(array_column($rfmData, 'recency_days'), true); // Lower is better
        $frequencyScores = $this->calculateScores(array_column($rfmData, 'frequency'));
        $monetaryScores = $this->calculateScores(array_column($rfmData, 'monetary'));

        foreach ($rfmData as $index => &$data) {
            $data['recency_score'] = $recencyScores[$index];
            $data['frequency_score'] = $frequencyScores[$index];
            $data['monetary_score'] = $monetaryScores[$index];
            $data['rfm_score'] = $recencyScores[$index] + $frequencyScores[$index] + $monetaryScores[$index];

            // Customer Segment
            $data['segment'] = $this->getCustomerSegment(
                $recencyScores[$index],
                $frequencyScores[$index],
                $monetaryScores[$index]
            );
        }

        // Sort by RFM score
        usort($rfmData, fn ($a, $b) => $b['rfm_score'] <=> $a['rfm_score']);

        return $rfmData;
    }

    /**
     * Calculate quintile scores (1-5)
     */
    private function calculateScores(array $values, bool $reverse = false): array
    {
        $sorted = $values;
        sort($sorted);
        $count = count($sorted);

        $scores = [];
        foreach ($values as $value) {
            $position = array_search($value, $sorted);
            $percentile = ($position + 1) / $count;

            if ($reverse) {
                $score = 5 - floor($percentile * 5);
            } else {
                $score = ceil($percentile * 5);
            }

            $scores[] = max(1, min(5, $score));
        }

        return $scores;
    }

    /**
     * Determine customer segment based on RFM scores
     */
    private function getCustomerSegment(int $r, int $f, int $m): string
    {
        if ($r >= 4 && $f >= 4 && $m >= 4) {
            return 'Champions';
        }
        if ($r >= 3 && $f >= 4 && $m >= 4) {
            return 'Loyal Customers';
        }
        if ($r >= 4 && $f <= 2 && $m >= 3) {
            return 'Potential Loyalists';
        }
        if ($r >= 4 && $f <= 2 && $m <= 2) {
            return 'New Customers';
        }
        if ($r >= 3 && $f >= 3 && $m >= 3) {
            return 'Promising';
        }
        if ($r <= 2 && $f >= 3 && $m >= 3) {
            return 'At Risk';
        }
        if ($r <= 2 && $f <= 2 && $m >= 3) {
            return 'Hibernating';
        }
        if ($r <= 2 && $f >= 3 && $m <= 2) {
            return 'Cannot Lose Them';
        }

        return 'Lost';
    }

    /**
     * Get top customers by spending
     */
    public function getTopCustomers(int $limit = 10): array
    {
        $rfm = $this->getRfmAnalysis();
        usort($rfm, fn ($a, $b) => $b['monetary'] <=> $a['monetary']);

        return array_slice($rfm, 0, $limit);
    }

    /**
     * Get customer segments distribution
     */
    public function getSegmentDistribution(): array
    {
        $rfm = $this->getRfmAnalysis();
        $segments = [];

        foreach ($rfm as $customer) {
            $segment = $customer['segment'];
            if (! isset($segments[$segment])) {
                $segments[$segment] = [
                    'count' => 0,
                    'revenue' => 0,
                    'avg_frequency' => 0,
                ];
            }
            $segments[$segment]['count']++;
            $segments[$segment]['revenue'] += $customer['monetary'];
            $segments[$segment]['avg_frequency'] += $customer['frequency'];
        }

        // Calculate averages
        foreach ($segments as $segment => &$data) {
            $data['avg_frequency'] = $data['count'] > 0 ? round($data['avg_frequency'] / $data['count'], 1) : 0;
            $data['avg_revenue'] = $data['count'] > 0 ? round($data['revenue'] / $data['count'], 2) : 0;
        }

        // Sort by revenue
        uasort($segments, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        return $segments;
    }

    /**
     * Get purchase frequency distribution
     */
    public function getPurchaseFrequency(): array
    {
        $query = Sale::query()
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId))
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as purchase_count')
            ->get();

        $distribution = [
            '1 purchase' => 0,
            '2-3 purchases' => 0,
            '4-6 purchases' => 0,
            '7-10 purchases' => 0,
            '11+ purchases' => 0,
        ];

        foreach ($query as $customer) {
            $count = $customer->purchase_count;
            if ($count === 1) {
                $distribution['1 purchase']++;
            } elseif ($count <= 3) {
                $distribution['2-3 purchases']++;
            } elseif ($count <= 6) {
                $distribution['4-6 purchases']++;
            } elseif ($count <= 10) {
                $distribution['7-10 purchases']++;
            } else {
                $distribution['11+ purchases']++;
            }
        }

        return $distribution;
    }

    /**
     * Export report to Excel
     */
    public function exportToExcel()
    {
        $metrics = $this->getCustomerMetrics();
        $rfm = $this->getRfmAnalysis();

        $data = [];

        // Add summary
        $data[] = ['CUSTOMER ANALYTICS REPORT'];
        $data[] = ['Period', $this->startDate.' to '.$this->endDate];
        $data[] = [''];
        $data[] = ['SUMMARY METRICS'];
        $data[] = ['Total Customers', $metrics['total_customers']];
        $data[] = ['Active Customers', $metrics['active_customers']];
        $data[] = ['New Customers', $metrics['new_customers']];
        $data[] = ['Total Revenue', '₹'.number_format($metrics['total_revenue'], 2)];
        $data[] = ['Avg Transaction Value', '₹'.number_format($metrics['avg_transaction_value'], 2)];
        $data[] = ['Avg Lifetime Value', '₹'.number_format($metrics['avg_lifetime_value'], 2)];
        $data[] = [''];

        // Add RFM analysis
        $data[] = ['Customer Name', 'Phone', 'Recency (days)', 'Frequency', 'Monetary', 'R Score', 'F Score', 'M Score', 'Total Score', 'Segment'];

        foreach ($rfm as $customer) {
            $data[] = [
                $customer['customer_name'],
                $customer['customer_phone'],
                $customer['recency_days'],
                $customer['frequency'],
                '₹'.number_format($customer['monetary'], 2),
                $customer['recency_score'],
                $customer['frequency_score'],
                $customer['monetary_score'],
                $customer['rfm_score'],
                $customer['segment'],
            ];
        }

        $filename = 'customer_analytics_'.$this->startDate.'_to_'.$this->endDate;

        return app(ExportService::class)->downloadExcel($data, $filename);
    }
}

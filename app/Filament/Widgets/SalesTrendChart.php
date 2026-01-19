<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use App\Services\OrganizationContext;
use App\Services\StoreContext;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class SalesTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Sales Trend (Last 30 Days)';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    #[On('organization-switched')]
    #[On('store-switched')]
    public function refresh(): void
    {
        // Force widget refresh
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('view_dashboard') ?? false;
    }

    protected function getData(): array
    {
        $organizationId = OrganizationContext::getCurrentOrganizationId();
        $storeId = StoreContext::getCurrentStoreId();
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays(29);

        $sales = Sale::query()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as total_revenue'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->where('organization_id', $organizationId)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill in missing dates with zero values
        $dates = [];
        $revenues = [];
        $transactions = [];

        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $sale = $sales->firstWhere('date', $dateStr);

            $dates[] = $date->format('M d');
            $revenues[] = $sale ? round($sale->total_revenue, 2) : 0;
            $transactions[] = $sale ? $sale->transaction_count : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (₹)',
                    'data' => $revenues,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Transactions',
                    'data' => $transactions,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $dates,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue (₹)',
                    ],
                    'ticks' => [
                        'callback' => "function(value) { return '₹' + value.toLocaleString(); }",
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Transactions',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
                'x' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Date',
                    ],
                ],
            ],
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}

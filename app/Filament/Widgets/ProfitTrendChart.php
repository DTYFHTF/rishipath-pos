<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\OrganizationContext;
use App\Services\StoreContext;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class ProfitTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Profit Analysis (Last 30 Days)';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('view_dashboard') ?? false;
    }

    #[On('organization-switched')]
    #[On('store-switched')]
    public function refresh(): void
    {
        // Force widget refresh
    }

    protected function getData(): array
    {
        $organizationId = OrganizationContext::getCurrentOrganizationId();
        $storeId = StoreContext::getCurrentStoreId();
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays(29);

        // Get daily sales with cost calculation
        $dailyData = [];

        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateStr = $date->format('Y-m-d');

            $sales = Sale::where('organization_id', $organizationId)
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->whereDate('created_at', $dateStr)
                ->get();
            $revenue = $sales->sum('final_total');

            // Calculate cost from sale items
            $saleIds = $sales->pluck('id');
            $cost = SaleItem::whereIn('sale_id', $saleIds)
                ->sum(DB::raw('quantity * cost_price'));

            $profit = $revenue - $cost;
            $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

            $dailyData[] = [
                'date' => $date->format('M d'),
                'revenue' => round($revenue, 2),
                'cost' => round($cost, 2),
                'profit' => round($profit, 2),
                'margin' => round($margin, 1),
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (₹)',
                    'data' => array_column($dailyData, 'revenue'),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Cost (₹)',
                    'data' => array_column($dailyData, 'cost'),
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Profit (₹)',
                    'data' => array_column($dailyData, 'profit'),
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Margin (%)',
                    'data' => array_column($dailyData, 'margin'),
                    'borderColor' => 'rgb(168, 85, 247)',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.1)',
                    'fill' => false,
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                    'borderDash' => [5, 5],
                ],
            ],
            'labels' => array_column($dailyData, 'date'),
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
                    'callbacks' => [
                        'label' => "function(context) { 
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                if (label.includes('%')) {
                                    label += context.parsed.y.toFixed(1) + '%';
                                } else {
                                    label += '₹' + context.parsed.y.toLocaleString();
                                }
                            }
                            return label;
                        }",
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Amount (₹)',
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
                        'text' => 'Margin (%)',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                    'ticks' => [
                        'callback' => "function(value) { return value.toFixed(1) + '%'; }",
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

    // Return parent render directly to preserve component context
    public function render(): \Illuminate\Contracts\View\View
    {
        return parent::render();
    }
}

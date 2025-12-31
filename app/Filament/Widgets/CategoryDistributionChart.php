<?php

namespace App\Filament\Widgets;

use App\Models\SaleItem;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CategoryDistributionChart extends ChartWidget
{
    protected static ?string $heading = 'Sales by Category (Last 30 Days)';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('view_dashboard') ?? false;
    }

    protected function getData(): array
    {
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays(29);

        $categoryData = SaleItem::query()
            ->join('product_variants', 'sale_items.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereBetween('sale_items.created_at', [$startDate, $endDate])
            ->select(
                'categories.name as category_name',
                DB::raw('SUM(sale_items.quantity * sale_items.price_per_unit) as total_revenue')
            )
            ->groupBy('categories.name')
            ->orderByDesc('total_revenue')
            ->limit(8)
            ->get();

        $labels = [];
        $values = [];
        $colors = [
            'rgb(59, 130, 246)',    // Blue
            'rgb(34, 197, 94)',     // Green
            'rgb(249, 115, 22)',    // Orange
            'rgb(168, 85, 247)',    // Purple
            'rgb(236, 72, 153)',    // Pink
            'rgb(20, 184, 166)',    // Teal
            'rgb(251, 191, 36)',    // Amber
            'rgb(239, 68, 68)',     // Red
        ];

        foreach ($categoryData as $index => $category) {
            $labels[] = $category->category_name;
            $values[] = round($category->total_revenue, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (₹)',
                    'data' => $values,
                    'backgroundColor' => array_slice($colors, 0, count($values)),
                    'borderWidth' => 2,
                    'borderColor' => '#fff',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'right',
                ],
                'tooltip' => [
                    'enabled' => true,
                    'callbacks' => [
                        'label' => "function(context) { 
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed !== null) {
                                label += '₹' + context.parsed.toLocaleString();
                            }
                            return label;
                        }",
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => true,
            'cutout' => '60%',
        ];
    }
}

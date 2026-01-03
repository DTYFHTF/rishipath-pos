<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class POSStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $todaySales = Sale::whereDate('date', today())
            ->where('status', 'completed')
            ->sum('total_amount');

        $monthSales = Sale::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->where('status', 'completed')
            ->sum('total_amount');

        $totalProducts = Product::where('active', true)->count();
        
        $totalCustomers = Customer::where('active', true)->count();

        $lowStockCount = DB::table('stock_levels')
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->count();

        return [
            Stat::make('Today\'s Sales', '₹' . number_format($todaySales, 2))
                ->description('Total revenue today')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('This Month', '₹' . number_format($monthSales, 2))
                ->description('Monthly revenue')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Active Products', $totalProducts)
                ->description('Products in catalog')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make('Low Stock Items', $lowStockCount)
                ->description('Items below reorder level')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'danger' : 'success'),
        ];
    }
}

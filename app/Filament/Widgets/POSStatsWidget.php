<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\OrganizationContext;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class POSStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    #[On('organization-switched')]
    #[On('store-switched')]
    public function refresh(): void
    {
        // Force widget refresh
    }

    protected function getStats(): array
    {
        $organizationId = OrganizationContext::getCurrentOrganizationId();

        $todaySales = Sale::where('organization_id', $organizationId)
            ->whereDate('date', today())
            ->where('status', 'completed')
            ->sum('total_amount');

        $monthSales = Sale::where('organization_id', $organizationId)
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->where('status', 'completed')
            ->sum('total_amount');

        $totalProducts = Product::where('organization_id', $organizationId)
            ->where('active', true)
            ->count();

        $totalCustomers = Customer::where('organization_id', $organizationId)
            ->where('active', true)
            ->count();

        $lowStockCount = DB::table('stock_levels')
            ->join('product_variants', 'stock_levels.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->where('products.organization_id', $organizationId)
            ->whereColumn('stock_levels.quantity', '<=', 'stock_levels.reorder_level')
            ->count();

        return [
            Stat::make('Today\'s Sales', '₹'.number_format($todaySales, 2))
                ->description('Total revenue today')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('This Month', '₹'.number_format($monthSales, 2))
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

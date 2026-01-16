<?php

namespace App\Filament\Widgets;

use App\Models\ProductBatch;
use App\Models\StockLevel;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class InventoryOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Total inventory value
        $inventoryValue = DB::table('product_batches')
            ->join('product_variants', 'product_batches.product_variant_id', '=', 'product_variants.id')
            ->select(DB::raw('SUM(product_batches.quantity_remaining * product_batches.purchase_price) as total_value'))
            ->value('total_value') ?? 0;

        // Low stock items
        $lowStockCount = StockLevel::whereColumn('quantity', '<=', 'reorder_level')->count();

        // Expired batches
        $expiredCount = ProductBatch::where('expiry_date', '<', now())
            ->where('quantity_remaining', '>', 0)
            ->count();

        // Expiring soon (within 30 days)
        $expiringSoonCount = ProductBatch::whereBetween('expiry_date', [now(), now()->addDays(30)])
            ->where('quantity_remaining', '>', 0)
            ->count();

        // Out of stock items
        $outOfStockCount = StockLevel::where('quantity', '<=', 0)->count();

        return [
            Stat::make('Inventory Value', '₹'.number_format($inventoryValue, 2))
                ->description('Total stock value')
                ->descriptionIcon('heroicon-m-currency-rupee')
                ->color('success'),

            Stat::make('Low Stock Items', $lowStockCount)
                ->description('Below reorder level')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'warning' : 'success'),

            Stat::make('Out of Stock', $outOfStockCount)
                ->description('Items unavailable')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($outOfStockCount > 0 ? 'danger' : 'success'),

            Stat::make('Expired Batches', $expiredCount)
                ->description('Needs attention')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($expiredCount > 0 ? 'danger' : 'success'),

            Stat::make('Expiring Soon', $expiringSoonCount)
                ->description('Within 30 days')
                ->descriptionIcon('heroicon-m-clock')
                ->color($expiringSoonCount > 0 ? 'warning' : 'success'),
        ];
    }
}

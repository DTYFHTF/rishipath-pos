<?php

namespace App\Filament\Widgets;

use App\Models\StockLevel;
use App\Services\OrganizationContext;
use App\Services\StoreContext;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Livewire\Attributes\On;

class LowStockAlertsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    #[On('organization-switched')]
    #[On('store-switched')]
    public function refresh(): void
    {
        // Force widget refresh
    }

    public function table(Table $table): Table
    {
        $organizationId = OrganizationContext::getCurrentOrganizationId();
        $storeId = StoreContext::getCurrentStoreId();

        return $table
            ->query(
                StockLevel::query()
                    ->with(['productVariant.product', 'store'])
                    ->join('product_variants', 'stock_levels.product_variant_id', '=', 'product_variants.id')
                    ->join('products', 'product_variants.product_id', '=', 'products.id')
                    ->where('products.organization_id', $organizationId)
                    ->when($storeId, fn($q) => $q->where('stock_levels.store_id', $storeId))
                    ->whereColumn('stock_levels.quantity', '<=', 'stock_levels.reorder_level')
                    ->select('stock_levels.*')
                    ->orderBy('stock_levels.quantity', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('productVariant.product.name')
                    ->label('Product')
                    ->searchable(),
                Tables\Columns\TextColumn::make('productVariant.sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('productVariant.pack_size')
                    ->label('Size')
                    ->formatStateUsing(fn ($record) => $record->productVariant->pack_size.' '.$record->productVariant->unit),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store'),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Current Stock')
                    ->numeric(decimalPlaces: 2)
                    ->color(fn ($state) => $state <= 0 ? 'danger' : 'warning')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('reorder_level')
                    ->label('Reorder Level')
                    ->numeric(),
                Tables\Columns\TextColumn::make('last_movement_at')
                    ->label('Last Movement')
                    ->dateTime()
                    ->since(),
            ])
            ->heading('⚠️ Low Stock Alerts')
            ->description('Products below reorder level')
            ->paginated([10, 25, 50]);
    }
}

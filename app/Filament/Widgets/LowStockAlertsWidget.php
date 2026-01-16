<?php

namespace App\Filament\Widgets;

use App\Models\StockLevel;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockAlertsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockLevel::query()
                    ->with(['productVariant.product', 'store'])
                    ->whereColumn('quantity', '<=', 'reorder_level')
                    ->orderBy('quantity', 'asc')
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

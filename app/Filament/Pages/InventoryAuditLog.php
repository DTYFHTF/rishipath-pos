<?php

namespace App\Filament\Pages;

use App\Models\InventoryMovement;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryAuditLog extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string $view = 'filament.pages.inventory-audit-log';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('view_inventory_audit') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryMovement::query()
                    ->with(['productVariant.product', 'user', 'store'])
                    ->where('organization_id', auth()->user()->organization_id)
                    ->orderByDesc('created_at')
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date/Time')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->colors([
                        'success' => 'purchase',
                        'danger' => 'sale',
                        'warning' => 'adjustment',
                        'info' => 'transfer',
                        'gray' => fn ($state) => in_array($state, ['damage', 'return']),
                    ])
                    ->searchable(),

                TextColumn::make('productVariant.product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('productVariant.sku')
                    ->label('SKU')
                    ->searchable(),

                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => 
                        ($record->from_quantity > $record->to_quantity ? '-' : '+') . 
                        number_format($state, 3) . ' ' . $record->unit
                    ),

                TextColumn::make('from_quantity')
                    ->label('From')
                    ->formatStateUsing(fn ($state, $record) => number_format($state, 3)),

                TextColumn::make('to_quantity')
                    ->label('To')
                    ->formatStateUsing(fn ($state, $record) => number_format($state, 3)),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('reference_type')
                    ->label('Reference')
                    ->formatStateUsing(fn ($state, $record) => 
                        $state ? "{$state} #{$record->reference_id}" : '—'
                    ),

                TextColumn::make('user.name')
                    ->label('User')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('cost_price')
                    ->label('Cost')
                    ->money('INR')
                    ->toggleable(),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'purchase' => 'Purchase',
                        'sale' => 'Sale',
                        'adjustment' => 'Adjustment',
                        'transfer' => 'Transfer',
                        'damage' => 'Damage',
                        'return' => 'Return',
                    ]),

                SelectFilter::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name'),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')
                            ->label('From Date'),
                        DatePicker::make('to')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}

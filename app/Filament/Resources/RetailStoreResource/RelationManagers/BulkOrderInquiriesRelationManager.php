<?php

namespace App\Filament\Resources\RetailStoreResource\RelationManagers;

use App\Filament\Resources\BulkOrderInquiryResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BulkOrderInquiriesRelationManager extends RelationManager
{
    protected static string $relationship = 'bulkOrderInquiries';

    protected static ?string $title = 'Bulk Order Inquiries';

    protected static ?string $icon = 'heroicon-o-shopping-cart';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Contact')
                    ->searchable(),

                Tables\Columns\TextColumn::make('company_name')
                    ->label('Company')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('product_names')
                    ->label('Products')
                    ->limit(40)
                    ->wrap(),

                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Qty')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'info',
                        'contacted' => 'warning',
                        'quoted' => 'success',
                        'closed' => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createBulkOrder')
                    ->label('New Bulk Order')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => BulkOrderInquiryResource::getUrl('create', [
                        'store' => $this->getOwnerRecord()->id,
                    ])),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => BulkOrderInquiryResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}

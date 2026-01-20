<?php

namespace App\Filament\Resources\PurchaseResource\RelationManagers;

use App\Models\ProductBatch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class BatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'batches';

    protected static ?string $recordTitleAttribute = 'batch_number';

    protected static ?string $title = 'Product Batches';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('batch_number')
                    ->disabled()
                    ->label('Batch Number'),
                Forms\Components\Select::make('product_variant_id')
                    ->relationship('productVariant', 'sku')
                    ->disabled()
                    ->label('Product Variant'),
                Forms\Components\DatePicker::make('expiry_date')
                    ->label('Expiry Date')
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                Forms\Components\TextInput::make('quantity_remaining')
                    ->numeric()
                    ->label('Quantity Remaining'),
                Forms\Components\TextInput::make('purchase_price')
                    ->numeric()
                    ->prefix('₹')
                    ->disabled()
                    ->label('Purchase Price'),
                Forms\Components\Textarea::make('notes')
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('batch_number')
            ->columns([
                TextColumn::make('batch_number')
                    ->label('Batch Number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-o-queue-list'),
                    
                TextColumn::make('productVariant.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('productVariant.product.name')
                    ->label('Product')
                    ->searchable()
                    ->limit(30),
                    
                TextColumn::make('expiry_date')
                    ->label('Expiry Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($record) => $record->expiry_date && $record->expiry_date < now() ? 'danger' : 
                        ($record->expiry_date && $record->expiry_date < now()->addDays(30) ? 'warning' : 'success'))
                    ->icon(fn ($record) => $record->expiry_date && $record->expiry_date < now() ? 'heroicon-o-x-circle' : 
                        ($record->expiry_date && $record->expiry_date < now()->addDays(30) ? 'heroicon-o-exclamation-triangle' : null)),
                        
                TextColumn::make('quantity_remaining')
                    ->label('Qty Remaining')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($record) => $record->quantity_remaining <= 0 ? 'danger' : 
                        ($record->quantity_remaining < 10 ? 'warning' : 'success')),
                        
                TextColumn::make('purchase_price')
                    ->label('Purchase Price')
                    ->money('INR')
                    ->sortable(),
                    
                TextColumn::make('store.name')
                    ->label('Store')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->relationship('store', 'name')
                    ->label('Store'),
                Tables\Filters\Filter::make('expired')
                    ->query(fn ($query) => $query->where('expiry_date', '<', now()))
                    ->label('Expired Only'),
                Tables\Filters\Filter::make('expiring_soon')
                    ->query(fn ($query) => $query->whereBetween('expiry_date', [now(), now()->addDays(30)]))
                    ->label('Expiring Soon (30 days)'),
            ])
            ->headerActions([
                // No create action - batches are auto-created when purchase is received
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('batch_number', 'desc')
            ->emptyStateHeading('No batches yet')
            ->emptyStateDescription('Product batches will appear here automatically when you receive this purchase.')
            ->emptyStateIcon('heroicon-o-cube');
    }
}

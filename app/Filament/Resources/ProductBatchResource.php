<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductBatchResource\Pages;
use App\Models\ProductBatch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductBatchResource extends Resource
{
    protected static ?string $model = ProductBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Batches';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Batch Information')
                    ->schema([
                        Forms\Components\Select::make('product_variant_id')
                            ->label('Product Variant')
                            ->options(function () {
                                return \App\Models\ProductVariant::with('product')
                                    ->get()
                                    ->mapWithKeys(fn ($v) => [
                                        $v->id => "{$v->product->name} - {$v->pack_size}{$v->unit} (SKU: {$v->sku})",
                                    ]);
                            })
                            ->searchable()
                            ->required()
                            ->preload(),
                        Forms\Components\Select::make('store_id')
                            ->relationship('store', 'name')
                            ->required()
                            ->default(1),
                        Forms\Components\TextInput::make('batch_number')
                            ->required()
                            ->maxLength(100)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule, $get) => $rule
                                    ->where('store_id', $get('store_id'))
                                    ->where('product_variant_id', $get('product_variant_id'))
                            ),
                        Forms\Components\Select::make('supplier_id')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Dates')
                    ->schema([
                        Forms\Components\DatePicker::make('manufactured_date')
                            ->label('Manufacturing Date')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now())
                            ->helperText('Date product was manufactured'),
                        Forms\Components\DatePicker::make('expiry_date')
                            ->label('Expiry Date')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(now())
                            ->helperText('Product expiration date'),
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Purchase Date')
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now())
                            ->helperText('Date purchased from supplier'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Quantities & Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('quantity_received')
                            ->label('Quantity Received')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->default(0),
                        Forms\Components\TextInput::make('purchase_price')
                            ->label('Purchase Price per Unit')
                            ->numeric()
                            ->prefix('₹')
                            ->step(0.01),
                        Forms\Components\TextInput::make('quantity_remaining')
                            ->label('Current Stock')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->default(fn ($get) => $get('quantity_received')),
                        Forms\Components\TextInput::make('quantity_sold')
                            ->label('Quantity Sold')
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('quantity_damaged')
                            ->label('Quantity Damaged')
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->default(0),
                        Forms\Components\TextInput::make('quantity_returned')
                            ->label('Quantity Returned')
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->default(0),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('batch_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('productVariant.product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('productVariant.sku')
                    ->label('Variant SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity_remaining')
                    ->label('Stock')
                    ->numeric(decimalPlaces: 0)
                    ->sortable()
                    ->color(fn ($record) => $record->quantity_remaining <= 0 ? 'danger' : ($record->quantity_remaining < 10 ? 'warning' : 'success')),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->expiry_date && $record->expiry_date->isPast() ? 'danger' :
                        ($record->expiry_date && $record->expiry_date->diffInDays() < 30 ? 'warning' : null)),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('purchase_price')
                    ->money('INR')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store')
                    ->relationship('store', 'name'),
                Tables\Filters\Filter::make('low_stock')
                    ->query(fn (Builder $query): Builder => $query->where('quantity_remaining', '<', 10))
                    ->label('Low Stock'),
                Tables\Filters\Filter::make('expired')
                    ->query(fn (Builder $query): Builder => $query->where('expiry_date', '<', now()))
                    ->label('Expired'),
                Tables\Filters\Filter::make('expiring_soon')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('expiry_date', [now(), now()->addDays(30)]))
                    ->label('Expiring in 30 Days'),
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
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductBatches::route('/'),
            'create' => Pages\CreateProductBatch::route('/create'),
            'edit' => Pages\EditProductBatch::route('/{record}/edit'),
        ];
    }
}

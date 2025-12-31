<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Product Catalog';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100),
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name_sanskrit')
                            ->label('Sanskrit Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name_hindi')
                            ->label('Hindi Name')
                            ->maxLength(255),
                        Forms\Components\Select::make('product_type')
                            ->required()
                            ->options([
                                'choorna' => 'Choorna (Powder)',
                                'tailam' => 'Tailam (Oil)',
                                'ghritam' => 'Ghritam (Ghee)',
                                'rasayana' => 'Rasayana',
                                'capsules' => 'Capsules/Tablets',
                                'tea' => 'Tea',
                                'honey' => 'Honey',
                            ]),
                        Forms\Components\Select::make('unit_type')
                            ->required()
                            ->options([
                                'weight' => 'Weight (GMS/KG)',
                                'volume' => 'Volume (ML/L)',
                                'piece' => 'Piece',
                            ]),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Product Details')
                    ->schema([
                        Forms\Components\Select::make('tax_category')
                            ->required()
                            ->options([
                                'essential' => 'Essential (5% GST)',
                                'standard' => 'Standard (12% GST)',
                                'luxury' => 'Luxury (18% GST)',
                            ])
                            ->default('standard'),
                        Forms\Components\Toggle::make('has_variants')
                            ->label('Has Multiple Variants'),
                        Forms\Components\Toggle::make('requires_batch')
                            ->label('Batch Tracking Required')
                            ->default(true),
                        Forms\Components\Toggle::make('requires_expiry')
                            ->label('Expiry Tracking Required')
                            ->default(true),
                        Forms\Components\TextInput::make('shelf_life_months')
                            ->label('Shelf Life (Months)')
                            ->numeric()
                            ->minValue(1),
                        Forms\Components\Toggle::make('is_prescription_required')
                            ->label('Prescription Required'),
                        Forms\Components\TagsInput::make('ingredients')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('usage_instructions')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->label('Active')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('product_type')
                    ->badge()
                    ->colors([
                        'success' => 'choorna',
                        'warning' => 'tailam',
                        'info' => 'ghritam',
                        'primary' => 'capsules',
                    ]),
                Tables\Columns\TextColumn::make('variants_count')
                    ->counts('variants')
                    ->label('Variants'),
                Tables\Columns\IconColumn::make('active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('product_type')
                    ->options([
                        'choorna' => 'Choorna',
                        'tailam' => 'Tailam',
                        'ghritam' => 'Ghritam',
                        'capsules' => 'Capsules',
                        'tea' => 'Tea',
                        'honey' => 'Honey',
                    ]),
                Tables\Filters\TernaryFilter::make('active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}

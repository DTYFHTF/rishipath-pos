<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Services\OrganizationContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Product Catalog';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('view_products') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('create_products') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('edit_products') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasPermission('delete_products') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Placeholder::make('sku_info')
                            ->label('SKU')
                            ->content(fn ($record) => $record?->sku ?? 'Will be auto-generated')
                            ->visible(fn ($record) => $record !== null),

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
                                'choorna' => '🌾 Choorna (Powder)',
                                'tailam' => '🪧 Tailam (Oil)',
                                'ghritam' => '🧈 Ghritam (Ghee)',
                                'rasayana' => '💊 Rasayana',
                                'capsules' => '💊 Capsules/Tablets',
                                'tea' => '🍵 Tea',
                                'honey' => '🍯 Honey',
                                'others' => '🧾 Others',
                            ])
                            ->searchable()
                            ->helperText('Traditional Ayurvedic product classification'),
                        Forms\Components\Select::make('unit_type')
                            ->required()
                            ->options([
                                'weight' => '⚖️ Weight (GMS/KG)',
                                'volume' => '🧪 Volume (ML/L)',
                                'piece' => '📦 Piece',
                            ])
                            ->reactive()
                            ->helperText('Base measurement unit for this product'),
                        Forms\Components\RichEditor::make('description')
                            ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link'])
                            ->helperText('Product description for online catalog and labels')
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
                            ->placeholder('Add ingredient (press Enter)')
                            ->helperText('List all ingredients')
                            ->columnSpanFull(),
                        Forms\Components\RichEditor::make('usage_instructions')
                            ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList'])
                            ->helperText('Dosage, timing, and usage guidelines')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Product Images')
                    ->schema([
                        Forms\Components\FileUpload::make('image_1')
                            ->label('Product Image 1')
                            ->image()
                            ->directory('product-images')
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '1:1',
                                '4:3',
                            ])
                            ->maxSize(2048),
                        Forms\Components\FileUpload::make('image_2')
                            ->label('Product Image 2')
                            ->image()
                            ->directory('product-images')
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '1:1',
                                '4:3',
                            ])
                            ->maxSize(2048),
                        Forms\Components\FileUpload::make('image_3')
                            ->label('Product Image 3')
                            ->image()
                            ->directory('product-images')
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '1:1',
                                '4:3',
                            ])
                            ->maxSize(2048),
                    ])
                    ->columns(3),

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
            ->modifyQueryUsing(function ($query) {
                $orgId = OrganizationContext::getCurrentOrganizationId() 
                    ?? auth()->user()?->organization_id ?? 1;
                return $query->where('organization_id', $orgId);
            })
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
                        'secondary' => 'others',
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
                        'others' => 'Others',
                    ]),
                Tables\Filters\TernaryFilter::make('active'),
            ])
            ->actions([
                Tables\Actions\Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-information-circle')
                    ->color('info')
                    ->modalHeading(fn ($record) => $record->name . ' - Inventory Details')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record) => view('filament.pages.product-detail-modal', ['product' => $record]))
                    ->slideOver(),
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

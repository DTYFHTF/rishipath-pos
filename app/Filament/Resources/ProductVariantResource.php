<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductVariantResource\Pages;
use App\Models\ProductVariant;
use App\Services\BarcodeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductVariantResource extends Resource
{
    protected static ?string $model = ProductVariant::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Product Catalog';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100),
                        Forms\Components\TextInput::make('pack_size')
                            ->label('Pack Size')
                            ->required()
                            ->numeric()
                            ->minValue(0.001),
                        Forms\Components\Select::make('unit')
                            ->required()
                            ->options([
                                'GMS' => 'Grams (GMS)',
                                'KG' => 'Kilograms (KG)',
                                'ML' => 'Milliliters (ML)',
                                'L' => 'Liters (L)',
                                'PCS' => 'Pieces (PCS)',
                            ]),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('base_price')
                            ->label('Base Price')
                            ->numeric()
                            ->prefix('₹')
                            ->minValue(0),
                        Forms\Components\TextInput::make('cost_price')
                            ->label('Cost Price')
                            ->numeric()
                            ->prefix('₹')
                            ->minValue(0),
                        Forms\Components\TextInput::make('mrp_india')
                            ->label('MRP (India)')
                            ->numeric()
                            ->prefix('₹')
                            ->minValue(0),
                        Forms\Components\TextInput::make('selling_price_nepal')
                            ->label('Selling Price (Nepal)')
                            ->numeric()
                            ->prefix('NPR ')
                            ->minValue(0),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Additional Details')
                    ->schema([
                        Forms\Components\TextInput::make('barcode')
                            ->label('Barcode')
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->helperText('Leave empty to auto-generate'),
                        Forms\Components\TextInput::make('hsn_code')
                            ->label('HSN Code')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('weight')
                            ->label('Weight (for shipping)')
                            ->numeric()
                            ->suffix('kg')
                            ->minValue(0),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Variant Images')
                    ->schema([
                        Forms\Components\FileUpload::make('image_1')
                            ->label('Variant Image 1')
                            ->image()
                            ->directory('variant-images')
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '1:1',
                                '4:3',
                            ])
                            ->maxSize(2048),
                        Forms\Components\FileUpload::make('image_2')
                            ->label('Variant Image 2')
                            ->image()
                            ->directory('variant-images')
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '1:1',
                                '4:3',
                            ])
                            ->maxSize(2048),
                        Forms\Components\FileUpload::make('image_3')
                            ->label('Variant Image 3')
                            ->image()
                            ->directory('variant-images')
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
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pack_size')
                    ->label('Size')
                    ->formatStateUsing(fn ($record) => $record->pack_size.' '.$record->unit),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->copyable()
                    ->placeholder('No barcode')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('mrp_india')
                    ->label('MRP')
                    ->money('INR')
                    ->sortable(),

                Tables\Columns\IconColumn::make('active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('has_barcode')
                    ->label('Barcode Status')
                    ->placeholder('All Variants')
                    ->trueLabel('With Barcode')
                    ->falseLabel('Without Barcode')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('barcode'),
                        false: fn ($query) => $query->whereNull('barcode'),
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('generate_barcode')
                    ->label('Generate')
                    ->icon('heroicon-o-qr-code')
                    ->color('primary')
                    ->visible(fn (ProductVariant $record) => ! $record->barcode)
                    ->action(function (ProductVariant $record) {
                        $barcodeService = new BarcodeService;
                        $barcode = $barcodeService->generateBarcodeForVariant($record);

                        Notification::make()
                            ->success()
                            ->title('Barcode Generated')
                            ->body("Barcode: {$barcode}")
                            ->send();
                    }),

                Tables\Actions\Action::make('view_barcode')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn (ProductVariant $record) => (bool) $record->barcode)
                    ->modalHeading(fn (ProductVariant $record) => 'Barcode: '.$record->barcode)
                    ->modalContent(fn (ProductVariant $record) => view('filament.components.barcode-display', [
                        'record' => $record,
                        'barcodeImage' => (new BarcodeService)->generateBarcodeImage($record->barcode),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('generate_barcodes')
                        ->label('Generate Barcodes')
                        ->icon('heroicon-o-qr-code')
                        ->color('primary')
                        ->action(function ($records) {
                            $barcodeService = new BarcodeService;
                            $generated = 0;

                            foreach ($records as $record) {
                                if (! $record->barcode) {
                                    $barcodeService->generateBarcodeForVariant($record);
                                    $generated++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('Barcodes Generated')
                                ->body("{$generated} barcode(s) generated successfully")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('product.name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductVariants::route('/'),
            'create' => Pages\CreateProductVariant::route('/create'),
            'edit' => Pages\EditProductVariant::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $withoutBarcode = static::getModel()::whereNull('barcode')->count();

        return $withoutBarcode > 0 ? (string) $withoutBarcode : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}

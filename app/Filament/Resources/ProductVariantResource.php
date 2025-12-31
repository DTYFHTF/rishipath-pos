<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductVariantResource\Pages;
use App\Models\ProductVariant;
use App\Services\BarcodeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ProductVariantResource extends Resource
{
    protected static ?string $model = ProductVariant::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    
    protected static ?string $navigationGroup = 'Product Catalog';
    
    protected static ?int $navigationSort = 2;

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
                    ->formatStateUsing(fn ($record) => $record->pack_size . ' ' . $record->unit),
                    
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
                    ->visible(fn (ProductVariant $record) => !$record->barcode)
                    ->action(function (ProductVariant $record) {
                        $barcodeService = new BarcodeService();
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
                    ->visible(fn (ProductVariant $record) => (bool)$record->barcode)
                    ->modalHeading(fn (ProductVariant $record) => 'Barcode: ' . $record->barcode)
                    ->modalContent(fn (ProductVariant $record) => view('filament.components.barcode-display', [
                        'record' => $record,
                        'barcodeImage' => (new BarcodeService())->generateBarcodeImage($record->barcode),
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
                            $barcodeService = new BarcodeService();
                            $generated = 0;
                            
                            foreach ($records as $record) {
                                if (!$record->barcode) {
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

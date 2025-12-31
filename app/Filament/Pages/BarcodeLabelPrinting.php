<?php

namespace App\Filament\Pages;

use App\Models\ProductVariant;
use App\Services\BarcodeService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class BarcodeLabelPrinting extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-printer';

    protected static string $view = 'filament.pages.barcode-label-printing';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Barcode Labels';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('view_product_batches') ?? false;
    }

    public ?array $data = [];
    public $showPrice = true;
    public $showSKU = true;
    public $generatedLabels = [];

    public function mount(): void
    {
        $this->form->fill([
            'selectedVariants' => [],
            'copiesPerLabel' => 1,
            'labelSize' => 'medium',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Select::make('selectedVariants')
                    ->label('Select Products')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(function () {
                        return ProductVariant::with('product')
                            ->whereNotNull('barcode')
                            ->get()
                            ->mapWithKeys(function ($variant) {
                                return [
                                    $variant->id => $variant->product->name . ' - ' . $variant->pack_size . $variant->unit . ' (' . $variant->barcode . ')',
                                ];
                            });
                    })
                    ->required()
                    ->helperText('Only products with barcodes are shown'),

                TextInput::make('copiesPerLabel')
                    ->label('Copies per Product')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->maxValue(100)
                    ->required(),

                Select::make('labelSize')
                    ->label('Label Size')
                    ->options([
                        'small' => 'Small (40x25mm)',
                        'medium' => 'Medium (50x30mm)',
                        'large' => 'Large (60x40mm)',
                    ])
                    ->default('medium')
                    ->required(),
            ]);
    }

    public function generateLabels()
    {
        $data = $this->form->getState();

        if (empty($data['selectedVariants'])) {
            Notification::make()
                ->danger()
                ->title('No Products Selected')
                ->body('Please select at least one product')
                ->send();
            return;
        }

        $barcodeService = new BarcodeService();
        
        $this->generatedLabels = $barcodeService->generateBulkLabels(
            $data['selectedVariants'],
            $data['copiesPerLabel']
        );

        Notification::make()
            ->success()
            ->title('Labels Generated')
            ->body(count($this->generatedLabels) . ' labels ready to print')
            ->send();
    }

    public function clearLabels()
    {
        $this->generatedLabels = [];
        $this->form->fill([
            'selectedVariants' => [],
            'copiesPerLabel' => 1,
            'labelSize' => 'medium',
        ]);
    }

    public function generateAllBarcodes()
    {
        $barcodeService = new BarcodeService();
        $variantsWithoutBarcode = ProductVariant::whereNull('barcode')->pluck('id')->toArray();
        
        if (empty($variantsWithoutBarcode)) {
            Notification::make()
                ->info()
                ->title('All Products Have Barcodes')
                ->body('No products need barcode generation')
                ->send();
            return;
        }

        $results = $barcodeService->generateBatchBarcodes($variantsWithoutBarcode);
        $successCount = collect($results)->where('success', true)->count();

        Notification::make()
            ->success()
            ->title('Barcodes Generated')
            ->body("{$successCount} barcodes generated successfully")
            ->send();
    }

    public function getBarcodeStats(): array
    {
        $barcodeService = new BarcodeService();
        return $barcodeService->getBarcodeStats();
    }
}

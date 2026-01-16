<?php

namespace App\Filament\Pages;

use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\InventoryService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class StockTransfer extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string $view = 'filament.pages.stock-transfer';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Transfer';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('transfer_stock') ?? false;
    }

    public $productVariantId;

    public $fromStoreId;

    public $toStoreId;

    public $quantity;

    public $notes;

    public $fromStock = 0;

    public $toStock = 0;

    public function mount(): void
    {
        $stores = Store::where('active', true)->get();
        if ($stores->count() >= 2) {
            $this->fromStoreId = $stores->first()->id;
            $this->toStoreId = $stores->skip(1)->first()->id;
        } elseif ($stores->count() >= 1) {
            $this->fromStoreId = $stores->first()->id;
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('productVariantId')
                    ->label('Product')
                    ->options(function () {
                        return ProductVariant::with('product')
                            ->get()
                            ->mapWithKeys(fn ($variant) => [
                                $variant->id => "{$variant->product->name} - {$variant->pack_size}{$variant->unit} ({$variant->sku})",
                            ]);
                    })
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateStockLevels()),

                Select::make('fromStoreId')
                    ->label('From Store')
                    ->options(Store::where('active', true)->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateStockLevels())
                    ->helperText(fn () => "Available: {$this->fromStock}"),

                Select::make('toStoreId')
                    ->label('To Store')
                    ->options(Store::where('active', true)->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateStockLevels())
                    ->helperText(fn () => "Current: {$this->toStock}"),

                TextInput::make('quantity')
                    ->label('Quantity to Transfer')
                    ->required()
                    ->numeric()
                    ->minValue(0.001)
                    ->maxValue(fn () => $this->fromStock)
                    ->step(0.001),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2)
                    ->placeholder('Reason for transfer...'),
            ]);
    }

    public function updateStockLevels(): void
    {
        if ($this->productVariantId) {
            $this->fromStock = $this->fromStoreId
                ? InventoryService::getStock($this->productVariantId, $this->fromStoreId)
                : 0;
            $this->toStock = $this->toStoreId
                ? InventoryService::getStock($this->productVariantId, $this->toStoreId)
                : 0;
        }
    }

    public function submitTransfer(): void
    {
        $this->validate([
            'productVariantId' => 'required',
            'fromStoreId' => 'required',
            'toStoreId' => 'required|different:fromStoreId',
            'quantity' => 'required|numeric|min:0.001|max:'.$this->fromStock,
        ]);

        try {
            InventoryService::transferStock(
                $this->productVariantId,
                $this->fromStoreId,
                $this->toStoreId,
                $this->quantity,
                $this->notes,
                Auth::id()
            );

            Notification::make()
                ->success()
                ->title('Transfer Completed')
                ->body("Transferred {$this->quantity} units successfully")
                ->send();

            // Reset form
            $this->productVariantId = null;
            $this->quantity = null;
            $this->notes = null;
            $this->fromStock = 0;
            $this->toStock = 0;

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Transfer Failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function getRecentTransfers()
    {
        return \App\Models\InventoryMovement::with(['productVariant.product', 'user', 'store'])
            ->where('type', 'transfer')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }
}

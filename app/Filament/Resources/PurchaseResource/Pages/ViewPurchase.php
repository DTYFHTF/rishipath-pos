<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use App\Models\PurchaseReturn;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;

class ViewPurchase extends ViewRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('receive')
                ->label('Receive Items')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => in_array($this->record->status, ['draft', 'ordered', 'partial']))
                ->requiresConfirmation()
                ->action(fn () => $this->record->receive()),

            Actions\Action::make('record_payment')
                ->label('Record Payment')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->visible(fn () => $this->record->payment_status !== 'paid' && $this->record->status === 'received')
                ->form([
                    \Filament\Forms\Components\TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->required()
                        ->prefix('₹')
                        ->default(fn () => $this->record->outstanding_amount),

                        \Filament\Forms\Components\Select::make('payment_method')
                        ->options([
                            'cash' => 'Cash',
                            'bank_transfer' => 'Bank Transfer',
                            'cheque' => 'Cheque',
                            'upi' => 'QR',
                        ])
                        ->required(),

                    \Filament\Forms\Components\TextInput::make('reference')
                        ->label('Reference #'),

                    \Filament\Forms\Components\Textarea::make('notes')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $this->record->recordPayment(
                        $data['amount'],
                        $data['payment_method'],
                        $data['reference'] ?? null,
                        $data['notes'] ?? null
                    );
                }),

            Actions\Action::make('process_return')
                ->label('Process Return')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn () => $this->record->status === 'received' && $this->record->items()->where('quantity_received', '>', 0)->exists())
                ->form(function () {
                    $items = $this->record->items()
                        ->where('quantity_received', '>', 0)
                        ->with('productVariant')
                        ->get();

                    $itemFields = [];
                    
                    foreach ($items as $item) {
                        $alreadyReturned = PurchaseReturn::where('purchase_item_id', $item->id)
                            ->sum('quantity_returned');
                        $availableToReturn = $item->quantity_received - $alreadyReturned;

                        if ($availableToReturn > 0) {
                            $variant = $item->productVariant;
                            $packSize = $variant ? $variant->pack_size : 1;
                            $unit = $item->unit;

                            $itemFields["item_{$item->id}"] = \Filament\Forms\Components\TextInput::make("item_{$item->id}")
                                ->label("{$item->product_name} ({$item->product_sku}) - {$packSize}{$unit}")
                                ->helperText("Received: {$item->quantity_received}, Returned: {$alreadyReturned}, Available: {$availableToReturn}")
                                ->numeric()
                                ->minValue(0)
                                ->maxValue($availableToReturn)
                                ->placeholder('0')
                                ->suffix('qty')
                                ->default(0);
                        }
                    }

                    if (empty($itemFields)) {
                        return [
                            \Filament\Forms\Components\Placeholder::make('no_items')
                                ->content('All items have been fully returned.'),
                        ];
                    }

                    return array_merge(
                        $itemFields,
                        [
                            \Filament\Forms\Components\Select::make('reason')
                                ->label('Return Reason')
                                ->options([
                                    'Defective' => 'Defective',
                                    'Damaged' => 'Damaged',
                                    'Wrong Product' => 'Wrong Product',
                                    'Expired' => 'Expired',
                                    'Poor Quality' => 'Poor Quality',
                                    'Overstocked' => 'Overstocked',
                                    'Other' => 'Other',
                                ])
                                ->required(),

                            \Filament\Forms\Components\Textarea::make('notes')
                                ->label('Additional Notes')
                                ->rows(3)
                                ->placeholder('Enter any additional details about this return...'),
                        ]
                    );
                })
                ->action(function (array $data) {
                    $returnItems = [];
                    $reason = $data['reason'] ?? 'Other';
                    $notes = $data['notes'] ?? null;

                    // Extract return quantities from form data
                    foreach ($data as $key => $value) {
                        if (strpos($key, 'item_') === 0 && $value > 0) {
                            $itemId = str_replace('item_', '', $key);
                            $returnItems[$itemId] = $value;
                        }
                    }

                    if (empty($returnItems)) {
                        Notification::make()
                            ->warning()
                            ->title('No items to return')
                            ->body('Please enter at least one item quantity to return.')
                            ->send();
                        return;
                    }

                    try {
                        $returns = $this->record->processReturn($returnItems, $reason, $notes);
                        
                        $totalQty = array_sum(array_column($returns, 'quantity_returned'));
                        $totalAmount = array_sum(array_column($returns, 'return_amount'));

                        Notification::make()
                            ->success()
                            ->title('Return Processed Successfully')
                            ->body("Returned {$totalQty} items worth ₹" . number_format($totalAmount, 2))
                            ->send();

                        // Refresh the page to show updated data
                        $this->refreshFormData(['mountedActionsData']);
                        
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Return Failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Purchase Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('purchase_number')
                            ->label('Purchase #'),
                        Infolists\Components\TextEntry::make('supplier.name')
                            ->label('Supplier'),
                        Infolists\Components\TextEntry::make('store.name')
                            ->label('Store'),
                        Infolists\Components\TextEntry::make('purchase_date')
                            ->label('Purchase Date')
                            ->date(),
                        Infolists\Components\TextEntry::make('expected_delivery_date')
                            ->label('Expected Delivery')
                            ->date(),
                        Infolists\Components\TextEntry::make('received_date')
                            ->label('Received Date')
                            ->date()
                            ->placeholder('Not received yet'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'gray',
                                'ordered' => 'warning',
                                'partial' => 'info',
                                'received' => 'success',
                                'cancelled' => 'danger',
                            }),
                        Infolists\Components\TextEntry::make('payment_status')
                            ->label('Payment Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'unpaid' => 'danger',
                                'partial' => 'warning',
                                'paid' => 'success',
                            }),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Items')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->schema([
                                Infolists\Components\TextEntry::make('product_name')
                                    ->label('Product'),
                                Infolists\Components\TextEntry::make('product_sku')
                                    ->label('SKU'),
                                Infolists\Components\TextEntry::make('quantity_ordered')
                                    ->label('Quantity')
                                    ->formatStateUsing(function ($record) {
                                        $variant = $record->productVariant;
                                        $packSize = $variant ? $variant->pack_size : 1;
                                        $unit = $record->unit;
                                        return "{$packSize}{$unit} × {$record->quantity_ordered} qty";
                                    }),
                                Infolists\Components\TextEntry::make('quantity_received')
                                    ->label('Received')
                                    ->formatStateUsing(function ($record) {
                                        $variant = $record->productVariant;
                                        $packSize = $variant ? $variant->pack_size : 1;
                                        $unit = $record->unit;
                                        return "{$packSize}{$unit} × {$record->quantity_received} qty";
                                    }),
                                Infolists\Components\TextEntry::make('unit_cost')
                                    ->label('Unit Cost')
                                    ->money('INR'),
                                Infolists\Components\TextEntry::make('line_total')
                                    ->label('Total')
                                    ->money('INR'),
                                Infolists\Components\TextEntry::make('expiry_date')
                                    ->label('Expiry')
                                    ->date()
                                    ->placeholder('N/A'),
                            ])
                            ->columns(4)
                            ->grid(1),
                    ]),

                Infolists\Components\Section::make('Returns')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('returns')
                            ->schema([
                                Infolists\Components\TextEntry::make('return_number')
                                    ->label('Return #'),
                                Infolists\Components\TextEntry::make('return_date')
                                    ->label('Date')
                                    ->date(),
                                Infolists\Components\TextEntry::make('productVariant.product.name')
                                    ->label('Product'),
                                Infolists\Components\TextEntry::make('quantity_returned')
                                    ->label('Quantity')
                                    ->suffix(' qty'),
                                Infolists\Components\TextEntry::make('return_amount')
                                    ->label('Amount')
                                    ->money('INR'),
                                Infolists\Components\TextEntry::make('reason')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'refunded' => 'info',
                                    }),
                                Infolists\Components\TextEntry::make('notes')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ])
                            ->columns(4)
                            ->grid(1),
                    ])
                    ->visible(fn () => $this->record->returns()->exists())
                    ->collapsible(),

                Infolists\Components\Section::make('Totals')
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal')
                            ->money('INR'),
                        Infolists\Components\TextEntry::make('tax_amount')
                            ->label('Tax')
                            ->money('INR'),
                        Infolists\Components\TextEntry::make('discount_amount')
                            ->label('Discount')
                            ->money('INR'),
                        Infolists\Components\TextEntry::make('shipping_cost')
                            ->label('Shipping')
                            ->money('INR'),
                        Infolists\Components\TextEntry::make('total')
                            ->money('INR')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('amount_paid')
                            ->label('Paid')
                            ->money('INR'),
                        Infolists\Components\TextEntry::make('outstanding_amount')
                            ->label('Outstanding')
                            ->money('INR')
                            ->color('danger'),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Additional Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('supplier_invoice_number')
                            ->label('Supplier Invoice #')
                            ->placeholder('N/A'),
                        Infolists\Components\TextEntry::make('notes')
                            ->markdown()
                            ->placeholder('No notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}

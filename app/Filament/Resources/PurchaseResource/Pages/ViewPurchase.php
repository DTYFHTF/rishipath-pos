<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

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
                            'upi' => 'UPI',
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

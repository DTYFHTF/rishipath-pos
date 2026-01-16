<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

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
}

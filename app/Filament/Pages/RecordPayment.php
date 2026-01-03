<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\Store;
use App\Services\CustomerLedgerService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class RecordPayment extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Customers';
    protected static ?int $navigationSort = 4;
    protected static string $view = 'filament.pages.record-payment';
    protected static ?string $title = 'Record Customer Payment';

    public ?array $data = [];
    public ?float $outstandingBalance = null;
    public ?float $currentBalance = null;

    public function mount(): void
    {
        $this->form->fill([
            'transaction_date' => now()->format('Y-m-d'),
            'store_id' => auth()->user()->store_id ?? Store::first()?->id,
            'payment_method' => 'cash',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('customer_id')
                    ->label('Customer')
                    ->options(Customer::where('active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        if ($state) {
                            $customer = Customer::find($state);
                            $this->outstandingBalance = $customer->getOutstandingAmount();
                            $this->currentBalance = $customer->getOutstandingBalance();
                        } else {
                            $this->outstandingBalance = null;
                            $this->currentBalance = null;
                        }
                    }),

                Select::make('store_id')
                    ->label('Store')
                    ->options(Store::pluck('name', 'id'))
                    ->required(),

                DatePicker::make('transaction_date')
                    ->label('Payment Date')
                    ->required()
                    ->default(now()),

                TextInput::make('amount')
                    ->label('Payment Amount')
                    ->required()
                    ->numeric()
                    ->prefix('₹')
                    ->minValue(0.01)
                    ->step(0.01)
                    ->placeholder('0.00'),

                Select::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'cash' => 'Cash',
                        'card' => 'Card',
                        'upi' => 'UPI',
                        'bank_transfer' => 'Bank Transfer',
                        'cheque' => 'Cheque',
                    ])
                    ->required()
                    ->default('cash'),

                TextInput::make('reference_number')
                    ->label('Reference/Transaction Number')
                    ->helperText('Cheque number, transaction ID, etc.')
                    ->placeholder('Optional'),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->placeholder('Optional notes about this payment')
                    ->columnSpanFull(),
            ])
            ->statePath('data')
            ->columns(2);
    }

    public function recordPayment()
    {
        $data = $this->form->getState();

        if (!$data['customer_id']) {
            Notification::make()
                ->title('Please select a customer')
                ->danger()
                ->send();
            return;
        }

        try {
            $customer = Customer::find($data['customer_id']);
            
            $ledgerService = new CustomerLedgerService();
            $ledgerService->recordPayment($customer, [
                'organization_id' => auth()->user()->organization_id,
                'store_id' => $data['store_id'],
                'amount' => $data['amount'],
                'transaction_date' => $data['transaction_date'],
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            Notification::make()
                ->title('Payment Recorded Successfully')
                ->success()
                ->body("₹{$data['amount']} received from {$customer->name}")
                ->send();

            // Reset form
            $this->form->fill([
                'transaction_date' => now()->format('Y-m-d'),
                'store_id' => auth()->user()->store_id ?? Store::first()?->id,
                'payment_method' => 'cash',
            ]);
            
            $this->outstandingBalance = null;
            $this->currentBalance = null;

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error Recording Payment')
                ->danger()
                ->body($e->getMessage())
                ->send();
        }
    }
}

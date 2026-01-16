<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;

class CustomerLedgerReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?int $navigationSort = 7;
    protected static string $view = 'filament.pages.customer-ledger-report';
    protected static ?string $title = 'Customer Ledger';

    public ?int $customer_id = null;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public $ledgerEntries = [];
    public $customerData = null;
    public $summary = [];

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
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
                    ->afterStateUpdated(fn () => $this->generateReport()),

                DatePicker::make('start_date')
                    ->label('Start Date')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn () => $this->generateReport()),

                DatePicker::make('end_date')
                    ->label('End Date')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn () => $this->generateReport()),
            ])
            ->columns(3);
    }

    public function generateReport()
    {
        if (!$this->customer_id) {
            $this->ledgerEntries = [];
            $this->customerData = null;
            $this->summary = [];
            return;
        }

        $customer = Customer::find($this->customer_id);
        if (!$customer) {
            return;
        }

        $this->customerData = [
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'customer_code' => $customer->customer_code,
        ];

        $query = CustomerLedgerEntry::forCustomer($this->customer_id)
            ->with(['store', 'createdBy'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($this->start_date) {
            $query->where('transaction_date', '>=', $this->start_date);
        }

        if ($this->end_date) {
            $query->where('transaction_date', '<=', $this->end_date);
        }

        $this->ledgerEntries = $query->get()->map(function ($entry) {
            return [
                'id' => $entry->id,
                'date' => $entry->transaction_date->format('d M Y'),
                'description' => $entry->description,
                'reference' => $entry->reference_number,
                'type' => $entry->entry_type,
                'debit' => $entry->debit_amount,
                'credit' => $entry->credit_amount,
                'balance' => $entry->balance,
                'status' => $entry->status,
                'store' => $entry->store?->name,
                'payment_method' => $entry->payment_method,
                'created_by' => $entry->createdBy?->name,
            ];
        })->toArray();

        // Calculate summary
        $totalDebit = collect($this->ledgerEntries)->sum('debit');
        $totalCredit = collect($this->ledgerEntries)->sum('credit');
        $currentBalance = CustomerLedgerEntry::getCustomerBalance($this->customer_id);
        $outstanding = CustomerLedgerEntry::getCustomerOutstanding($this->customer_id);

        $this->summary = [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'net_amount' => $totalDebit - $totalCredit,
            'current_balance' => $currentBalance,
            'outstanding' => $outstanding,
        ];
    }

    public function downloadPdf()
    {
        // TODO: Implement PDF download
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'PDF download feature coming soon!',
        ]);
    }

    public function downloadExcel()
    {
        // TODO: Implement Excel download
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Excel download feature coming soon!',
        ]);
    }
}

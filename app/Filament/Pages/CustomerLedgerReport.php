<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Services\OrganizationContext;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

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

    public ?string $entry_type = null;

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
                Section::make('Filters')
                    ->schema([
                        Select::make('customer_id')
                            ->label('Customer')
                            ->options(Customer::where('organization_id', OrganizationContext::getCurrentOrganizationId() ?? \Illuminate\Support\Facades\Auth::user()?->organization_id ?? 1)
                                ->where('active', true)
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->columnSpan(['sm' => 2])
                            ->afterStateUpdated(fn () => $this->generateReport()),

                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required()
                            ->native(false)
                            ->live()
                            ->columnSpan(1)
                            ->afterStateUpdated(fn () => $this->generateReport()),

                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->required()
                            ->native(false)
                            ->live()
                            ->columnSpan(1)
                            ->afterStateUpdated(fn () => $this->generateReport()),

                        Select::make('entry_type')
                            ->label('Type')
                            ->options([
                                'receivable' => 'Receivable',
                                'payment' => 'Payment',
                                'credit_note' => 'Credit Note',
                            ])
                            ->placeholder('All Types')
                            ->live()
                            ->columnSpan(1)
                            ->afterStateUpdated(fn () => $this->generateReport()),
                    ])
                    ->columns(5)
                    ->compact(),
            ]);
    }

    public function generateReport()
    {
        if (! $this->customer_id) {
            $this->ledgerEntries = [];
            $this->customerData = null;
            $this->summary = [];

            return;
        }

        $customer = Customer::find($this->customer_id);
        if (! $customer) {
            return;
        }

        $this->customerData = [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'customer_code' => $customer->customer_code,
            'loyalty_points' => $customer->loyalty_points ?? 0,
        ];

        $query = CustomerLedgerEntry::forCustomer($this->customer_id)
            ->where('organization_id', OrganizationContext::getCurrentOrganizationId() ?? \Illuminate\Support\Facades\Auth::user()?->organization_id ?? 1)
            ->with(['store', 'createdBy'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($this->start_date) {
            $query->where('transaction_date', '>=', $this->start_date);
        }

        if ($this->end_date) {
            $query->where('transaction_date', '<=', $this->end_date);
        }

        if ($this->entry_type) {
            $query->where('entry_type', $this->entry_type);
        }

        $this->ledgerEntries = $query->get()->map(function ($entry) {
            return [
                'id' => $entry->id,
                'date' => $entry->transaction_date->format('d M Y'),
                'description' => $entry->description,
                'reference' => $entry->reference_number,
                'reference_id' => $entry->reference_id,
                'reference_type' => $entry->reference_type,
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
        if (empty($this->ledgerEntries) || ! $this->customerData) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'No data to export. Please generate a report first.',
            ]);

            return;
        }

        $pdf = \Barryvdh\DomPdf\Facade\Pdf::loadView('exports.customer-ledger-pdf', [
            'customerData' => $this->customerData,
            'ledgerEntries' => $this->ledgerEntries,
            'summary' => $this->summary,
            'startDate' => $this->start_date,
            'endDate' => $this->end_date,
        ]);

        $filename = 'customer-ledger-' . str_replace(' ', '-', strtolower($this->customerData['name'])) . '-' . now()->format('Y-m-d') . '.pdf';

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename
        );
    }

    public function downloadExcel()
    {
        if (empty($this->ledgerEntries) || ! $this->customerData) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'No data to export. Please generate a report first.',
            ]);

            return;
        }

        $export = new \App\Exports\CustomerLedgerExport(
            $this->customerData,
            $this->ledgerEntries,
            $this->summary,
            $this->start_date,
            $this->end_date
        );

        $filename = 'customer-ledger-' . str_replace(' ', '-', strtolower($this->customerData['name'])) . '-' . now()->format('Y-m-d') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\Supplier;
use App\Models\CustomerLedgerEntry;
use App\Services\OrganizationContext;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class SupplierLedgerReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 8;

    protected static string $view = 'filament.pages.supplier-ledger-report';

    protected static ?string $title = 'Supplier Ledger';

    public ?int $supplier_id = null;

    public ?string $start_date = null;

    public ?string $end_date = null;

    public ?string $entry_type = null;

    public $ledgerEntries = [];

    public $supplierData = null;

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
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->options(Supplier::where('organization_id', OrganizationContext::getCurrentOrganizationId() ?? \Illuminate\Support\Facades\Auth::user()?->organization_id ?? 1)
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
                                'receivable' => 'Payable',
                                'payment' => 'Payment',
                                'debit_note' => 'Debit Note',
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
        if (! $this->supplier_id) {
            $this->ledgerEntries = [];
            $this->supplierData = null;
            $this->summary = [];

            return;
        }

        $supplier = Supplier::find($this->supplier_id);

        if (! $supplier) {
            $this->ledgerEntries = [];
            $this->supplierData = null;
            $this->summary = [];

            return;
        }

        $this->supplierData = [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'supplier_code' => $supplier->supplier_code,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'contact_person' => $supplier->contact_person,
        ];

        $query = CustomerLedgerEntry::forSupplier($this->supplier_id)
            ->whereBetween('transaction_date', [$this->start_date, $this->end_date])
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc');

        if ($this->entry_type) {
            $query->where('entry_type', $this->entry_type);
        }

        $this->ledgerEntries = $query->get()->map(function ($entry) {
            return [
                'id' => $entry->id,
                'date' => $entry->transaction_date->format('d-M-Y'),
                'type' => ucwords(str_replace('_', ' ', $entry->entry_type)),
                'reference' => $entry->reference_number,
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
                'description' => $entry->description,
                // For suppliers: CREDIT = we owe them, DEBIT = we paid them (inverted from customers)
                'credit' => $entry->credit_amount, // Amount we owe
                'debit' => $entry->debit_amount,   // Amount we paid
                'balance' => $entry->balance,
                'status' => $entry->status,
                'store' => $entry->store?->name,
                'payment_method' => $entry->payment_method,
                'created_by' => $entry->createdBy?->name,
            ];
        })->toArray();

        $this->calculateSummary();
    }

    protected function calculateSummary()
    {
        $totalDebit = collect($this->ledgerEntries)->sum('debit');
        $totalCredit = collect($this->ledgerEntries)->sum('credit');
        $currentBalance = CustomerLedgerEntry::getLedgerableBalance(Supplier::find($this->supplier_id));

        $this->summary = [
            'total_debit' => $totalDebit,      // Total payments made
            'total_credit' => $totalCredit,    // Total amount owed
            'net_amount' => $totalCredit - $totalDebit,
            'current_balance' => $currentBalance, // Amount we currently owe
            'outstanding' => $currentBalance,
        ];
    }

    public function downloadPdf()
    {
        if (empty($this->ledgerEntries) || ! $this->supplierData) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'No data to export. Please generate a report first.',
            ]);

            return;
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.supplier-ledger-pdf', [
            'supplierData' => $this->supplierData,
            'ledgerEntries' => $this->ledgerEntries,
            'summary' => $this->summary,
            'startDate' => $this->start_date,
            'endDate' => $this->end_date,
        ]);

        $filename = 'supplier-ledger-' . str_replace(' ', '-', strtolower($this->supplierData['name'])) . '-' . now()->format('Y-m-d') . '.pdf';

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename
        );
    }

    public function downloadExcel()
    {
        if (empty($this->ledgerEntries) || ! $this->supplierData) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'No data to export. Please generate a report first.',
            ]);

            return;
        }

        $export = new \App\Exports\SupplierLedgerExport(
            $this->supplierData,
            $this->ledgerEntries,
            $this->summary,
            $this->start_date,
            $this->end_date
        );

        $filename = 'supplier-ledger-' . str_replace(' ', '-', strtolower($this->supplierData['name'])) . '-' . now()->format('Y-m-d') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\Purchase;
use App\Services\OrganizationContext;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class SupplierLedgerReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected $queryString = [
        'supplier_id' => ['except' => null],
        'show_transactions' => ['except' => false],
    ];

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

    public bool $show_transactions = false;

    public $transactions = [];

    public function mount(): void
    {
        // Get supplier_id from URL query parameter
        $supplierId = request()->query('supplier_id');
        
        $this->form->fill([
            'supplier_id' => $supplierId,
            'show_transactions' => request()->query('show_transactions') ? true : false,
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'),
        ]);
        
        // If supplier_id is provided, automatically load the ledger
        if ($supplierId) {
            $this->supplier_id = (int) $supplierId;
            $this->start_date = now()->startOfMonth()->format('Y-m-d');
            $this->end_date = now()->addDay()->format('Y-m-d');
            $this->generateReport();
        }
    }

    // Handlers to sync form state to component properties and regenerate report
    public function handleSupplierUpdated($state)
    {
        $this->supplier_id = $state ? (int) $state : null;
        $this->generateReport();
    }

    public function handleShowTransactionsUpdated($state)
    {
        $this->show_transactions = (bool) $state;
        $this->generateReport();
    }

    public function handleDateUpdated($field, $state)
    {
        if (in_array($field, ['start_date', 'end_date'])) {
            $this->{$field} = $state;
        }
        $this->generateReport();
    }

    public function handleEntryTypeUpdated($state)
    {
        $this->entry_type = $state;
        $this->generateReport();
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
                            ->afterStateUpdated(fn ($state) => $this->handleSupplierUpdated($state)),

                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required()
                            ->native(false)
                            ->live()
                            ->columnSpan(1)
                            ->afterStateUpdated(fn ($state) => $this->handleDateUpdated('start_date', $state)),

                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->required()
                            ->native(false)
                            ->live()
                            ->columnSpan(1)
                            ->afterStateUpdated(fn ($state) => $this->handleDateUpdated('end_date', $state)),

                        Select::make('entry_type')
                            ->label('Type')
                            ->options([
                                'purchase' => 'Purchase (Payable)',
                                'payment' => 'Payment',
                                'return' => 'Return',
                            ])
                            ->placeholder('All Types')
                            ->live()
                            ->columnSpan(1)
                            ->afterStateUpdated(fn ($state) => $this->handleEntryTypeUpdated($state)),

                        Toggle::make('show_transactions')
                            ->label('Show transactions')
                            ->helperText('Also load all purchases for this supplier in the selected date range')
                            ->columnSpan(1)
                            ->afterStateUpdated(fn ($state) => $this->handleShowTransactionsUpdated($state)),
                    ])
                    ->columns(5)
                    ->compact(),
            ]);
    }

    public function generateReport()
    {
        // Sync form state to component properties so Livewire query string updates
        try {
            $state = $this->form->getState();
            if (isset($state['supplier_id'])) {
                $this->supplier_id = $state['supplier_id'] ? (int) $state['supplier_id'] : null;
            }
            if (isset($state['show_transactions'])) {
                $this->show_transactions = (bool) $state['show_transactions'];
            }
            if (isset($state['start_date'])) {
                $this->start_date = $state['start_date'];
            }
            if (isset($state['end_date'])) {
                $this->end_date = $state['end_date'];
            }
            if (isset($state['entry_type'])) {
                $this->entry_type = $state['entry_type'];
            }
        } catch (\Throwable $e) {
            // If form not initialized yet, ignore
        }

        if (! $this->supplier_id) {
            $this->ledgerEntries = [];
            $this->supplierData = null;
            $this->summary = [];
            $this->transactions = [];

            return;
        }

        $supplier = Supplier::find($this->supplier_id);

        if (! $supplier) {
            $this->ledgerEntries = [];
            $this->supplierData = null;
            $this->summary = [];
            $this->transactions = [];

            return;
        }

        $this->supplierData = [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'supplier_code' => $supplier->supplier_code,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'contact_person' => $supplier->contact_person,
            'current_balance' => $supplier->current_balance ?? 0,
        ];

        // Query from supplier_ledger_entries (the dedicated supplier ledger table)
        $query = SupplierLedgerEntry::where('supplier_id', $this->supplier_id)
            ->with(['purchase', 'createdBy']);

        if ($this->start_date) {
            $query->where('created_at', '>=', $this->start_date);
        }
        if ($this->end_date) {
            $query->where('created_at', '<=', $this->end_date . ' 23:59:59');
        }

        if ($this->entry_type) {
            $query->where('type', $this->entry_type);
        }

        $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');

        $this->ledgerEntries = $query->get()->map(function ($entry) {
            $isPayable = $entry->type === 'purchase';
            return [
                'id' => $entry->id,
                'date' => $entry->created_at->format('d-M-Y'),
                'type' => ucwords($entry->type),
                'reference' => $entry->reference_number ?: ($entry->purchase ? $entry->purchase->purchase_number : '-'),
                'reference_type' => 'Purchase',
                'reference_id' => $entry->purchase_id,
                'description' => $entry->notes,
                // For suppliers: positive amount = purchase (we owe more), negative = payment/return (we owe less)
                'payable' => $isPayable ? abs($entry->amount) : 0,
                'paid' => !$isPayable ? abs($entry->amount) : 0,
                'balance' => $entry->balance_after,
                'status' => $isPayable ? 'pending' : 'completed',
                'payment_method' => $entry->payment_method,
                'created_by' => $entry->createdBy?->name,
            ];
        })->toArray();

        $this->calculateSummary($supplier);

        // Load transactions (purchases) if requested
        $this->transactions = [];
        if (!empty($this->show_transactions)) {
            $purchasesQuery = Purchase::where('supplier_id', $this->supplier_id)
                ->orderBy('purchase_date', 'desc')
                ->orderBy('created_at', 'desc');

            if ($this->start_date) {
                $purchasesQuery->where('purchase_date', '>=', $this->start_date);
            }
            if ($this->end_date) {
                $purchasesQuery->where('purchase_date', '<=', $this->end_date);
            }

            $this->transactions = $purchasesQuery->get()->map(function ($purchase) {
                return [
                    'id' => $purchase->id,
                    'purchase_number' => $purchase->purchase_number,
                    'date' => $purchase->purchase_date?->format('d M Y') ?? $purchase->created_at->format('d M Y'),
                    'status' => $purchase->status,
                    'payment_status' => $purchase->payment_status,
                    'total' => $purchase->total,
                    'amount_paid' => $purchase->amount_paid,
                    'outstanding' => $purchase->outstanding_amount,
                ];
            })->toArray();
        }
    }

    protected function calculateSummary(?Supplier $supplier = null)
    {
        $totalPayable = collect($this->ledgerEntries)->sum('payable');
        $totalPaid = collect($this->ledgerEntries)->sum('paid');
        $currentBalance = $supplier ? ($supplier->current_balance ?? 0) : 0;

        $this->summary = [
            'total_debit' => $totalPaid,       // Total payments made
            'total_credit' => $totalPayable,   // Total amount owed
            'net_amount' => $totalPayable - $totalPaid,
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

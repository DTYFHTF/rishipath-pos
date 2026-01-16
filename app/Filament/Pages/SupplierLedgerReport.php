<?php

namespace App\Filament\Pages;

use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use Filament\Pages\Page;

class SupplierLedgerReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static string $view = 'filament.pages.supplier-ledger-report';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $navigationLabel = 'Supplier Ledger';
    protected static ?int $navigationSort = 11;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('view_reports') ?? false;
    }

    public $supplierId;
    public $startDate;
    public $endDate;

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    public function getSupplierSummary(): array
    {
        $query = Supplier::where('active', true);

        $suppliers = $query->get()->map(function ($supplier) {
            $purchases = $supplier->purchases()
                ->where('status', 'received')
                ->when($this->startDate, fn($q) => $q->whereDate('purchase_date', '>=', $this->startDate))
                ->when($this->endDate, fn($q) => $q->whereDate('purchase_date', '<=', $this->endDate));

            return [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'code' => $supplier->supplier_code,
                'purchase_count' => $purchases->count(),
                'total_purchases' => $purchases->sum('total'),
                'total_paid' => $purchases->sum('amount_paid'),
                'current_balance' => $supplier->current_balance,
            ];
        });

        return $suppliers->sortByDesc('current_balance')->values()->toArray();
    }

    public function getOverallMetrics(): array
    {
        $suppliers = Supplier::where('active', true)->get();
        
        $totalPayable = $suppliers->sum('current_balance');
        $suppliersWithBalance = $suppliers->where('current_balance', '>', 0)->count();

        $periodQuery = SupplierLedgerEntry::query()
            ->when($this->startDate, fn($q) => $q->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate, fn($q) => $q->whereDate('created_at', '<=', $this->endDate));

        $purchases = (clone $periodQuery)->where('type', 'purchase')->sum('amount');
        $payments = abs((clone $periodQuery)->where('type', 'payment')->sum('amount'));

        return [
            'total_payable' => $totalPayable,
            'suppliers_with_balance' => $suppliersWithBalance,
            'period_purchases' => $purchases,
            'period_payments' => $payments,
            'total_suppliers' => $suppliers->count(),
        ];
    }

    public function getLedgerEntries(): \Illuminate\Database\Eloquent\Collection
    {
        $query = SupplierLedgerEntry::with(['supplier', 'purchase', 'createdBy'])
            ->when($this->supplierId, fn($q) => $q->where('supplier_id', $this->supplierId))
            ->when($this->startDate, fn($q) => $q->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate, fn($q) => $q->whereDate('created_at', '<=', $this->endDate))
            ->orderByDesc('created_at');

        return $query->limit(100)->get();
    }

    public function getSelectedSupplier(): ?Supplier
    {
        if (!$this->supplierId) {
            return null;
        }

        return Supplier::find($this->supplierId);
    }
}

<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Supplier;
use App\Services\OrganizationContext;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class AccountsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    #[On('organization-switched')]
    #[On('store-switched')]
    public function refresh(): void
    {
        // Force widget refresh
    }

    protected function getStats(): array
    {
        $organizationId = OrganizationContext::getCurrentOrganizationId();

        // Calculate total receivables (what customers owe us)
        $totalReceivables = CustomerLedgerEntry::where('organization_id', $organizationId)
            ->where('ledgerable_type', Customer::class)
            ->where('status', 'pending')
            ->sum('debit_amount');

        // Calculate total payables (what we owe suppliers)
        $totalPayables = CustomerLedgerEntry::where('organization_id', $organizationId)
            ->where('ledgerable_type', Supplier::class)
            ->where('status', 'pending')
            ->sum('credit_amount');

        // Overdue receivables
        $overdueReceivables = CustomerLedgerEntry::where('organization_id', $organizationId)
            ->where('ledgerable_type', Customer::class)
            ->where('status', 'pending')
            ->where('due_date', '<', now())
            ->sum('debit_amount');

        // Overdue payables
        $overduePayables = CustomerLedgerEntry::where('organization_id', $organizationId)
            ->where('ledgerable_type', Supplier::class)
            ->where('status', 'pending')
            ->where('due_date', '<', now())
            ->sum('credit_amount');

        // Net position (receivables - payables)
        $netPosition = $totalReceivables - $totalPayables;

        return [
            Stat::make('Total Receivables', '₹' . number_format($totalReceivables, 2))
                ->description($overdueReceivables > 0 ? number_format($overdueReceivables, 2) . ' overdue' : 'All current')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($overdueReceivables > 0 ? 'warning' : 'success')
                ->chart([7, 4, 5, 2, 3, 4, 5]),

            Stat::make('Total Payables', '₹' . number_format($totalPayables, 2))
                ->description($overduePayables > 0 ? number_format($overduePayables, 2) . ' overdue' : 'All current')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color($overduePayables > 0 ? 'danger' : 'info')
                ->chart([3, 4, 5, 2, 1, 3, 4]),

            Stat::make('Net Position', '₹' . number_format($netPosition, 2))
                ->description($netPosition > 0 ? 'Positive cash position' : 'Negative cash position')
                ->descriptionIcon($netPosition > 0 ? 'heroicon-m-arrow-up' : 'heroicon-m-arrow-down')
                ->color($netPosition > 0 ? 'success' : 'warning')
                ->chart([2, 3, 4, 3, 2, 3, 4]),
        ];
    }
}

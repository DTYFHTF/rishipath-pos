<?php

namespace App\Filament\Pages;

use App\Models\Sale;
use App\Models\SalesAgent;
use App\Services\OrganizationContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class AgentEarningsSettlement extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.agent-earnings-settlement';

    protected static ?string $navigationGroup = 'Field Sales';

    protected static ?string $navigationLabel = 'Agent Earnings';

    protected static ?string $title = 'Agent Earnings & Settlement';

    protected static ?int $navigationSort = 2;

    public ?int $agentId = null;

    public string $fromDate;

    public string $toDate;

    public function mount(): void
    {
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyPermission([
            'access_pos_billing',
            'view_inventory_reports',
            'view_reports',
        ]);
    }

    public function getAgentsProperty(): Collection
    {
        $orgId = OrganizationContext::getCurrentOrganizationId()
            ?? auth()->user()?->organization_id;

        return SalesAgent::query()
            ->where('organization_id', $orgId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'agent_code']);
    }

    public function getSalesProperty(): Collection
    {
        $orgId = OrganizationContext::getCurrentOrganizationId()
            ?? auth()->user()?->organization_id;

        return Sale::query()
            ->with(['salesAgent'])
            ->where('organization_id', $orgId)
            ->whereNotNull('sales_agent_id')
            ->whereDate('date', '>=', $this->fromDate)
            ->whereDate('date', '<=', $this->toDate)
            ->when($this->agentId, fn ($q) => $q->where('sales_agent_id', $this->agentId))
            ->orderByDesc('id')
            ->limit(250)
            ->get();
    }

    public function getSummaryProperty(): array
    {
        $sales = $this->sales;

        $totalSales = (float) $sales->sum('total_amount');
        $paidCollections = (float) $sales->where('payment_status', 'paid')->sum('amount_paid');
        $commissions = (float) $sales->sum('agent_commission_amount');
        $deliveryCharges = (float) $sales->sum('delivery_charge');
        $unsettledCount = (int) $sales
            ->where('payment_status', 'paid')
            ->whereNull('settlement_confirmed_at')
            ->count();

        return [
            'total_sales' => $totalSales,
            'paid_collections' => $paidCollections,
            'commissions' => $commissions,
            'delivery_charges' => $deliveryCharges,
            'unsettled_count' => $unsettledCount,
        ];
    }

    public function confirmSettlement(int $saleId): void
    {
        $orgId = OrganizationContext::getCurrentOrganizationId()
            ?? auth()->user()?->organization_id;

        $sale = Sale::query()
            ->where('organization_id', $orgId)
            ->where('id', $saleId)
            ->first();

        if (! $sale) {
            Notification::make()->danger()->title('Sale not found')->send();

            return;
        }

        if ($sale->payment_status !== 'paid') {
            Notification::make()->warning()->title('Only paid sales can be settled')->send();

            return;
        }

        $sale->update([
            'settlement_confirmed_at' => now(),
        ]);

        Notification::make()->success()->title('Settlement confirmed')->send();
    }
}

<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\LoyaltyPoint;
use App\Services\OrganizationContext;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class LoyaltyStatsWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected static ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission('view_dashboard') ?? false;
    }

    #[On('organization-switched')]
    #[On('store-switched')]
    public function refresh(): void
    {
        // Force widget refresh
    }

    protected function getStats(): array
    {
        $orgId = OrganizationContext::getCurrentOrganizationId();

        $totalMembers = Customer::where('organization_id', $orgId)
            ->whereNotNull('loyalty_enrolled_at')
            ->count();

        $activeMembers = Customer::where('organization_id', $orgId)
            ->whereNotNull('loyalty_enrolled_at')
            ->whereHas('sales', function ($q) {
                $q->where('created_at', '>=', now()->subDays(90));
            })
            ->count();

        $pointsThisMonth = LoyaltyPoint::where('organization_id', $orgId)
            ->where('type', 'earned')
            ->whereMonth('created_at', now()->month)
            ->sum('points');

        $redemptionsThisMonth = abs(LoyaltyPoint::where('organization_id', $orgId)
            ->where('type', 'redeemed')
            ->whereMonth('created_at', now()->month)
            ->sum('points'));

        return [
            Stat::make('Loyalty Members', number_format($totalMembers))
                ->description("{$activeMembers} active (90 days)")
                ->descriptionIcon('heroicon-o-user-group')
                ->color('success'),

            Stat::make('Points Earned (Month)', number_format($pointsThisMonth))
                ->description('Points issued this month')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('primary'),

            Stat::make('Points Redeemed (Month)', number_format($redemptionsThisMonth))
                ->description('Points used this month')
                ->descriptionIcon('heroicon-o-gift')
                ->color('warning'),
        ];
    }
}

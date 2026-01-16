<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTier;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class LoyaltyProgram extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static string $view = 'filament.pages.loyalty-program';

    protected static ?string $navigationGroup = 'Loyalty';

    protected static ?string $navigationLabel = 'Loyalty Overview';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('view_loyalty_program') ?? false;
    }

    public function getStats(): array
    {
        $orgId = auth()->user()->organization_id;

        $totalMembers = Customer::where('organization_id', $orgId)
            ->whereNotNull('loyalty_enrolled_at')
            ->count();

        $activeMembers = Customer::where('organization_id', $orgId)
            ->whereNotNull('loyalty_enrolled_at')
            ->whereHas('sales', function ($q) {
                $q->where('created_at', '>=', now()->subDays(90));
            })
            ->count();

        $pointsIssued = LoyaltyPoint::where('organization_id', $orgId)
            ->where('type', 'earned')
            ->sum('points');

        $pointsRedeemed = abs(LoyaltyPoint::where('organization_id', $orgId)
            ->where('type', 'redeemed')
            ->sum('points'));

        $tierDistribution = Customer::where('organization_id', $orgId)
            ->whereNotNull('loyalty_tier_id')
            ->select('loyalty_tier_id', DB::raw('count(*) as count'))
            ->groupBy('loyalty_tier_id')
            ->with('loyaltyTier')
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->loyaltyTier?->name ?? 'Unknown' => $item->count,
            ]);

        return [
            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
            'points_issued' => $pointsIssued,
            'points_redeemed' => $pointsRedeemed,
            'points_outstanding' => $pointsIssued - $pointsRedeemed,
            'tier_distribution' => $tierDistribution->toArray(),
        ];
    }

    public function getTopMembers(): array
    {
        return Customer::where('organization_id', auth()->user()->organization_id)
            ->whereNotNull('loyalty_enrolled_at')
            ->with('loyaltyTier')
            ->orderBy('loyalty_points', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function getRecentActivity(): array
    {
        return LoyaltyPoint::where('organization_id', auth()->user()->organization_id)
            ->with(['customer', 'sale'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();
    }

    public function getTiers(): array
    {
        return LoyaltyTier::where('organization_id', auth()->user()->organization_id)
            ->where('active', true)
            ->orderBy('order')
            ->get()
            ->toArray();
    }
}

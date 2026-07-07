<?php

namespace App\Filament\Pages;

use App\Models\RetailStore;
use App\Services\OrganizationContext;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Auto Retail Store Visit Planner
 *
 * Recommends which stores the field team should visit today, ordered by:
 *  1. How overdue the store is (days since last visit vs. recommended interval)
 *  2. Upcoming next_visit_date set during previous visit
 *  3. Average order value (prioritise high-revenue stores)
 *  4. Geographic clustering (group nearby stores to minimise travel)
 */
class RetailVisitPlanner extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static string $view = 'filament.pages.retail-visit-planner';

    protected static ?string $navigationGroup = 'Field Sales';

    protected static ?string $navigationLabel = 'Visit Planner';

    protected static ?string $title = 'Retail Store Visit Planner';

    protected static ?int $navigationSort = 1;

    // ── Public state (Livewire) ────────────────────────────────────────────

    /** Number of stores to include in today's plan */
    public int $storesPerDay = 8;

    /** Visit interval in days — stores not visited within this period are overdue */
    public int $visitIntervalDays = 30;

    /** Selected store IDs the user has confirmed for today */
    public array $selectedStoreIds = [];

    public ?string $routeUrl = null;

    // ─────────────────────────────────────────────────────────────────────

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('view_retail_stores') ?? false;
    }

    public function mount(): void
    {
        $recommended = $this->getRecommendedStores();
        $this->selectedStoreIds = $recommended->take($this->storesPerDay)->pluck('id')->map(fn ($id) => (string) $id)->toArray();
    }

    // ── Core algorithm ────────────────────────────────────────────────────

    /**
     * Score and rank all stores, then return them most-urgent first.
     */
    public function getRecommendedStores(): Collection
    {
        $orgId = OrganizationContext::getCurrentOrganizationId()
            ?? auth()->user()?->organization_id;

        $stores = RetailStore::with(['latestVisit'])
            ->where('organization_id', $orgId)
            ->where('status', 'active')
            ->get();

        $scored = $stores->map(function (RetailStore $store) {
            $score = 0;

            // 1. Days since last visit (max bonus: 60 pts at 60+ days)
            $lastVisit = $store->last_visited_at ?? $store->created_at;
            $daysSince = (int) now()->diffInDays($lastVisit, false) * -1; // positive = days ago
            $daysSince = max(0, $daysSince);
            $score += min(60, $daysSince * (60 / max(1, $this->visitIntervalDays)));

            // 2. next_visit_date urgency (bonus if overdue, penalty if far future)
            $nextVisitDate = $store->latestVisit?->next_visit_date;
            if ($nextVisitDate) {
                $daysUntilNext = now()->diffInDays($nextVisitDate, false);
                if ($daysUntilNext <= 0) {
                    // Overdue — the more overdue the more urgent
                    $score += min(30, abs($daysUntilNext) * 3);
                } else {
                    // Scheduled in the future — small positive if within 3 days
                    $score += max(0, (3 - $daysUntilNext) * 5);
                }
            } else {
                // Never had a scheduled next visit — give small urgency boost
                $score += 10;
            }

            // 3. Average order value from last 90 days (max 20 pts)
            $avgOrderValue = $store->visits()
                ->where('visit_date', '>=', now()->subDays(90))
                ->whereNotNull('order_value')
                ->avg('order_value') ?? 0;
            $score += min(20, ($avgOrderValue / 500) * 20); // normalised to ₹500 = 20 pts

            return array_merge($store->toArray(), [
                '_score' => round($score, 1),
                '_days_since' => $daysSince,
                '_next_visit' => $nextVisitDate?->format('Y-m-d'),
                '_avg_order' => round($avgOrderValue, 0),
                '_model' => $store,
            ]);
        });

        return $scored->sortByDesc('_score')->values();
    }

    /**
     * Re-rank stores when the user changes the interval or storesPerDay.
     */
    public function recalculate(): void
    {
        $recommended = $this->getRecommendedStores();
        $this->selectedStoreIds = $recommended->take($this->storesPerDay)->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        $this->routeUrl = null;
    }

    /**
     * Build a Google Maps multi-stop route URL from selected stores.
     * Optimises order using nearest-neighbour heuristic on lat/lng.
     */
    public function buildRoute(): void
    {
        $stores = RetailStore::whereIn('id', $this->selectedStoreIds)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        if ($stores->isEmpty()) {
            $this->routeUrl = null;

            return;
        }

        $ordered = $this->nearestNeighbourSort($stores);

        // Google Maps directions URL supports up to 10 waypoints
        $waypoints = $ordered->map(fn ($s) => "{$s->latitude},{$s->longitude}")->toArray();

        $origin = array_shift($waypoints);
        $destination = array_pop($waypoints) ?? $origin;
        $waypointsStr = implode('|', $waypoints);

        $this->routeUrl = 'https://www.google.com/maps/dir/?api=1'
            .'&origin='.urlencode($origin)
            .'&destination='.urlencode($destination)
            .($waypointsStr ? '&waypoints='.urlencode($waypointsStr) : '')
            .'&travelmode=driving';
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Nearest-neighbour TSP heuristic — starts from the first store and
     * repeatedly visits the closest unvisited store.
     */
    private function nearestNeighbourSort(Collection $stores): Collection
    {
        if ($stores->count() <= 2) {
            return $stores;
        }

        $remaining = $stores->values()->toArray();
        $ordered = [array_shift($remaining)];

        while (! empty($remaining)) {
            $last = end($ordered);
            $closest = null;
            $minDist = PHP_FLOAT_MAX;

            foreach ($remaining as $key => $store) {
                $dist = $this->haversine(
                    (float) $last['latitude'], (float) $last['longitude'],
                    (float) $store['latitude'], (float) $store['longitude']
                );
                if ($dist < $minDist) {
                    $minDist = $dist;
                    $closest = $key;
                }
            }

            $ordered[] = $remaining[$closest];
            array_splice($remaining, $closest, 1);
        }

        return collect($ordered);
    }

    /**
     * Haversine distance in kilometres between two lat/lng points.
     */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371;
        $dL = deg2rad($lat2 - $lat1);
        $dN = deg2rad($lon2 - $lon1);
        $a = sin($dL / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dN / 2) ** 2;

        return $R * 2 * asin(sqrt($a));
    }

    // ── Relationship proxy ────────────────────────────────────────────────

    /**
     * Proxy to RetailStore visits() – called from getRecommendedStores via model.
     * The actual relationship is declared on RetailStore; we access it here only
     * for avg order value queries.
     */
    public function getAllStores(): Collection
    {
        $orgId = OrganizationContext::getCurrentOrganizationId()
            ?? auth()->user()?->organization_id;

        return RetailStore::where('organization_id', $orgId)
            ->where('status', 'active')
            ->orderBy('store_name')
            ->get();
    }
}

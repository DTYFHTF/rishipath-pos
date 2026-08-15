<?php

namespace App\Filament\Pages;

use App\Models\RetailStore;
use App\Services\GoogleMapsLink;
use App\Services\OrganizationContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Auto Retail Store Visit Planner
 *
 * Two ways to build a day:
 *  - "Urgency" ranks every store by how overdue it is, its scheduled next
 *    visit, and its average order value.
 *  - "Area" clusters stores that sit within walking/riding distance of each
 *    other so the team can clear a whole neighbourhood in one trip.
 *
 * Both need coordinates. Most stores are captured with only a Google Maps
 * link, so unmapped stores are surfaced explicitly and can be resolved from
 * their links in one click (see resolveCoordinates()).
 */
class RetailVisitPlanner extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static string $view = 'filament.pages.retail-visit-planner';

    protected static ?string $navigationGroup = 'Field Sales';

    protected static ?string $navigationLabel = 'Visit Planner';

    protected static ?string $title = 'Retail Store Visit Planner';

    protected static ?int $navigationSort = 1;

    /** Stores within this many km of each other belong to the same area cluster. */
    private const CLUSTER_RADIUS_KM = 1.2;

    /**
     * Hard cap on how far apart two shops in one cluster may be.
     *
     * Without it, single-link clustering chains: a line of shops each 1 km
     * apart would swallow the whole valley into one "area" and the cluster
     * would stop meaning "a trip you can finish on foot".
     */
    private const MAX_CLUSTER_SPREAD_KM = 3.0;

    /** Google's Maps URL API caps a single directions link at 10 stops. */
    private const MAX_STOPS_PER_LEG = 10;

    // ── Public state (Livewire) ────────────────────────────────────────────

    /** 'urgency' ranks by score; 'area' groups by geographic cluster. */
    public string $mode = 'urgency';

    /** Which shops to plan for: 'all' (bar inactive), 'customers', 'prospects'. */
    public string $statusScope = 'all';

    /** Number of stores to include in today's plan */
    public int $storesPerDay = 15;

    /** Visit interval in days — stores not visited within this period are overdue */
    public int $visitIntervalDays = 30;

    /** Selected store IDs the user has confirmed for today */
    public array $selectedStoreIds = [];

    /** Cluster key currently expanded in area mode */
    public ?string $activeCluster = null;

    /** Optional starting point (the agent's live position) for the route */
    public ?float $originLat = null;

    public ?float $originLng = null;

    /** Built route legs: each is ['url' => …, 'stops' => [names], 'distance' => km] */
    public array $routeLegs = [];

    public ?string $routeSummary = null;

    /** Per-render cache — the view asks for the ranking several times. */
    protected ?Collection $rankedCache = null;

    // ─────────────────────────────────────────────────────────────────────

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('view_retail_stores') ?? false;
    }

    public function mount(): void
    {
        $this->autoSelect();
    }

    // ── Store loading ─────────────────────────────────────────────────────

    /**
     * @return Collection<int, RetailStore>
     */
    public function storesQuery(): Collection
    {
        $orgId = OrganizationContext::getCurrentOrganizationId()
            ?? auth()->user()?->organization_id;

        return RetailStore::with(['latestVisit'])
            ->where('organization_id', $orgId)
            // Prospects are the whole point of a field round — they are shops
            // we are trying to convert, and 'prospect' is the status every new
            // store gets by default. Planning only 'active' left the planner
            // showing 2 of 478 stores. Only 'inactive' means "do not visit".
            ->when($this->statusScope === 'customers', fn ($q) => $q->where('status', 'active'))
            ->when($this->statusScope === 'prospects', fn ($q) => $q->where('status', 'prospect'))
            ->when($this->statusScope === 'all', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->get();
    }

    /**
     * Score and rank all stores, then return them most-urgent first.
     */
    public function getRecommendedStores(): Collection
    {
        if ($this->rankedCache !== null) {
            return $this->rankedCache;
        }

        // Average order value for every store in one query rather than one
        // query per store inside the scoring loop.
        $avgOrders = \App\Models\RetailStoreVisit::query()
            ->where('visit_date', '>=', now()->subDays(90))
            ->whereNotNull('order_value')
            ->selectRaw('retail_store_id, AVG(order_value) as avg_value')
            ->groupBy('retail_store_id')
            ->pluck('avg_value', 'retail_store_id');

        $scored = $this->storesQuery()->map(function (RetailStore $store) use ($avgOrders) {
            $score = 0;

            // 1. Days since last visit (max bonus: 60 pts at a full interval)
            $lastVisit = $store->last_visited_at ?? $store->created_at;
            $daysSince = max(0, (int) now()->diffInDays($lastVisit, false) * -1);
            $score += min(60, $daysSince * (60 / max(1, $this->visitIntervalDays)));

            // 2. next_visit_date urgency (bonus if overdue, small boost if imminent)
            $nextVisitDate = $store->latestVisit?->next_visit_date;
            if ($nextVisitDate) {
                $daysUntilNext = now()->diffInDays($nextVisitDate, false);
                $score += $daysUntilNext <= 0
                    ? min(30, abs($daysUntilNext) * 3)
                    : max(0, (3 - $daysUntilNext) * 5);
            } else {
                $score += 10;
            }

            // 3. Average order value from last 90 days (max 20 pts, ₹500 = full)
            $avgOrderValue = (float) ($avgOrders[$store->id] ?? 0);
            $score += min(20, ($avgOrderValue / 500) * 20);

            return array_merge($store->toArray(), [
                '_score' => round($score, 1),
                '_days_since' => $daysSince,
                '_next_visit' => $nextVisitDate?->format('Y-m-d'),
                '_avg_order' => round($avgOrderValue, 0),
                '_mapped' => $this->hasCoordinates($store),
            ]);
        });

        return $this->rankedCache = $scored->sortByDesc('_score')->values();
    }

    /** Invalidated whenever settings change the ranking. */
    private function forgetRanking(): void
    {
        $this->rankedCache = null;
    }

    public function updatedVisitIntervalDays(): void
    {
        $this->forgetRanking();
    }

    public function setStatusScope(string $scope): void
    {
        $this->statusScope = in_array($scope, ['all', 'customers', 'prospects'], true) ? $scope : 'all';
        $this->activeCluster = null;
        $this->forgetRanking();
        $this->resetRoute();
        $this->autoSelect();
    }

    // ── Area clustering ───────────────────────────────────────────────────

    /**
     * Group stores into geographic clusters.
     *
     * Single-link clustering: two stores join the same cluster when they are
     * within CLUSTER_RADIUS_KM of each other, which lets a cluster follow the
     * real shape of a market street instead of a fixed circle. Stores with no
     * coordinates fall back to grouping by their typed `area` so they are
     * still plannable — they just cannot be routed.
     *
     * @return Collection<int, array>
     */
    public function getClusters(): Collection
    {
        $ranked = $this->getRecommendedStores();
        [$mapped, $unmapped] = $ranked->partition(fn ($s) => $s['_mapped']);

        $clusters = collect($this->clusterByProximity($mapped->values()->all()))
            ->map(fn (array $members, int $i) => $this->describeCluster($members, 'geo-'.$i, true));

        $fallback = $unmapped
            ->groupBy(fn ($s) => trim((string) ($s['area'] ?: $s['city'] ?: 'Unknown area')))
            ->map(fn ($members, $name) => $this->describeCluster($members->all(), 'area-'.$name, false, $name));

        return $clusters
            ->concat($fallback->values())
            ->sortByDesc(fn ($c) => [$c['mapped'] ? 1 : 0, $c['overdue'], $c['count']])
            ->values();
    }

    /**
     * @param  array<int, array>  $stores
     * @return array<int, array<int, array>>
     */
    private function clusterByProximity(array $stores): array
    {
        $unassigned = $stores;
        $clusters = [];

        while ($unassigned !== []) {
            $seed = array_shift($unassigned);
            $cluster = [$seed];

            // Breadth-first expansion: keep pulling in anything close to
            // anything already in the cluster.
            $frontier = [$seed];

            while ($frontier !== []) {
                $current = array_pop($frontier);

                foreach ($unassigned as $key => $candidate) {
                    $distance = $this->haversine(
                        (float) $current['latitude'], (float) $current['longitude'],
                        (float) $candidate['latitude'], (float) $candidate['longitude'],
                    );

                    if ($distance > self::CLUSTER_RADIUS_KM) {
                        continue;
                    }

                    if (! $this->fitsWithinSpread($candidate, $cluster)) {
                        continue;
                    }

                    $cluster[] = $candidate;
                    $frontier[] = $candidate;
                    unset($unassigned[$key]);
                }

                $unassigned = array_values($unassigned);
            }

            $clusters[] = $cluster;
        }

        return $clusters;
    }

    /**
     * A candidate may only join if it stays close to every existing member,
     * which bounds the cluster's diameter instead of letting it chain.
     *
     * @param  array<int, array>  $cluster
     */
    private function fitsWithinSpread(array $candidate, array $cluster): bool
    {
        foreach ($cluster as $member) {
            $distance = $this->haversine(
                (float) $member['latitude'], (float) $member['longitude'],
                (float) $candidate['latitude'], (float) $candidate['longitude'],
            );

            if ($distance > self::MAX_CLUSTER_SPREAD_KM) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array>  $members
     */
    private function describeCluster(array $members, string $key, bool $mapped, ?string $forcedName = null): array
    {
        $collection = collect($members);

        // Name a geographic cluster after the area/city most of its stores share.
        $name = $forcedName ?? $collection
            ->map(fn ($s) => trim((string) ($s['area'] ?: $s['city'] ?: '')))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        $lat = $mapped ? $collection->avg(fn ($s) => (float) $s['latitude']) : null;
        $lng = $mapped ? $collection->avg(fn ($s) => (float) $s['longitude']) : null;

        // Widest gap across the cluster, so the user can see how walkable it is.
        $spread = 0.0;
        if ($mapped && count($members) > 1) {
            foreach ($members as $a) {
                foreach ($members as $b) {
                    $spread = max($spread, $this->haversine(
                        (float) $a['latitude'], (float) $a['longitude'],
                        (float) $b['latitude'], (float) $b['longitude'],
                    ));
                }
            }
        }

        return [
            'key' => $key,
            'name' => $name ?: 'Unnamed area',
            'mapped' => $mapped,
            'count' => count($members),
            'overdue' => $collection->filter(fn ($s) => $s['_days_since'] > $this->visitIntervalDays)->count(),
            'score' => round($collection->sum('_score'), 1),
            'value' => round($collection->sum('_avg_order'), 0),
            'spread_km' => round($spread, 1),
            'lat' => $lat,
            'lng' => $lng,
            'store_ids' => $collection->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'stores' => $collection->sortByDesc('_score')->values()->all(),
        ];
    }

    // ── Actions ───────────────────────────────────────────────────────────

    public function setMode(string $mode): void
    {
        $this->mode = $mode === 'area' ? 'area' : 'urgency';
        $this->activeCluster = null;
        $this->resetRoute();

        if ($this->mode === 'urgency') {
            $this->autoSelect();
        }
    }

    public function recalculate(): void
    {
        $this->forgetRanking();
        $this->autoSelect();
        $this->resetRoute();
    }

    /** Select the top-N most urgent stores. */
    private function autoSelect(): void
    {
        $this->selectedStoreIds = $this->getRecommendedStores()
            ->take($this->storesPerDay)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /** Put a whole cluster into today's plan. */
    public function planCluster(string $key): void
    {
        $cluster = $this->getClusters()->firstWhere('key', $key);

        if (! $cluster) {
            return;
        }

        $this->activeCluster = $key;
        $this->selectedStoreIds = $cluster['store_ids'];
        $this->resetRoute();

        Notification::make()
            ->title($cluster['count'].' stores in '.$cluster['name'].' added to today\'s plan')
            ->body($cluster['mapped']
                ? 'Spread about '.$cluster['spread_km'].' km. Hit "Build Route" for turn-by-turn directions.'
                : 'These stores have no coordinates yet, so they cannot be routed.')
            ->success()
            ->send();
    }

    public function clearSelection(): void
    {
        $this->selectedStoreIds = [];
        $this->activeCluster = null;
        $this->resetRoute();
    }

    /** Receives the browser's geolocation so routes start where the agent is. */
    public function setOrigin(float $lat, float $lng): void
    {
        $this->originLat = $lat;
        $this->originLng = $lng;
        $this->resetRoute();

        Notification::make()
            ->title('Starting point set')
            ->body('Routes will now begin from your current location.')
            ->success()
            ->send();
    }

    public function clearOrigin(): void
    {
        $this->originLat = null;
        $this->originLng = null;
        $this->resetRoute();
    }

    /**
     * Fill in latitude/longitude for stores that only have a Google Maps link.
     */
    public function resolveCoordinates(): void
    {
        $orgId = OrganizationContext::getCurrentOrganizationId()
            ?? auth()->user()?->organization_id;

        $pending = RetailStore::query()
            ->where('organization_id', $orgId)
            ->whereNotNull('google_location_url')
            ->where('google_location_url', '!=', '')
            ->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))
            ->get();

        if ($pending->isEmpty()) {
            Notification::make()
                ->title('Nothing to resolve')
                ->body('Every store with a Google Maps link already has coordinates.')
                ->info()
                ->send();

            return;
        }

        $links = app(GoogleMapsLink::class);
        $resolved = 0;

        foreach ($pending as $store) {
            $coords = $links->coordinatesFor($store->google_location_url);

            if (! $coords) {
                continue;
            }

            $store->forceFill([
                'latitude' => $coords['lat'],
                'longitude' => $coords['lng'],
                'google_location_url' => $coords['expanded_url'] ?? $store->google_location_url,
            ])->saveQuietly();

            $resolved++;
        }

        $this->forgetRanking();
        $failed = $pending->count() - $resolved;

        Notification::make()
            ->title("Mapped {$resolved} of {$pending->count()} stores")
            ->body($failed > 0
                ? "{$failed} link(s) had no coordinates in them — open those stores and drop a pin on the map."
                : 'All linked stores now have coordinates.')
            ->{$resolved > 0 ? 'success' : 'warning'}()
            ->send();
    }

    /**
     * Build Google Maps directions link(s) for the selected stores.
     *
     * A single Maps URL takes at most 10 stops, so a bigger day is split into
     * consecutive legs. Stop order comes from a nearest-neighbour pass
     * refined with 2-opt, starting from the agent's position when known.
     */
    public function buildRoute(): void
    {
        $this->resetRoute();

        if ($this->selectedStoreIds === []) {
            Notification::make()
                ->title('No stores selected')
                ->body('Tick some stores, or pick an area cluster, first.')
                ->warning()
                ->send();

            return;
        }

        $selected = RetailStore::whereIn('id', $this->selectedStoreIds)->get();
        $routable = $selected->filter(fn (RetailStore $s) => $this->hasCoordinates($s))->values();
        $skipped = $selected->count() - $routable->count();

        if ($routable->isEmpty()) {
            Notification::make()
                ->title('None of these stores can be routed')
                ->body($selected->whereNotNull('google_location_url')->isNotEmpty()
                    ? 'They have Google Maps links but no coordinates. Tap "Map stores from links" to extract them.'
                    : 'Add a Google Maps link or drop a pin on each store first.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $ordered = $this->optimiseOrder($routable);
        $totalKm = $this->routeDistance($ordered);

        // Chunk into legs of MAX_STOPS_PER_LEG, repeating the last stop of a
        // leg as the first of the next so the trip stays continuous.
        $chunks = $ordered->chunk(self::MAX_STOPS_PER_LEG)->values();

        $this->routeLegs = $chunks->map(function (Collection $chunk, int $index) use ($chunks) {
            $stops = $chunk->values();

            if ($index > 0) {
                $stops->prepend($chunks[$index - 1]->last());
            }

            return [
                'label' => $chunks->count() > 1 ? 'Leg '.($index + 1).' of '.$chunks->count() : 'Full route',
                'url' => $this->directionsUrl($stops, includeOrigin: $index === 0),
                'stops' => $stops->pluck('store_name')->all(),
                'distance' => round($this->routeDistance($stops), 1),
            ];
        })->all();

        $this->routeSummary = trim(sprintf(
            '%d stop%s · ~%s km driving%s%s',
            $routable->count(),
            $routable->count() === 1 ? '' : 's',
            number_format($totalKm, 1),
            $this->originLat !== null ? ' from your location' : '',
            $skipped > 0 ? " · {$skipped} store(s) skipped (no coordinates)" : '',
        ));

        Notification::make()
            ->title('Route ready')
            ->body($this->routeSummary)
            ->success()
            ->send();
    }

    // ── Route helpers ─────────────────────────────────────────────────────

    private function resetRoute(): void
    {
        $this->routeLegs = [];
        $this->routeSummary = null;
    }

    private function hasCoordinates(RetailStore|array $store): bool
    {
        $lat = is_array($store) ? ($store['latitude'] ?? null) : $store->latitude;
        $lng = is_array($store) ? ($store['longitude'] ?? null) : $store->longitude;

        return $lat !== null && $lng !== null && (float) $lat !== 0.0 && (float) $lng !== 0.0;
    }

    private function directionsUrl(Collection $stops, bool $includeOrigin): string
    {
        $points = $stops->map(fn (RetailStore $s) => $s->latitude.','.$s->longitude)->values();

        if ($includeOrigin && $this->originLat !== null && $this->originLng !== null) {
            $points->prepend($this->originLat.','.$this->originLng);
        }

        $points = $points->all();
        $origin = array_shift($points);
        $destination = array_pop($points) ?? $origin;

        return 'https://www.google.com/maps/dir/?api=1'
            .'&origin='.urlencode($origin)
            .'&destination='.urlencode($destination)
            .($points !== [] ? '&waypoints='.urlencode(implode('|', $points)) : '')
            .'&travelmode=driving';
    }

    /**
     * Nearest-neighbour ordering refined by 2-opt.
     *
     * Nearest-neighbour alone leaves long crossing hops; 2-opt repeatedly
     * un-crosses pairs of legs and typically cuts 10–20% off the distance.
     */
    private function optimiseOrder(Collection $stores): Collection
    {
        if ($stores->count() <= 1) {
            return $stores;
        }

        $remaining = $stores->all();
        $ordered = [];

        // Start from the agent's position when we have it, otherwise from the
        // store closest to the group's centre.
        $currentLat = $this->originLat ?? $stores->avg(fn ($s) => (float) $s->latitude);
        $currentLng = $this->originLng ?? $stores->avg(fn ($s) => (float) $s->longitude);

        while ($remaining !== []) {
            $closest = null;
            $minDistance = PHP_FLOAT_MAX;

            foreach ($remaining as $key => $store) {
                $distance = $this->haversine($currentLat, $currentLng, (float) $store->latitude, (float) $store->longitude);

                if ($distance < $minDistance) {
                    $minDistance = $distance;
                    $closest = $key;
                }
            }

            $next = $remaining[$closest];
            $ordered[] = $next;
            $currentLat = (float) $next->latitude;
            $currentLng = (float) $next->longitude;
            array_splice($remaining, $closest, 1);
        }

        return collect($this->twoOpt($ordered));
    }

    /**
     * @param  array<int, RetailStore>  $route
     * @return array<int, RetailStore>
     */
    private function twoOpt(array $route): array
    {
        $count = count($route);
        $improved = true;
        $guard = 0;

        while ($improved && $guard++ < 50) {
            $improved = false;

            for ($i = 0; $i < $count - 2; $i++) {
                for ($j = $i + 2; $j < $count - 1; $j++) {
                    $before = $this->legDistance($route[$i], $route[$i + 1])
                        + $this->legDistance($route[$j], $route[$j + 1]);

                    $after = $this->legDistance($route[$i], $route[$j])
                        + $this->legDistance($route[$i + 1], $route[$j + 1]);

                    if ($after < $before - 0.0001) {
                        $segment = array_reverse(array_slice($route, $i + 1, $j - $i));
                        array_splice($route, $i + 1, $j - $i, $segment);
                        $improved = true;
                    }
                }
            }
        }

        return $route;
    }

    private function legDistance(RetailStore $a, RetailStore $b): float
    {
        return $this->haversine(
            (float) $a->latitude, (float) $a->longitude,
            (float) $b->latitude, (float) $b->longitude,
        );
    }

    private function routeDistance(Collection $stops): float
    {
        $total = 0.0;
        $previousLat = $this->originLat;
        $previousLng = $this->originLng;

        foreach ($stops as $stop) {
            if ($previousLat !== null && $previousLng !== null) {
                $total += $this->haversine($previousLat, $previousLng, (float) $stop->latitude, (float) $stop->longitude);
            }

            $previousLat = (float) $stop->latitude;
            $previousLng = (float) $stop->longitude;
        }

        // Straight-line distance underestimates road distance; ~1.35x is a
        // reasonable city correction factor.
        return $total * 1.35;
    }

    /**
     * Haversine distance in kilometres between two lat/lng points.
     */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $R * 2 * asin(sqrt($a));
    }

    // ── View helpers ──────────────────────────────────────────────────────

    /**
     * Coverage counters shown in the header strip.
     *
     * @return array{total: int, mapped: int, linked_unmapped: int, unmapped: int}
     */
    public function getCoverage(): array
    {
        $stores = $this->storesQuery();
        $mapped = $stores->filter(fn ($s) => $this->hasCoordinates($s));
        $unmapped = $stores->reject(fn ($s) => $this->hasCoordinates($s));

        return [
            'total' => $stores->count(),
            'mapped' => $mapped->count(),
            'linked_unmapped' => $unmapped->filter(fn ($s) => filled($s->google_location_url))->count(),
            'unmapped' => $unmapped->count(),
        ];
    }

    /**
     * Selected stores, in the order they would be visited, for the map preview.
     */
    public function getSelectedMarkers(): array
    {
        if ($this->selectedStoreIds === []) {
            return [];
        }

        $stores = RetailStore::whereIn('id', $this->selectedStoreIds)
            ->get()
            ->filter(fn (RetailStore $s) => $this->hasCoordinates($s))
            ->values();

        if ($stores->isEmpty()) {
            return [];
        }

        return $this->optimiseOrder($stores)
            ->values()
            ->map(fn (RetailStore $s, int $i) => [
                'n' => $i + 1,
                'lat' => (float) $s->latitude,
                'lng' => (float) $s->longitude,
                'name' => $s->store_name,
                'area' => trim((string) ($s->area ?: $s->city)),
            ])
            ->all();
    }

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

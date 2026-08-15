<?php

namespace Tests\Feature;

use App\Filament\Pages\RetailVisitPlanner;
use App\Models\Organization;
use App\Models\RetailStore;
use App\Models\Role;
use App\Models\User;
use App\Services\GoogleMapsLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VisitPlannerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();

        $role = Role::create([
            'organization_id' => $this->org->id,
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'permissions' => ['view_retail_stores'],
            'is_system_role' => true,
        ]);

        $this->user = User::create([
            'organization_id' => $this->org->id,
            'role_id' => $role->id,
            'name' => 'Planner Tester',
            'email' => 'planner@test.local',
            'password' => bcrypt('secret'),
            'active' => true,
        ]);

        $this->actingAs($this->user);
    }

    private function store(string $name, string $area, ?float $lat, ?float $lng, int $daysAgo = 40, ?string $url = null): RetailStore
    {
        return RetailStore::create([
            'organization_id' => $this->org->id,
            'store_name' => $name,
            'contact_number' => '98'.random_int(10000000, 99999999),
            'area' => $area,
            'city' => 'Kathmandu',
            'status' => 'active',
            'latitude' => $lat,
            'longitude' => $lng,
            'google_location_url' => $url,
            'last_visited_at' => now()->subDays($daysAgo),
        ]);
    }

    /** Three tight market clusters plus one isolated store. */
    private function seedClusters(): void
    {
        // Asan — four shops within a few hundred metres
        $this->store('Bajrangawoli', 'Asan', 27.7060, 85.3095);
        $this->store('Newa Masala', 'Asan', 27.7065, 85.3101);
        $this->store('Indrachowk Kirana', 'Indrachowk', 27.7048, 85.3084);
        $this->store('Mahabouddha Traders', 'Mahabouddha', 27.7031, 85.3122);

        // Thamel — three shops, ~1.5 km north of Asan
        $this->store('Thamel Organic', 'Thamel', 27.7154, 85.3123);
        $this->store('Chhetrapati Stores', 'Chhetrapati', 27.7139, 85.3079);
        $this->store('Paknajol Suppliers', 'Thamel', 27.7168, 85.3092);

        // Patan — across the river
        $this->store('Patan Dhoka Kirana', 'Patan', 27.6742, 85.3231);
        $this->store('Mangal Bazar Masala', 'Patan', 27.6730, 85.3250);

        // Bhaktapur — far east, must stand alone
        $this->store('Bhaktapur Durbar', 'Bhaktapur', 27.6710, 85.4298);
    }

    public function test_it_extracts_coordinates_from_every_google_maps_link_shape(): void
    {
        $links = app(GoogleMapsLink::class);

        // The !3d/!4d pin wins over the @lat,lng viewport centre.
        $this->assertSame(
            [27.7089123, 85.3151456],
            array_values(array_slice($links->coordinatesFor(
                'https://www.google.com/maps/place/Shop/@27.70,85.31,17z/data=!3m1!4b1!8m2!3d27.7089123!4d85.3151456'
            ), 0, 2))
        );

        $this->assertSame(27.7172, $links->coordinatesFor('https://www.google.com/maps?q=27.7172,85.3240')['lat']);
        $this->assertSame(85.3240, $links->coordinatesFor('https://maps.google.com/?q=27.6710,85.3240')['lng']);
        $this->assertSame(27.7172, $links->coordinatesFor('https://www.google.com/maps/@27.7172,85.3240,15z')['lat']);
        $this->assertSame(27.6588, $links->coordinatesFor('https://www.google.com/maps/dir/?api=1&destination=27.6588,85.3247')['lat']);

        // Garbage in, null out — 0,0 is never a real store.
        $this->assertNull($links->coordinatesFor('https://www.google.com/maps/place/Thamel/@0,0,17z'));
        $this->assertNull($links->coordinatesFor('https://example.com/no-coords'));
        $this->assertNull($links->coordinatesFor(null));
    }

    public function test_it_backfills_coordinates_from_links(): void
    {
        $this->store('Linked Shop', 'Asan', null, null, url: 'https://www.google.com/maps?q=27.7060,85.3095');
        $this->store('Unlinked Shop', 'Balaju', null, null);

        Livewire::test(RetailVisitPlanner::class)->call('resolveCoordinates');

        $linked = RetailStore::where('store_name', 'Linked Shop')->first();
        $this->assertEqualsWithDelta(27.7060, (float) $linked->latitude, 0.00001);
        $this->assertEqualsWithDelta(85.3095, (float) $linked->longitude, 0.00001);

        $this->assertNull(RetailStore::where('store_name', 'Unlinked Shop')->first()->latitude);
    }

    public function test_it_groups_nearby_shops_into_area_clusters(): void
    {
        $this->seedClusters();

        $clusters = Livewire::test(RetailVisitPlanner::class)->instance()->getClusters();

        $mapped = $clusters->where('mapped', true);

        // Asan and Thamel sit ~1 km apart and merge into one walkable cluster;
        // Patan (across the river) and Bhaktapur (10 km east) stand alone.
        $this->assertCount(3, $mapped, 'expected three geographic clusters');
        $this->assertSame([7, 2, 1], $mapped->pluck('count')->sort()->reverse()->values()->all());

        $core = $mapped->firstWhere('count', 7);
        $this->assertSame('Asan', $core['name'], 'cluster takes the name most of its shops share');

        // Shops far apart never share a cluster.
        $this->assertNotContains('Bhaktapur Durbar', collect($core['stores'])->pluck('store_name')->all());
        $this->assertSame('Bhaktapur', $mapped->firstWhere('count', 1)['name']);

        // Every store is accounted for exactly once.
        $this->assertSame(10, $clusters->sum('count'));
    }

    public function test_clusters_never_chain_beyond_a_walkable_spread(): void
    {
        // A chain of 12 shops each ~0.9 km apart. Single-link clustering
        // without a diameter cap would merge all 12 into one 10 km "area".
        for ($i = 0; $i < 12; $i++) {
            $this->store("Chain {$i}", 'Ring Road', 27.70 + ($i * 0.008), 85.31);
        }

        $clusters = Livewire::test(RetailVisitPlanner::class)->instance()->getClusters();

        $this->assertGreaterThan(1, $clusters->count(), 'the chain must be broken into several areas');
        $this->assertSame(12, $clusters->sum('count'));

        foreach ($clusters as $cluster) {
            $this->assertLessThanOrEqual(3.0, $cluster['spread_km'], "cluster {$cluster['name']} is too spread out");
        }
    }

    public function test_unmapped_stores_fall_back_to_area_clusters(): void
    {
        $this->store('No Pin One', 'Kalimati', null, null);
        $this->store('No Pin Two', 'Kalimati', null, null);
        $this->store('Mapped', 'Asan', 27.7060, 85.3095);

        $clusters = Livewire::test(RetailVisitPlanner::class)->instance()->getClusters();

        $fallback = $clusters->firstWhere('mapped', false);
        $this->assertSame('Kalimati', $fallback['name']);
        $this->assertSame(2, $fallback['count']);
    }

    public function test_planning_a_cluster_selects_exactly_its_stores(): void
    {
        $this->seedClusters();

        $component = Livewire::test(RetailVisitPlanner::class)->set('mode', 'area');
        $core = $component->instance()->getClusters()->firstWhere('count', 7);

        $component->call('planCluster', $core['key']);

        $this->assertEqualsCanonicalizing($core['store_ids'], $component->get('selectedStoreIds'));
    }

    public function test_build_route_produces_a_directions_url_ordered_by_proximity(): void
    {
        $this->seedClusters();

        $ids = RetailStore::whereIn('store_name', ['Bajrangawoli', 'Newa Masala', 'Bhaktapur Durbar'])
            ->pluck('id')->map(fn ($id) => (string) $id)->all();

        $legs = Livewire::test(RetailVisitPlanner::class)
            ->set('selectedStoreIds', $ids)
            ->call('buildRoute')
            ->get('routeLegs');

        $this->assertCount(1, $legs);
        $this->assertStringStartsWith('https://www.google.com/maps/dir/?api=1', $legs[0]['url']);
        $this->assertStringContainsString('travelmode=driving', $legs[0]['url']);
        $this->assertCount(3, $legs[0]['stops']);

        // The two Asan shops sit next to each other in the visit order; the
        // far-away Bhaktapur shop is not sandwiched between them.
        $positions = array_flip($legs[0]['stops']);
        $this->assertSame(1, abs($positions['Bajrangawoli'] - $positions['Newa Masala']));
    }

    public function test_build_route_splits_into_legs_beyond_the_google_url_limit(): void
    {
        for ($i = 0; $i < 14; $i++) {
            $this->store("Shop {$i}", 'Asan', 27.70 + ($i * 0.001), 85.31 + ($i * 0.001));
        }

        $legs = Livewire::test(RetailVisitPlanner::class)
            ->set('selectedStoreIds', RetailStore::pluck('id')->map(fn ($id) => (string) $id)->all())
            ->call('buildRoute')
            ->get('routeLegs');

        $this->assertCount(2, $legs, '14 stops must split across two Google Maps links');
        $this->assertSame('Leg 1 of 2', $legs[0]['label']);
        $this->assertLessThanOrEqual(10, count($legs[0]['stops']));

        // Legs join up: the last stop of leg 1 opens leg 2.
        $this->assertSame(end($legs[0]['stops']), $legs[1]['stops'][0]);
    }

    public function test_build_route_reports_instead_of_failing_silently_when_nothing_is_mapped(): void
    {
        $this->store('No Pin', 'Kalimati', null, null, url: 'https://maps.app.goo.gl/broken');

        $component = Livewire::test(RetailVisitPlanner::class)
            ->set('selectedStoreIds', RetailStore::pluck('id')->map(fn ($id) => (string) $id)->all())
            ->call('buildRoute');

        $this->assertSame([], $component->get('routeLegs'));
        $component->assertNotified();
    }

    public function test_setting_an_origin_starts_the_route_from_the_agent(): void
    {
        $this->seedClusters();

        $ids = RetailStore::whereIn('store_name', ['Bajrangawoli', 'Bhaktapur Durbar'])
            ->pluck('id')->map(fn ($id) => (string) $id)->all();

        $legs = Livewire::test(RetailVisitPlanner::class)
            ->set('selectedStoreIds', $ids)
            ->call('setOrigin', 27.6710, 85.4290)   // standing in Bhaktapur
            ->call('buildRoute')
            ->get('routeLegs');

        // Origin is prepended to the directions URL…
        $this->assertStringContainsString('origin=27.671%2C85.429', $legs[0]['url']);
        // …and the nearest shop to it is visited first.
        $this->assertSame('Bhaktapur Durbar', $legs[0]['stops'][0]);
    }

    public function test_the_planner_page_renders_every_branch(): void
    {
        config(['services.google_maps.api_key' => 'test-key']);
        $this->seedClusters();
        $this->store('No Pin', 'Kalimati', null, null, url: 'https://www.google.com/maps?q=27.71,85.31');

        $component = Livewire::test(RetailVisitPlanner::class)
            ->assertOk()
            // Coverage banner + its resolve button
            ->assertSee('have no coordinates')
            ->assertSee('Map 1 stores from links');

        // Area mode: cluster cards
        $component->call('setMode', 'area')
            ->assertOk()
            ->assertSee('Asan')
            ->assertSee('shops');

        // Route panel + map preview
        $component->set('selectedStoreIds', RetailStore::whereNotNull('latitude')
            ->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->call('buildRoute')
            ->assertOk()
            ->assertSee('Route ready')
            ->assertSee('maps/dir/?api=1', escape: false)
            ->assertSee('visitPlannerMap', escape: false);
    }

    public function test_the_store_form_renders_the_map_picker(): void
    {
        config(['services.google_maps.api_key' => 'test-key']);

        $store = $this->store('Editable', 'Asan', 27.7060, 85.3095);

        Livewire::test(\App\Filament\Resources\RetailStoreResource\Pages\EditRetailStore::class, [
            'record' => $store->getKey(),
        ])
            ->assertOk()
            ->assertSee('mapPickerComponent', escape: false)
            // The picker is seeded from the saved coordinates so editing opens
            // on the right pin rather than an empty map.
            ->assertFormSet(fn (array $state) => str_starts_with($state['map_location'] ?? '', '27.706'));
    }

    public function test_pasting_a_maps_link_fills_the_coordinate_fields(): void
    {
        $store = $this->store('Linkable', 'Asan', null, null);

        Livewire::test(\App\Filament\Resources\RetailStoreResource\Pages\EditRetailStore::class, [
            'record' => $store->getKey(),
        ])
            ->fillForm(['google_location_url' => 'https://www.google.com/maps?q=27.7154,85.3123'])
            ->assertFormSet([
                'latitude' => '27.7154000',
                'longitude' => '85.3123000',
                'map_location' => '27.7154,85.3123',
            ]);
    }

    public function test_coverage_counts_mapped_and_linked_but_unmapped_stores(): void
    {
        $this->store('Mapped', 'Asan', 27.7060, 85.3095);
        $this->store('Linked only', 'Thamel', null, null, url: 'https://www.google.com/maps?q=27.71,85.31');
        $this->store('Nothing', 'Balaju', null, null);

        $coverage = Livewire::test(RetailVisitPlanner::class)->instance()->getCoverage();

        $this->assertSame(3, $coverage['total']);
        $this->assertSame(1, $coverage['mapped']);
        $this->assertSame(2, $coverage['unmapped']);
        $this->assertSame(1, $coverage['linked_unmapped']);
    }

    /**
     * Every new store defaults to 'prospect', so planning only 'active' shops
     * once left the planner offering 2 of 478 stores.
     */
    public function test_prospects_are_planned_by_default_and_can_be_filtered(): void
    {
        $customer = $this->store('Paying Shop', 'Thamel', 27.7150, 85.3120);
        $prospect = $this->store('New Lead', 'Thamel', 27.7155, 85.3125);
        $prospect->update(['status' => 'prospect']);
        $dormant = $this->store('Closed Down', 'Thamel', 27.7160, 85.3130);
        $dormant->update(['status' => 'inactive']);

        $page = Livewire::test(RetailVisitPlanner::class);

        $names = fn () => $page->instance()->storesQuery()->pluck('store_name')->all();

        // Default scope: everything except inactive.
        $this->assertEqualsCanonicalizing(['Paying Shop', 'New Lead'], $names());
        $this->assertNotContains('Closed Down', $names());

        $page->call('setStatusScope', 'customers');
        $this->assertSame(['Paying Shop'], $names());

        $page->call('setStatusScope', 'prospects');
        $this->assertSame(['New Lead'], $names());
    }
}

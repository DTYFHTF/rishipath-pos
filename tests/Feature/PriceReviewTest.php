<?php

namespace Tests\Feature;

use App\Filament\Pages\PriceReview;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PriceReviewTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create(['country_code' => 'NP']);
        $this->actingAs($this->user(['edit_product_variants']));
    }

    private function user(array $permissions): User
    {
        $key = md5(implode(',', $permissions));

        $role = Role::firstOrCreate(
            ['organization_id' => $this->org->id, 'slug' => 'role-'.$key],
            [
                'name' => 'Role '.implode(',', $permissions),
                'permissions' => $permissions,
                'is_system_role' => false,
            ]
        );

        return User::firstOrCreate(
            ['email' => $key.'@test.local'],
            [
                'organization_id' => $this->org->id,
                'role_id' => $role->id,
                'name' => 'Tester',
                'password' => bcrypt('secret'),
                'active' => true,
            ]
        );
    }

    /**
     * @param  array<string, array{0: float, 1: float}>  $packs  grams => [cost, mrp]
     */
    private function product(string $name, array $packs): Product
    {
        $category = Category::firstOrCreate(
            ['organization_id' => $this->org->id, 'name' => 'Spices'],
            ['active' => true]
        );

        $product = Product::create([
            'organization_id' => $this->org->id,
            'category_id' => $category->id,
            'sku' => 'P-'.str($name)->slug()->upper()->value(),
            'name' => $name,
            'product_type' => 'simple',
            'unit_type' => 'weight',
            'active' => true,
        ]);

        foreach ($packs as $grams => [$cost, $mrp]) {
            ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $product->sku.'-'.$grams,
                'pack_size' => (float) $grams,
                'unit' => 'g',
                'cost_price' => $cost,
                'base_price' => $mrp,
                'mrp_india' => $mrp,
                'selling_price_nepal' => $mrp,
                'active' => true,
            ]);
        }

        return $product->fresh('variants');
    }

    /**
     * A product priced exactly on the formula, so nothing should be flagged:
     * cost 400/kg. 1kg on the bulk tier = 400 x 1.25 = Rs500. 100g on the
     * small tier = 40 x 1.30 = 52, + Rs5 packet = 57, rounded up to Rs60.
     */
    private function alignedProduct(): Product
    {
        return $this->product('Aligned Spice', [
            '100' => [40, 60],
            '1000' => [400, 500],
        ]);
    }

    public function test_a_product_priced_on_the_formula_does_not_appear(): void
    {
        $this->alignedProduct();

        $groups = Livewire::test(PriceReview::class)->instance()->groups;

        $this->assertCount(0, $groups, 'nothing has drifted, so the queue is empty');
    }

    public function test_a_cost_rise_surfaces_the_stale_price(): void
    {
        $product = $this->alignedProduct();

        // Supplier raised the rate: cost per kg goes 400 -> 600, prices untouched.
        $product->variants->each(fn ($v) => $v->update([
            'cost_price' => (float) $v->cost_price * 1.5,
        ]));

        $groups = Livewire::test(PriceReview::class)->instance()->groups;

        $this->assertCount(1, $groups);
        $this->assertSame('Aligned Spice', $groups[0]['product']->name);
        $this->assertSame(600.0, $groups[0]['cost_per_kg']);

        // Every row is under-priced, because cost went up and price did not.
        foreach ($groups[0]['rows'] as $row) {
            $this->assertGreaterThan(0, $row['gap'], "{$row['pack']} should read as under-priced");
            $this->assertGreaterThan($row['current'], $row['derived']);
        }
    }

    public function test_applying_a_product_writes_every_suggested_price(): void
    {
        $product = $this->alignedProduct();
        $product->variants->each(fn ($v) => $v->update(['cost_price' => (float) $v->cost_price * 1.5]));

        $component = Livewire::test(PriceReview::class);
        $before = $component->instance()->groups[0]['rows'];

        $component->call('applyProduct', $product->id)->assertNotified();

        foreach ($before as $row) {
            $this->assertSame(
                $row['derived'],
                (float) ProductVariant::find($row['variant_id'])->selling_price_nepal
            );
        }

        // The queue empties once the prices agree again.
        $this->assertCount(0, Livewire::test(PriceReview::class)->instance()->groups);
    }

    public function test_applying_a_single_pack_leaves_the_others_alone(): void
    {
        $product = $this->alignedProduct();
        $product->variants->each(fn ($v) => $v->update(['cost_price' => (float) $v->cost_price * 1.5]));

        $component = Livewire::test(PriceReview::class);
        $rows = $component->instance()->groups[0]['rows'];
        $target = $rows[0];
        $other = $rows[1];

        $component->call('applyVariant', $target['variant_id'])->assertNotified();

        $this->assertSame($target['derived'], (float) ProductVariant::find($target['variant_id'])->selling_price_nepal);
        $this->assertSame($other['current'], (float) ProductVariant::find($other['variant_id'])->selling_price_nepal);
    }

    public function test_keeping_a_price_locks_it_out_of_the_queue(): void
    {
        $product = $this->alignedProduct();
        $product->variants->each(fn ($v) => $v->update(['cost_price' => (float) $v->cost_price * 1.5]));

        $component = Livewire::test(PriceReview::class);
        $row = $component->instance()->groups[0]['rows'][0];

        $component->call('lockVariant', $row['variant_id'])->assertNotified();

        $variant = ProductVariant::find($row['variant_id']);
        $this->assertTrue((bool) $variant->manual_price_locked);
        $this->assertSame($row['current'], (float) $variant->selling_price_nepal, 'locking must not change the price');

        // It no longer appears, but its siblings still do.
        $remaining = collect(Livewire::test(PriceReview::class)->instance()->groups)
            ->flatMap(fn ($g) => $g['rows'])
            ->pluck('variant_id');

        $this->assertNotContains($row['variant_id'], $remaining);
        $this->assertGreaterThan(0, $remaining->count());
    }

    public function test_a_locked_price_is_never_overwritten_by_apply_all(): void
    {
        $product = $this->alignedProduct();
        $product->variants->each(fn ($v) => $v->update(['cost_price' => (float) $v->cost_price * 1.5]));

        $locked = $product->variants->first();
        $lockedPrice = (float) $locked->selling_price_nepal;
        $locked->update(['manual_price_locked' => true]);

        Livewire::test(PriceReview::class)->call('applyProduct', $product->id);

        $this->assertSame($lockedPrice, (float) $locked->fresh()->selling_price_nepal);
    }

    public function test_a_cost_fall_reads_as_over_priced(): void
    {
        $product = $this->alignedProduct();
        $product->variants->each(fn ($v) => $v->update(['cost_price' => (float) $v->cost_price * 0.5]));

        $groups = Livewire::test(PriceReview::class)->instance()->groups;

        foreach ($groups[0]['rows'] as $row) {
            $this->assertLessThan(0, $row['gap'], "{$row['pack']} should read as over-priced");
        }
    }

    public function test_the_filters_split_under_and_over_priced(): void
    {
        $up = $this->product('Costlier Now', ['100' => [60, 60], '1000' => [600, 520]]);
        $down = $this->product('Cheaper Now', ['100' => [20, 60], '1000' => [200, 520]]);

        $names = fn ($filter) => collect(
            Livewire::test(PriceReview::class)->set('filter', $filter)->instance()->groups
        )->pluck('product.name');

        $this->assertContains('Costlier Now', $names('up')->all());
        $this->assertNotContains('Cheaper Now', $names('up')->all());

        $this->assertContains('Cheaper Now', $names('down')->all());
        $this->assertNotContains('Costlier Now', $names('down')->all());

        $this->assertCount(2, $names('all'));
    }

    public function test_search_narrows_to_one_product(): void
    {
        $this->product('Cumin Seeds', ['1000' => [600, 520], '100' => [60, 60]]);
        $this->product('Coriander Powder', ['1000' => [600, 520], '100' => [60, 60]]);

        $groups = Livewire::test(PriceReview::class)->set('search', 'cumin')->instance()->groups;

        $this->assertCount(1, $groups);
        $this->assertSame('Cumin Seeds', $groups[0]['product']->name);
    }

    public function test_a_product_is_reviewed_against_its_own_markup(): void
    {
        // 1399 x 1.43 = 2001 -> 2005. On its own blend markup this price is
        // correct; on the standard 1.30 markup it would look 10% over-priced.
        $blend = $this->product('Garam Masala', ['1000' => [1399, 2005]]);
        $blend->update(['retail_markup' => \App\Services\PackPricing::BLEND_MARKUP]);

        $this->assertCount(0, Livewire::test(PriceReview::class)->instance()->groups,
            'a product on its own markup must not be flagged');

        // Clearing the markup drops it back to the standard rate, and the same
        // price now reads as drifted — markup is data, not a name.
        $blend->update(['retail_markup' => null]);

        $this->assertCount(1, Livewire::test(PriceReview::class)->instance()->groups);
    }

    public function test_a_premium_markup_sets_the_target_price(): void
    {
        // Rishipeya positioned at Rs3000/kg on a Rs1596/kg cost.
        $p = $this->product('Rishipeya', ['1000' => [1596, 2285], '100' => [159.6, 230]]);
        $p->update(['retail_markup' => 1.88]);

        $groups = Livewire::test(PriceReview::class)->instance()->groups;

        $kilo = collect($groups[0]['rows'])->firstWhere('pack', '1000 G');
        $this->assertSame(3005.0, $kilo['derived']);
        $this->assertGreaterThan(0.28, $kilo['gap'], 'roughly a 31% uplift from todays price');
    }

    public function test_the_page_renders(): void
    {
        $product = $this->alignedProduct();
        $product->variants->each(fn ($v) => $v->update(['cost_price' => (float) $v->cost_price * 1.5]));

        Livewire::test(PriceReview::class)
            ->assertOk()
            ->assertSee('Aligned Spice')
            ->assertSee('Under-priced')
            ->assertSee('Apply all');
    }

    public function test_the_empty_state_renders_when_nothing_has_drifted(): void
    {
        $this->alignedProduct();

        Livewire::test(PriceReview::class)
            ->assertOk()
            ->assertSee('Every price matches its cost');
    }

    public function test_a_sales_agent_cannot_reach_the_page(): void
    {
        $this->actingAs($this->user(['access_pos_billing', 'view_products']));

        $this->assertFalse(PriceReview::canAccess());
    }

    public function test_a_manager_can_reach_the_page(): void
    {
        $this->actingAs($this->user(['edit_product_variants']));

        $this->assertTrue(PriceReview::canAccess());
    }
}

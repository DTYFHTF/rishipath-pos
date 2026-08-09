<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\CostRepricer;
use App\Services\PackPricing;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Setting one cost per kilo has to move the whole product, because the
 * alternative — editing six packs by hand — is what let packs of the same
 * product end up disagreeing about what the product costs.
 */
class CostRepricerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Organization::factory()->create(['id' => 1, 'slug' => 'rishipath']);
        $this->seed(ProductCatalogSeeder::class);
    }

    private function almond(): Product
    {
        return Product::with('variants')->where('name', 'Almond')->firstOrFail();
    }

    public function test_every_pack_cost_becomes_its_share_of_the_new_rate(): void
    {
        CostRepricer::apply($this->almond(), 2000.0);

        foreach ($this->almond()->variants->where('active', true) as $variant) {
            $expected = round(2000.0 * ($variant->comparable_size / 1000), 2);

            $this->assertEqualsWithDelta($expected, (float) $variant->cost_price, 0.01,
                "{$variant->pack_label} cost does not match the new per-kilo rate");
        }
    }

    public function test_prices_follow_the_new_cost_through_the_normal_tiers(): void
    {
        CostRepricer::apply($this->almond(), 2000.0);

        foreach ($this->almond()->variants->where('active', true) as $variant) {
            $expected = PackPricing::packPriceFromCost(2000.0, (float) $variant->comparable_size);

            $this->assertSame($expected, (float) $variant->selling_price_nepal,
                "{$variant->pack_label} was not repriced through PackPricing");
        }
    }

    public function test_a_cost_cut_brings_prices_down_too(): void
    {
        $before = (float) $this->almond()->variants->firstWhere('unit', 'KG')->selling_price_nepal;

        CostRepricer::apply($this->almond(), 400.0);

        $after = (float) $this->almond()->variants->firstWhere('unit', 'KG')->selling_price_nepal;

        $this->assertLessThan($before, $after, 'a cheaper cost must reach the shelf, not just the margin');
        $this->assertSame(500.0, $after, '400/kg at the 1.25 bulk tier');
    }

    public function test_a_locked_pack_keeps_its_price_but_still_records_the_new_cost(): void
    {
        $variant = $this->almond()->variants->firstWhere('unit', 'KG');
        $variant->forceFill(['selling_price_nepal' => 1999, 'manual_price_locked' => true])->save();

        CostRepricer::apply($this->almond(), 2000.0);

        $fresh = ProductVariant::find($variant->id);

        $this->assertSame(1999.0, (float) $fresh->selling_price_nepal,
            'a manual override is a decision about the selling price and must survive');
        $this->assertEqualsWithDelta(2000.0, (float) $fresh->cost_price, 0.01,
            'the cost is a fact, not a decision — recording it is what keeps margin reports honest');
    }

    public function test_a_cheap_staple_is_held_but_not_below_its_new_cost(): void
    {
        $product = Product::with('variants')->where('name', 'Gud Normal')->firstOrFail();
        $small = $product->variants->where('active', true)
            ->sortBy(fn ($v) => $v->comparable_size)->first();
        $small->forceFill(['selling_price_nepal' => 5, 'base_price' => 5])->save();

        // A rate high enough that this pack's share alone exceeds Rs5.
        CostRepricer::apply($product->fresh('variants'), 2000.0);

        $fresh = ProductVariant::find($small->id);

        $this->assertGreaterThan((float) $fresh->cost_price, (float) $fresh->selling_price_nepal,
            'the staple hold must never mean holding a pack below what it now costs');
    }

    public function test_preview_reports_what_apply_will_do_without_writing_anything(): void
    {
        $rows = CostRepricer::preview($this->almond(), 2000.0);

        $this->assertNotEmpty($rows);

        foreach ($this->almond()->variants as $variant) {
            $this->assertNotEqualsWithDelta(2000.0, (float) $variant->cost_price, 0.01,
                'preview must not touch the database');
        }

        $kg = collect($rows)->firstWhere('pack', $this->almond()->variants->firstWhere('unit', 'KG')->pack_label);
        $this->assertSame(2500.0, $kg['price_new'], '2000/kg at the 1.25 bulk tier');
    }

    public function test_a_zero_or_negative_cost_is_rejected_rather_than_zeroing_the_catalogue(): void
    {
        $this->assertSame([], CostRepricer::preview($this->almond(), 0.0));
        $this->assertSame([], CostRepricer::preview($this->almond(), -50.0));
        $this->assertSame(['costs' => 0, 'prices' => 0], CostRepricer::apply($this->almond(), 0.0));
    }

    public function test_applying_the_same_cost_twice_changes_nothing_the_second_time(): void
    {
        CostRepricer::apply($this->almond(), 2000.0);
        $second = CostRepricer::apply($this->almond()->fresh('variants'), 2000.0);

        $this->assertSame(['costs' => 0, 'prices' => 0], $second,
            'a no-op must report as one, or the admin cannot tell whether anything happened');
    }

    private function adminWith(array $permissions): User
    {
        $role = Role::create([
            'organization_id' => 1,
            'slug' => 'r-'.md5(implode(',', $permissions)),
            'name' => 'Tester',
            'permissions' => $permissions,
            'is_system_role' => false,
        ]);

        return User::create([
            'organization_id' => 1,
            'role_id' => $role->id,
            'name' => 'Tester',
            'email' => md5(implode(',', $permissions)).'@test.local',
            'password' => bcrypt('secret'),
            'active' => true,
        ]);
    }

    public function test_an_admin_can_reprice_a_product_from_the_products_table(): void
    {
        $this->actingAs($this->adminWith(['view_products', 'edit_product_variants']));

        $product = $this->almond();

        Livewire::test(ListProducts::class)
            ->callTableAction('setCost', $product, ['cost_per_kg' => 2000])
            ->assertHasNoTableActionErrors();

        $kg = $this->almond()->variants->firstWhere('unit', 'KG');

        $this->assertSame(2500.0, (float) $kg->selling_price_nepal);
        $this->assertEqualsWithDelta(2000.0, (float) $kg->cost_price, 0.01);
    }

    public function test_the_action_rejects_a_cost_of_zero_from_the_form(): void
    {
        $this->actingAs($this->adminWith(['view_products', 'edit_product_variants']));

        $before = (float) $this->almond()->variants->firstWhere('unit', 'KG')->selling_price_nepal;

        Livewire::test(ListProducts::class)
            ->callTableAction('setCost', $this->almond(), ['cost_per_kg' => 0])
            ->assertHasTableActionErrors(['cost_per_kg']);

        $this->assertSame($before, (float) $this->almond()->variants->firstWhere('unit', 'KG')->selling_price_nepal);
    }

    public function test_a_user_without_variant_edit_rights_does_not_get_the_action(): void
    {
        $this->actingAs($this->adminWith(['view_products']));

        Livewire::test(ListProducts::class)
            ->assertTableActionHidden('setCost', $this->almond());
    }
}

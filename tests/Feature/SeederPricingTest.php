<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PackPricing;
use Database\Seeders\BlendProductsSeeder;
use Database\Seeders\MultaniMittiSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prices used to come from three independent, hand-typed sources — a table
 * embedded in ProductCatalogSeeder, a margin formula in BlendProductsSeeder,
 * a flat constant in MultaniMittiSeeder — none of which agreed with each
 * other or with PackPricing, and all of which ran on every single deploy.
 * That is why a fix applied once (in PackPricing, or by hand in the admin
 * panel) never stuck: the next `db:seed --force` silently overwrote it.
 *
 * These tests pin the two ways that could go wrong even after routing
 * everything through PackPricing: a seeder calling packPrice() directly
 * bypasses the staple-protection floor and the manual-lock check that only
 * live inside previewProduct().
 */
class SeederPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ProductCatalogSeeder hardcodes organization_id 1; the other two
        // seeders look up the 'rishipath' slug. A fresh test database gives
        // the first inserted organization id 1.
        Organization::factory()->create(['id' => 1, 'slug' => 'rishipath']);
    }

    private function seedCatalog(): void
    {
        $this->seed(ProductCatalogSeeder::class);
    }

    public function test_the_catalog_seeds_without_error(): void
    {
        $this->seedCatalog();

        $this->assertGreaterThan(50, Product::where('active', true)->count());
    }

    public function test_every_pack_price_matches_what_pack_pricing_would_derive_from_its_own_cost(): void
    {
        $this->seedCatalog();

        $product = Product::with('variants')->where('name', 'Almond')->firstOrFail();
        $kiloVariant = $product->variants->first(fn ($v) => strtoupper($v->unit) === 'KG');
        $kiloPrice = (float) $kiloVariant->selling_price_nepal;

        foreach ($product->variants as $variant) {
            $expected = PackPricing::packPrice($kiloPrice, (float) $variant->comparable_size);
            $this->assertSame(
                $expected,
                (float) $variant->selling_price_nepal,
                "{$variant->pack_label} does not match the shared formula"
            );
        }
    }

    public function test_reseeding_does_not_raise_a_staple_already_at_or_below_rs20(): void
    {
        $this->seedCatalog();

        // Simulate the real production state this bug shipped against: a
        // cheap staple already sitting at a low price from before the fix.
        $variant = ProductVariant::whereHas('product', fn ($q) => $q->where('name', 'Gud Normal'))
            ->where('unit', 'GMS')->where('pack_size', 20)->firstOrFail();
        $variant->forceFill(['selling_price_nepal' => 5, 'base_price' => 5, 'mrp_india' => 5])->save();

        $this->seedCatalog();

        $this->assertSame(5.0, (float) $variant->fresh()->selling_price_nepal,
            'a reseed must never push a Rs5 staple pack up on its own');
    }

    public function test_reseeding_leaves_a_manually_locked_price_alone(): void
    {
        $this->seedCatalog();

        $variant = ProductVariant::whereHas('product', fn ($q) => $q->where('name', 'Almond'))
            ->where('unit', 'KG')->firstOrFail();
        $variant->forceFill([
            'selling_price_nepal' => 1999, 'base_price' => 1999, 'mrp_india' => 1999,
            'manual_price_locked' => true,
        ])->save();

        $this->seedCatalog();

        $this->assertSame(1999.0, (float) $variant->fresh()->selling_price_nepal);
        $this->assertTrue((bool) $variant->fresh()->manual_price_locked);
    }

    public function test_a_locked_price_still_deactivates_if_dropped_from_the_rate_list(): void
    {
        // Locking a price must not also lock the variant active forever —
        // it only protects the price, not the catalog membership.
        $this->seedCatalog();

        $variant = ProductVariant::whereHas('product', fn ($q) => $q->where('name', 'Almond'))
            ->where('unit', 'GMS')->where('pack_size', 20)->firstOrFail();
        $variant->update(['manual_price_locked' => true]);

        $this->assertTrue($variant->fresh()->active);
    }

    public function test_the_full_seed_chain_including_blends_runs_without_error(): void
    {
        $this->seedCatalog();
        $this->seed(BlendProductsSeeder::class);
        $this->seed(MultaniMittiSeeder::class);

        $this->assertDatabaseHas('products', ['name' => 'Garam Masala', 'active' => true]);
        $this->assertDatabaseHas('products', ['name' => 'Rishipeya', 'active' => true]);
        $this->assertDatabaseHas('products', ['name' => 'Multani Mitti', 'active' => true]);
    }

    public function test_garam_masala_lands_on_its_flat_rs2000_target(): void
    {
        $this->seedCatalog();
        $this->seed(BlendProductsSeeder::class);

        $kg = ProductVariant::whereHas('product', fn ($q) => $q->where('name', 'Garam Masala'))
            ->where('unit', 'KG')->firstOrFail();

        $this->assertSame(2000.0, (float) $kg->selling_price_nepal);
    }

    public function test_rishipeya_lands_on_its_flat_rs3000_target(): void
    {
        $this->seedCatalog();
        $this->seed(BlendProductsSeeder::class);

        $kg = ProductVariant::whereHas('product', fn ($q) => $q->where('name', 'Rishipeya'))
            ->where('unit', 'KG')->firstOrFail();

        $this->assertSame(3000.0, (float) $kg->selling_price_nepal);
    }

    public function test_rishipeya_and_garam_masala_no_longer_share_a_derived_margin(): void
    {
        // The old design solved Garam Masala's margin and reused the same
        // fraction for Rishipeya. Each blend now has its own independent
        // target, so their markups need not (and here, do not) match.
        $this->seedCatalog();
        $this->seed(BlendProductsSeeder::class);

        $garamMasala = Product::where('name', 'Garam Masala')->firstOrFail();
        $rishipeya = Product::where('name', 'Rishipeya')->firstOrFail();

        $this->assertNotEqualsWithDelta(
            (float) $garamMasala->retail_markup,
            (float) $rishipeya->retail_markup,
            0.01
        );
    }

    public function test_blend_reseed_respects_a_manual_lock_too(): void
    {
        $this->seedCatalog();
        $this->seed(BlendProductsSeeder::class);

        $variant = ProductVariant::whereHas('product', fn ($q) => $q->where('name', 'Rishipeya'))
            ->where('unit', 'GMS')->where('pack_size', 20)->firstOrFail();
        $variant->forceFill(['selling_price_nepal' => 999, 'manual_price_locked' => true])->save();

        $this->seed(BlendProductsSeeder::class);

        $this->assertSame(999.0, (float) $variant->fresh()->selling_price_nepal);
    }

    public function test_multani_mitti_lands_on_its_target_and_survives_reseed(): void
    {
        $this->seedCatalog();
        $this->seed(MultaniMittiSeeder::class);

        $variant = ProductVariant::where('sku', 'PC-MM-200GMS')->firstOrFail();
        $expected = PackPricing::packPrice(
            PackPricing::kilogramPrice(250.0, (float) $variant->product->retail_markup),
            200.0
        );

        $this->assertSame($expected, (float) $variant->selling_price_nepal);

        // And a reseed must not move it further.
        $priceAfterFirstSeed = $variant->selling_price_nepal;
        $this->seed(MultaniMittiSeeder::class);
        $this->assertSame((float) $priceAfterFirstSeed, (float) $variant->fresh()->selling_price_nepal);
    }

    public function test_a_products_own_markup_survives_being_reseeded_by_product_catalog_seeder(): void
    {
        // Rishipeya is not in ProductCatalogSeeder's list, but nothing stops
        // a future product from carrying a premium retail_markup while also
        // matching a name in that seeder's rate list — its markup must not
        // be clobbered back to the standard rate on the next deploy.
        $this->seedCatalog();

        $product = Product::where('name', 'Almond')->firstOrFail();
        $product->update(['retail_markup' => 1.88]);

        $this->seedCatalog();

        $this->assertSame(1.88, (float) $product->fresh()->retail_markup);

        $kg = ProductVariant::whereHas('product', fn ($q) => $q->where('name', 'Almond'))
            ->where('unit', 'KG')->firstOrFail();
        // cost/kg is 1600 per the rate list; at a 1.88 markup that's 3008,
        // rounded up to the next Rs5.
        $this->assertSame(3010.0, (float) $kg->selling_price_nepal);
    }
}

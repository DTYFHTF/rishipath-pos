<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PackVariantGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackVariantGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
            'sku' => 'SHD-TEST',
            'has_variants' => false,
        ]);
    }

    public function test_it_creates_one_variant_per_pack_with_its_share_of_the_kilo_cost(): void
    {
        $result = PackVariantGenerator::generate($this->product, [100, 500, 1000], 320.0);

        $this->assertSame(3, $result['created']);

        $costs = ProductVariant::where('product_id', $this->product->id)
            ->get()
            ->mapWithKeys(fn ($v) => [$v->pack_size.$v->unit => (float) $v->cost_price]);

        $this->assertEqualsWithDelta(32.0, $costs['100.000GMS'], 0.01);
        $this->assertEqualsWithDelta(160.0, $costs['500.000GMS'], 0.01);
        $this->assertEqualsWithDelta(320.0, $costs['1.000KG'], 0.01);
    }

    public function test_a_kilo_is_stored_as_one_kg_not_a_thousand_grams(): void
    {
        // Both spellings exist in the live data and do not compare equal, which
        // shows the same pack twice in the POS.
        PackVariantGenerator::generate($this->product, [1000], 100.0);

        $variant = ProductVariant::where('product_id', $this->product->id)->sole();

        $this->assertSame('KG', $variant->unit);
        $this->assertEqualsWithDelta(1.0, (float) $variant->pack_size, 0.001);
    }

    public function test_prices_are_derived_from_the_cost_without_being_asked_for(): void
    {
        PackVariantGenerator::generate($this->product, [1000], 320.0);

        $variant = ProductVariant::where('product_id', $this->product->id)->sole();

        $this->assertNotNull($variant->sku);
        $this->assertNotNull($variant->selling_price_nepal);
        $this->assertGreaterThan(320.0, (float) $variant->selling_price_nepal);
    }

    public function test_it_leaves_an_existing_live_variant_alone(): void
    {
        PackVariantGenerator::generate($this->product, [100], 320.0);
        $before = ProductVariant::where('product_id', $this->product->id)->sole();
        $before->forceFill(['selling_price_nepal' => 999, 'manual_price_locked' => true])->save();

        $result = PackVariantGenerator::generate($this->product, [100, 500], 999.0);

        $this->assertSame(1, $result['created']);   // only the 500g
        $this->assertSame(1, $result['skipped']);   // the negotiated 100g
        $this->assertEqualsWithDelta(999.0, (float) $before->refresh()->selling_price_nepal, 0.01);
    }

    public function test_it_switches_a_deactivated_pack_back_on_rather_than_duplicating_it(): void
    {
        PackVariantGenerator::generate($this->product, [100], 320.0);
        ProductVariant::where('product_id', $this->product->id)->update(['active' => false]);

        $result = PackVariantGenerator::generate($this->product, [100], 320.0);

        $this->assertSame(1, $result['reactivated']);
        $this->assertSame(0, $result['created']);
        $this->assertCount(1, ProductVariant::where('product_id', $this->product->id)->get());
    }

    public function test_generating_variants_marks_the_product_as_having_them(): void
    {
        PackVariantGenerator::generate($this->product, PackVariantGenerator::STANDARD_PACKS, 320.0);

        $this->assertTrue($this->product->refresh()->has_variants);
        $this->assertCount(7, ProductVariant::where('product_id', $this->product->id)->get());
    }
}

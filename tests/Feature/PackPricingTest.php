<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PackPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackPricingTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create(['country_code' => 'NP']);
    }

    /** @param array<string,array{0:float,1:float}> $packs  pack "<grams>" => [cost, current mrp] */
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

    // ── The formula ───────────────────────────────────────────────────────

    public function test_a_pack_is_its_share_of_the_kilo_price_plus_the_packet_charge(): void
    {
        // Rs400/kg: 500g = 200 + 5 = 205, 100g = 40 + 5 = 45
        $this->assertSame(205.0, PackPricing::packPrice(400, 500));
        $this->assertSame(45.0, PackPricing::packPrice(400, 100));
        // 20g = 8 + 5 = 13, rounded up to the next Rs5
        $this->assertSame(15.0, PackPricing::packPrice(400, 20));
    }

    public function test_the_kilo_pack_carries_no_packet_charge(): void
    {
        $this->assertSame(400.0, PackPricing::packPrice(400, 1000));
    }

    public function test_every_price_is_a_multiple_of_five(): void
    {
        foreach ([97, 250, 333, 1399, 2285] as $kilo) {
            foreach ([20, 50, 100, 200, 500, 1000] as $grams) {
                $price = PackPricing::packPrice($kilo, $grams);
                $this->assertSame(0.0, fmod($price, 5.0), "Rs{$kilo}/kg at {$grams}g gave {$price}");
            }
        }
    }

    public function test_the_packet_charge_never_exceeds_the_value_of_the_goods(): void
    {
        // Gud at Rs125/kg: 20g of product is worth Rs2.50. A flat Rs5 fee would
        // make it Rs7.50 -> Rs10, doubling the cheapest staple in the catalogue.
        $this->assertSame(5.0, PackPricing::packPrice(125, 20));

        // The cap only bites while the goods are worth less than the fee.
        $this->assertSame(15.0, PackPricing::packPrice(400, 20));   // Rs8 of goods, full Rs5 fee
    }

    public function test_a_price_never_falls_below_one_step(): void
    {
        $this->assertSame(5.0, PackPricing::packPrice(10, 20));
    }

    public function test_kilo_price_is_cost_times_the_bulk_markup(): void
    {
        $this->assertSame(1.25, PackPricing::RETAIL_MARKUP_BULK);
        // 320 * 1.25 = 400, already a multiple of Rs5
        $this->assertSame(400.0, PackPricing::kilogramPrice(320));
        // Blends carry the processing markup
        $this->assertSame(2005.0, PackPricing::kilogramPrice(1399, PackPricing::BLEND_MARKUP));
    }

    public function test_packs_under_half_a_kilo_carry_the_higher_markup(): void
    {
        $this->assertSame(1.25, PackPricing::markupForPack(1000));
        $this->assertSame(1.25, PackPricing::markupForPack(500), 'the threshold itself is bulk');
        $this->assertSame(1.30, PackPricing::markupForPack(499.9));
        $this->assertSame(1.30, PackPricing::markupForPack(200));
        $this->assertSame(1.30, PackPricing::markupForPack(20));
    }

    public function test_an_explicit_product_markup_overrides_the_size_tier(): void
    {
        // A blend or premium line is priced to hit a deliberate Rs/kg target;
        // adding a size tier on top would push it past that number.
        $this->assertSame(1.88, PackPricing::markupForPack(20, 1.88));
        $this->assertSame(1.88, PackPricing::markupForPack(1000, 1.88));
    }

    public function test_the_tier_makes_a_small_pack_dearer_per_gram_than_the_kilo(): void
    {
        // Rs320/kg. 1kg at 25% = Rs400. 100g at 30% = 41.60 + Rs5 packet = Rs50,
        // which is Rs500/kg-equivalent — dearer per gram, by design.
        $this->assertSame(400.0, PackPricing::packPriceFromCost(320, 1000));
        $this->assertSame(50.0, PackPricing::packPriceFromCost(320, 100));

        $kiloRate = 400.0;
        $smallPackRate = 50.0 / 0.1;
        $this->assertGreaterThan($kiloRate, $smallPackRate);
    }

    public function test_markup_is_read_from_the_product_not_its_name(): void
    {
        $p = $this->product('Rishipeya', ['1000' => [1596, 2285]]);

        // Nothing set: the standard retail markup applies.
        $this->assertSame(PackPricing::RETAIL_MARKUP, PackPricing::markupFor($p));

        // A renamed premium product keeps its markup, because it is data.
        $p->update(['retail_markup' => 1.88, 'name' => 'Renamed Blend']);
        $this->assertSame(1.88, PackPricing::markupFor($p->fresh()));
        $this->assertSame(3005.0, PackPricing::kilogramPrice(1596, PackPricing::markupFor($p->fresh())));
    }

    public function test_a_zero_markup_falls_back_to_the_standard_rate(): void
    {
        $p = $this->product('Odd Data', ['1000' => [400, 520]]);
        $p->update(['retail_markup' => 0]);

        $this->assertSame(PackPricing::RETAIL_MARKUP, PackPricing::markupFor($p->fresh()));
    }

    public function test_zero_or_missing_inputs_return_null_rather_than_a_zero_price(): void
    {
        $this->assertNull(PackPricing::packPrice(0, 100));
        $this->assertNull(PackPricing::packPrice(400, 0));
        $this->assertNull(PackPricing::kilogramPrice(0));
    }

    // ── Derived from real product data ────────────────────────────────────

    public function test_cost_per_kilo_is_read_from_any_convertible_pack(): void
    {
        $p = $this->product('Coriander Powder', [
            '100' => [32, 50], '500' => [160, 215], '1000' => [320, 400],
        ]);

        $this->assertSame(320.0, PackPricing::costPerKg($p));
    }

    public function test_the_small_pack_premium_collapses(): void
    {
        // Today: 20g of Rs320/kg coriander sells at Rs20 — a 212% markup on a
        // Rs6.40 cost, while the 1kg buyer pays 25%.
        $p = $this->product('Coriander Powder', [
            '20' => [6.40, 20], '1000' => [320, 400],
        ]);

        $preview = PackPricing::previewProduct($p, allowRises: true);
        $small = collect($preview)->first(fn ($e) => (int) $e['variant']->comparable_size === 20);

        // Rs320 x 1.30 = Rs420/kg -> 20g = 8.40 + 5 = Rs15
        $this->assertSame(15.0, $small['derived']);
        $markup = ($small['derived'] - 6.40) / 6.40;
        $this->assertLessThan(1.5, $markup, 'the 20g markup must come down from 212%');
    }

    public function test_a_locked_price_survives_recalculation(): void
    {
        $p = $this->product('Garam Masala', ['20' => [28, 40], '1000' => [1399, 2000]]);
        $p->variants->firstWhere('pack_size', 20.0)->update(['manual_price_locked' => true]);

        $preview = PackPricing::previewProduct($p->fresh('variants'), allowRises: true);
        $small = collect($preview)->first(fn ($e) => (int) $e['variant']->comparable_size === 20);

        $this->assertSame(40.0, $small['derived'], 'a deliberate override must not be recalculated');
        $this->assertTrue($small['locked']);
    }

    public function test_cheap_staples_are_never_raised_even_when_rises_are_allowed(): void
    {
        // Gud: Rs5 for 20g today. The formula would push it to Rs10.
        $p = $this->product('Gud Normal', ['20' => [2, 5], '1000' => [100, 130]]);

        $preview = PackPricing::previewProduct($p, allowRises: true);
        $small = collect($preview)->first(fn ($e) => (int) $e['variant']->comparable_size === 20);

        $this->assertSame(5.0, $small['derived'], 'a Rs5 staple pack must not double');
        $this->assertTrue($small['capped']);
    }

    public function test_expensive_packs_may_rise_when_rises_are_allowed(): void
    {
        // Priced below what the formula says: 500g of a Rs800/kg item is
        // 400 x 1.25 = 500, + Rs5 packet = Rs505, against Rs400 today.
        $p = $this->product('Walnut Premium', ['500' => [400, 400], '1000' => [800, 800]]);

        $rises = PackPricing::previewProduct($p, allowRises: true);
        $held = PackPricing::previewProduct($p, allowRises: false);

        $mid = fn ($set) => collect($set)->first(fn ($e) => (int) $e['variant']->comparable_size === 500);

        $this->assertGreaterThan(400.0, $mid($rises)['derived']);
        $this->assertSame(400.0, $mid($held)['derived'], 'without --allow-rises the cheaper price stands');
        $this->assertTrue($mid($held)['capped']);
    }

    public function test_a_product_with_no_cost_price_yields_no_derived_price(): void
    {
        $p = $this->product('Mystery Spice', ['100' => [0, 50], '1000' => [0, 400]]);

        $this->assertNull(PackPricing::costPerKg($p));
        foreach (PackPricing::previewProduct($p) as $entry) {
            $this->assertNull($entry['derived']);
        }
    }

    public function test_pack_price_derives_a_flat_ladder_from_an_already_marked_up_kilo_price(): void
    {
        // packPrice() is the flat-markup helper, used where the Rs/kg figure
        // is a deliberate target (blends, premium lines) and no size tier
        // applies. Every pack is then a plain share of that kilo price.
        $kilo = 500.0;

        foreach ([20, 50, 100, 200, 500] as $grams) {
            $expected = PackPricing::roundToStep(
                $kilo * ($grams / 1000) + min(5, $kilo * ($grams / 1000))
            );
            $this->assertSame($expected, PackPricing::packPrice($kilo, $grams));
        }
    }

    public function test_a_tiered_pack_is_deliberately_not_a_plain_share_of_the_kilo_price(): void
    {
        // Worth pinning: with the 25/30 split a 100g pack is priced off its
        // own tier, so it is NOT the kilo price divided by ten plus the
        // packet fee. Anyone reaching for that shortcut is computing the old
        // flat-markup answer and will land low.
        $costPerKg = 320.0;

        $kilo = PackPricing::packPriceFromCost($costPerKg, 1000);
        $hundred = PackPricing::packPriceFromCost($costPerKg, 100);

        $flatShortcut = PackPricing::packPrice($kilo, 100);

        $this->assertSame(400.0, $kilo);
        $this->assertSame(50.0, $hundred);
        $this->assertSame(45.0, $flatShortcut);
        $this->assertGreaterThan($flatShortcut, $hundred, 'the small-pack tier is what makes up the difference');
    }
}

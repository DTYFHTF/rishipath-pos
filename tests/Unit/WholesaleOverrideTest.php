<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PricingService;
use Tests\TestCase;

/**
 * wholesale_price lets a human fix a dealer price directly — the wholesale
 * counterpart to manual_price_locked on retail. It exists because the 13%
 * cost-formula wholesale price is not always the right dealer price: Moringa
 * Powder's 100g pack retails at a locked Rs250 (a premium price unrelated to
 * its Rs100 cost) but the intended dealer discount is a flat Rs75 off that,
 * which the formula — anchored to cost, not to retail — cannot produce.
 */
class WholesaleOverrideTest extends TestCase
{
    private function variant(array $attrs = []): ProductVariant
    {
        $product = new Product;
        $variant = new ProductVariant(array_merge([
            'cost_price' => 100.0,
            'selling_price_nepal' => 250.0,
            'wholesale_price' => null,
        ], $attrs));
        $variant->setRelation('product', $product);

        return $variant;
    }

    public function test_an_override_is_returned_exactly_as_entered(): void
    {
        $variant = $this->variant(['wholesale_price' => 175.0]);

        $this->assertSame(175.0, PricingService::getWholesalePrice($variant));
    }

    public function test_an_override_below_cost_is_still_honoured(): void
    {
        // Whoever set it owns the consequence — same rule as
        // manual_price_locked on the retail side.
        $variant = $this->variant(['cost_price' => 500.0, 'wholesale_price' => 100.0]);

        $this->assertSame(100.0, PricingService::getWholesalePrice($variant));
    }

    public function test_without_an_override_the_cost_formula_still_runs(): void
    {
        $variant = $this->variant(['cost_price' => 200.0, 'wholesale_price' => null]);

        $this->assertNotNull(PricingService::getWholesalePrice($variant));
        $this->assertNotSame(0.0, PricingService::getWholesalePrice($variant));
    }

    public function test_a_zero_override_is_treated_as_no_override(): void
    {
        // Guards against a stray 0 (e.g. a cleared numeric form field)
        // silently making a pack free at the till.
        $variant = $this->variant(['cost_price' => 200.0, 'wholesale_price' => 0.0]);

        $this->assertGreaterThan(0.0, PricingService::getWholesalePrice($variant));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Receiving a purchase used to price the ONE received pack from its own
 * cost via a flat markup-percent table (PricingService::suggestVariantPrices)
 * — a different formula from PackPricing, applied to a single pack in
 * isolation. That is exactly the mechanism that let packs of the same
 * product disagree about what the product costs (Bay Leaf: kilo Rs300/kg,
 * 25g pack Rs1,200/kg) — receiving one pack at a new rate could silently
 * push it out of step with its siblings.
 *
 * Receiving now updates the pack's cost and reprices the WHOLE product
 * through PackPricing::reprice() — the same call Price Review and Set Cost
 * use — so a delivery can never disagree with the rest of the system about
 * what a given cost implies.
 */
class PurchaseReceiptRepricingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Store $store;

    private Supplier $supplier;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->store = Store::factory()->create(['organization_id' => $this->org->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->org->id]);
        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($this->user);
    }

    /** @return array{0: Product, 1: ProductVariant, 2: ProductVariant} product, kg, 100g */
    private function productWithTwoPacks(): array
    {
        $product = Product::factory()->create(['organization_id' => $this->org->id]);

        $kg = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'pack_size' => 1000,
            'unit' => 'GMS',
            'cost_price' => 300,
            'selling_price_nepal' => 375,
            'base_price' => 375,
            'mrp_india' => 375,
        ]);

        $small = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'pack_size' => 100,
            'unit' => 'GMS',
            'cost_price' => 110, // stale — implies 1100/kg, disagrees with the kilo
            'selling_price_nepal' => 45,
            'base_price' => 45,
            'mrp_india' => 45,
        ]);

        return [$product, $kg, $small];
    }

    public function test_receiving_a_pack_reprices_every_other_pack_of_the_same_product_too(): void
    {
        [$product, $kg, $small] = $this->productWithTwoPacks();

        $purchase = Purchase::factory()->create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'ordered',
        ]);

        // A fresh delivery of the KILO pack at a new, confirmed rate.
        PurchaseItem::factory()->create([
            'purchase_id' => $purchase->id,
            'product_variant_id' => $kg->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_cost' => 400,
        ]);

        $purchase->receive();

        $freshKg = $kg->fresh();
        $freshSmall = $small->fresh();

        $this->assertEqualsWithDelta(400.0, (float) $freshKg->cost_price, 0.01);
        $this->assertSame(500.0, (float) $freshKg->selling_price_nepal, '400/kg at the 1.25 bulk tier');

        // The 100g pack was never in this purchase, so its OWN recorded cost
        // is untouched — only the pack that was actually received has its
        // cost updated as a fact. But its selling price must still move: the
        // kilo's new Rs400/kg alone would derive Rs60, except the small
        // pack's own stale cost (Rs110, i.e. Rs1,100/kg) floors it higher, to
        // Rs150 — the same own-cost-floor Price Review and Set Cost apply,
        // now reachable from a purchase receipt too.
        $this->assertEqualsWithDelta(110.0, (float) $freshSmall->cost_price, 0.01,
            'a receipt only knows the cost of the pack actually delivered');
        $this->assertSame(150.0, (float) $freshSmall->selling_price_nepal,
            'the small pack must be repriced along with the kilo, not left behind');
    }

    public function test_a_locked_pack_is_not_touched_by_a_sibling_receipt(): void
    {
        [$product, $kg, $small] = $this->productWithTwoPacks();
        $small->update(['manual_price_locked' => true]);

        $purchase = Purchase::factory()->create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'ordered',
        ]);

        PurchaseItem::factory()->create([
            'purchase_id' => $purchase->id,
            'product_variant_id' => $kg->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_cost' => 400,
        ]);

        $purchase->receive();

        $this->assertSame(45.0, (float) $small->fresh()->selling_price_nepal,
            'a manual lock must survive a sibling pack being received at a new cost');
    }
}

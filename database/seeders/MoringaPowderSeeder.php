<?php

/**
 * MoringaPowderSeeder — adds Moringa Powder as a single-pack premium line
 * under "Seeds & Superfoods".
 *
 * Only one pack exists (100g), and both its retail and wholesale price are
 * deliberate business decisions rather than derived from the standard tiers:
 * cost is Rs1,000/kg (Rs100 for the 100g pack), retail is fixed at Rs250 —
 * a wide premium unrelated to the usual 25–30% markup — and wholesale is
 * fixed at Rs175, a flat Rs75 dealer discount off that retail price rather
 * than the 13%-over-cost formula (which would land far lower, at odds with
 * the premium positioning).
 *
 * manual_price_locked protects the retail price from PackPricing's tiers;
 * wholesale_price is the same idea for the dealer price — see
 * PricingService::getWholesalePrice().
 *
 * Uses firstOrCreate for the product → safe to re-run without duplicating.
 * Variant pricing uses updateOrCreate keyed by SKU.
 */

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class MoringaPowderSeeder extends Seeder
{
    private const COST_PER_KG = 1000.0;

    private const PACK_GRAMS = 100;

    private const RETAIL_PRICE = 250.0;

    private const WHOLESALE_PRICE = 175.0;

    public function run(): void
    {
        $orgId = Organization::where('slug', 'rishipath')->firstOrFail()->id;

        $category = Category::firstOrCreate(
            ['organization_id' => $orgId, 'name' => 'Seeds & Superfoods'],
            ['active' => true, 'slug' => 'seeds-superfoods']
        );

        $product = Product::firstOrCreate(
            ['organization_id' => $orgId, 'name' => 'Moringa Powder'],
            [
                'category_id' => $category->id,
                'sku' => 'PC-MORINGA',
                'name_nepali' => 'सहजनको धुलो',
                'description' => 'Moringa leaf powder — single-pack premium line.',
                'product_type' => 'others',
                'unit_type' => 'weight',
                'has_variants' => true,
                'active' => true,
            ]
        );

        // ProductCatalogSeeder (runs earlier in DatabaseSeeder) deactivates
        // every product outside its own rate list — reactivate so a
        // production `db:seed` doesn't hide this product after its first
        // deploy.
        if (! $product->active) {
            $product->update(['active' => true]);
        }

        $costPrice = round(self::COST_PER_KG * self::PACK_GRAMS / 1000, 2);

        $variant = ProductVariant::firstOrNew(['sku' => 'PC-MORINGA-100GMS']);
        $variant->product_id = $product->id;
        $variant->pack_size = (float) self::PACK_GRAMS;
        $variant->unit = 'GMS';
        $variant->cost_price = $costPrice;
        $variant->active = true;

        // Both prices are deliberate overrides — set only once, so a manual
        // adjustment made later in the admin panel survives a reseed.
        if (! $variant->exists) {
            $variant->manual_price_locked = true;
            $variant->selling_price_nepal = self::RETAIL_PRICE;
            $variant->base_price = self::RETAIL_PRICE;
            $variant->mrp_india = self::RETAIL_PRICE;
            $variant->wholesale_price = self::WHOLESALE_PRICE;
        }

        $variant->save();

        $this->command->info(sprintf(
            '✅ Moringa Powder ready (product_id=%d, 100g: cost रू%.2f, retail रू%.0f, wholesale रू%.0f)',
            $product->id,
            $variant->cost_price,
            $variant->fresh()->selling_price_nepal,
            $variant->fresh()->wholesale_price,
        ));
    }
}

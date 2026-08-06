<?php

/**
 * MultaniMittiSeeder — adds Multani Mitti (Fuller's earth) under a new
 * "Personal Care" category, since it doesn't fit the spice/dry-fruit lines.
 *
 * Single enhanced-packaging SKU: 200g pack, CP रू250/kg. Priced from a flat
 * रू/kg target via PackPricing, the same formula the whole catalogue uses —
 * see TARGET_PER_KG below for the number and why.
 *
 * Uses firstOrCreate for the product → safe to re-run without duplicating it.
 * Variant pricing uses updateOrCreate keyed by SKU so a price change here
 * always takes effect on reseed, and respects manual_price_locked so a
 * deliberate override made in Price Review survives reseeding too.
 */

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PackPricing;
use Illuminate\Database\Seeder;

class MultaniMittiSeeder extends Seeder
{
    private const COST_PER_KG = 250.0;

    private const PACK_GRAMS = 200;

    /**
     * Flat target retail price per kg — pending confirmation, see the
     * conversation this landed in. At Rs250/kg cost this is a 12x (1100%)
     * markup, the highest in the catalogue by a wide margin; update this one
     * constant once the number is confirmed.
     */
    private const TARGET_PER_KG = 3000.0;

    public function run(): void
    {
        $orgId = Organization::where('slug', 'rishipath')->firstOrFail()->id;

        $category = Category::firstOrCreate(
            ['organization_id' => $orgId, 'name' => 'Personal Care'],
            ['active' => true, 'slug' => 'personal-care']
        );

        $product = Product::firstOrCreate(
            ['organization_id' => $orgId, 'name' => 'Multani Mitti'],
            [
                'category_id' => $category->id,
                'sku' => 'PC-MM',
                'name_nepali' => 'मुलतानी माटो',
                'name_hindi' => 'Multani Mitti',
                'description' => "Fuller's earth - enhanced packaging line.",
                'product_type' => 'others',
                'unit_type' => 'weight',
                'has_variants' => true,
                'active' => true,
            ]
        );

        // ProductCatalogSeeder (runs earlier in DatabaseSeeder) deactivates
        // every product outside its own rate list - reactivate so a
        // production `db:seed` (never migrate:fresh) doesn't hide this
        // product after its first deploy.
        if (! $product->active) {
            $product->update(['active' => true]);
        }

        $markup = self::TARGET_PER_KG / self::COST_PER_KG;
        $product->retail_markup = round($markup, 2);
        $product->save();

        // PASS 1 — structure and cost.
        $variant = ProductVariant::firstOrNew(['sku' => 'PC-MM-200GMS']);
        $variant->product_id = $product->id;
        $variant->pack_size = (float) self::PACK_GRAMS;
        $variant->unit = 'GMS';
        $variant->cost_price = round(self::COST_PER_KG * self::PACK_GRAMS / 1000, 2);
        $variant->active = true;
        $variant->save();

        // PASS 2 — derive the price via PackPricing::previewProduct(), the
        // same call every other seeder makes, so a manual lock or (if this
        // product ever earns other, cheaper pack sizes) the staple-protection
        // floor are respected here too rather than reimplemented by hand.
        $preview = PackPricing::previewProduct($product->fresh('variants'), $markup, allowRises: true);
        $entry = $preview[$variant->id] ?? null;

        if ($entry && $entry['derived'] !== null) {
            $variant->mrp_india = $entry['derived'];
            $variant->base_price = $entry['derived'];
            $variant->selling_price_nepal = $entry['derived'];
            $variant->save();
        }

        $this->command->info(sprintf(
            '✅ Multani Mitti ready (product_id=%d, CP रू%.0f/kg → रू%.2f/200g cost, sells रू%.0f, target रू%.0f/kg)',
            $product->id,
            self::COST_PER_KG,
            $variant->cost_price,
            $variant->fresh()->selling_price_nepal,
            self::TARGET_PER_KG
        ));
    }
}

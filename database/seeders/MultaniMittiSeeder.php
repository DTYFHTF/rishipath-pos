<?php

/**
 * MultaniMittiSeeder — adds Multani Mitti (Fuller's earth) under a new
 * "Personal Care" category, since it doesn't fit the spice/dry-fruit lines.
 *
 * Single enhanced-packaging SKU: 200g pack, CP रू250/kg (रू50 material cost
 * for the pack) sold at रू150 - a deliberately higher margin reflecting the
 * upgraded packaging, not the standard spice markup rules.
 *
 * Uses firstOrCreate for the product → safe to re-run without duplicating it.
 * Variant pricing uses updateOrCreate keyed by SKU so a price change here
 * always takes effect on reseed.
 */

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class MultaniMittiSeeder extends Seeder
{
    private const COST_PER_KG = 250.0;

    private const PACK_GRAMS = 200;

    private const SELL_PRICE = 150.0;

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

        $cost = round(self::COST_PER_KG * self::PACK_GRAMS / 1000, 2);

        ProductVariant::updateOrCreate(
            ['sku' => 'PC-MM-200GMS'],
            [
                'product_id' => $product->id,
                'pack_size' => (float) self::PACK_GRAMS,
                'unit' => 'GMS',
                'cost_price' => $cost,
                'mrp_india' => self::SELL_PRICE,
                'base_price' => self::SELL_PRICE,
                'selling_price_nepal' => self::SELL_PRICE,
                'active' => true,
            ]
        );

        $this->command->info(sprintf(
            '✅ Multani Mitti ready (product_id=%d, CP रू%.0f/kg → रू%.2f/200g, sells रू%.0f)',
            $product->id,
            self::COST_PER_KG,
            $cost,
            self::SELL_PRICE
        ));
    }
}

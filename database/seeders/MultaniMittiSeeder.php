<?php

/**
 * MultaniMittiSeeder — adds Multani Mitti (Fuller's earth) under a new
 * "Personal Care" category, since it doesn't fit the spice/dry-fruit lines.
 *
 * Single enhanced-packaging SKU: 200g pack, CP रू250/kg. Retail and wholesale
 * are both flat, deliberately-set numbers rather than derived from a per-kg
 * target — the original 3000/kg target (a 12x markup, by far the highest in
 * the catalogue) was walked back; see RETAIL_PRICE/WHOLESALE_PRICE below for
 * the current numbers.
 *
 * Uses firstOrCreate for the product → safe to re-run without duplicating it.
 * The variant is locked (manual_price_locked) so these are the numbers
 * actually charged rather than something a size-tier formula would derive —
 * there's only one pack, so the formula's per-pack tiering adds nothing here.
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

    private const RETAIL_PRICE = 150.0;

    private const WHOLESALE_PRICE = 110.0;

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

        // Informational only (the price itself is locked, not derived from
        // this) — the implied per-kg rate at today's numbers, so Price
        // Review's headline figure isn't left showing the old 12x target.
        $product->retail_markup = round((self::RETAIL_PRICE / (self::PACK_GRAMS / 1000)) / self::COST_PER_KG, 2);
        $product->save();

        $variant = ProductVariant::firstOrNew(['sku' => 'PC-MM-200GMS']);
        $variant->product_id = $product->id;
        $variant->pack_size = (float) self::PACK_GRAMS;
        $variant->unit = 'GMS';
        $variant->cost_price = round(self::COST_PER_KG * self::PACK_GRAMS / 1000, 2);
        $variant->active = true;

        // Gate on the lock, not on whether the row is new: this product
        // existed before with different (formula-derived, unlocked) numbers,
        // and gating on "new row" alone would leave that old price in place
        // forever instead of migrating it once to the new flat price. Once
        // set, manual_price_locked protects it from every later reseed,
        // including this seeder's own, the same way a price set by hand in
        // Price Review would be.
        if (! $variant->manual_price_locked) {
            $variant->manual_price_locked = true;
            $variant->selling_price_nepal = self::RETAIL_PRICE;
            $variant->base_price = self::RETAIL_PRICE;
            $variant->mrp_india = self::RETAIL_PRICE;
            $variant->wholesale_price = self::WHOLESALE_PRICE;
        }

        $variant->save();

        $this->command->info(sprintf(
            '✅ Multani Mitti ready (product_id=%d, 200g: cost रू%.2f, retail रू%.0f, wholesale रू%.0f)',
            $product->id,
            $variant->cost_price,
            $variant->fresh()->selling_price_nepal,
            $variant->fresh()->wholesale_price,
        ));
    }
}

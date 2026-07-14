<?php

/**
 * JeeraPowderSeeder — adds Jeera Powder if it does not already exist.
 *
 * Uses firstOrCreate for the product → safe to re-run without duplicating it.
 * Variant pricing uses updateOrCreate keyed by SKU so a CP change here always
 * takes effect on reseed, computed via PricingService's standard markup-by-
 * pack-size rules (same rules the rest of the weight-based catalog uses).
 *
 * CP = 520 / kg.
 */

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PricingService;
use Illuminate\Database\Seeder;

class JeeraPowderSeeder extends Seeder
{
    private const ORG_ID = 1;

    private const COST_PER_KG = 520.0;

    public function run(): void
    {
        $category = Category::firstOrCreate(
            ['organization_id' => self::ORG_ID, 'name' => 'Spice Powders'],
            ['active' => true, 'slug' => \Illuminate\Support\Str::slug('Spice Powders')]
        );

        [$product, $created] = $this->firstOrCreateProduct($category->id);

        // ProductCatalogSeeder (runs earlier in DatabaseSeeder) deactivates
        // every product outside its own rate list, and Jeera Powder isn't in
        // it - reactivate so a `db:seed` on top of an already-seeded database
        // (i.e. production deploys, which never run migrate:fresh) doesn't
        // leave this product permanently hidden after its first deploy.
        if (! $product->active) {
            $product->update(['active' => true]);
        }

        $this->command->info(($created ? '[NEW]' : '[EXISTS]')." Jeera Powder (product_id={$product->id})");

        $packs = [20, 50, 100, 200, 500, 1000];

        $variantsUpserted = 0;
        foreach ($packs as $grams) {
            if ($grams === 1000) {
                $packSize = 1.000;
                $unit = 'KG';
                $sfx = '1KG';
            } else {
                $packSize = (float) $grams;
                $unit = 'GMS';
                $sfx = $grams.'G';
            }

            $cost = round(self::COST_PER_KG * $grams / 1000, 2);
            $suggested = PricingService::suggestVariantPrices($cost, $packSize, $unit);

            $sku = 'SHD-'.$product->id.'-'.$sfx;

            ProductVariant::updateOrCreate(
                ['sku' => $sku],
                [
                    'product_id' => $product->id,
                    'pack_size' => $packSize,
                    'unit' => $unit,
                    'cost_price' => $cost,
                    'mrp_india' => $suggested['mrp_india'],
                    'base_price' => $suggested['base_price'],
                    'selling_price_nepal' => $suggested['selling_price_nepal'],
                    'active' => true,
                ]
            );

            $variantsUpserted++;
        }

        $this->command->info("  Variants upserted: {$variantsUpserted} / ".count($packs).' (CP रू'.self::COST_PER_KG.'/kg)');
    }

    private function firstOrCreateProduct(int $categoryId): array
    {
        $existing = Product::where('organization_id', self::ORG_ID)
            ->where('name', 'Jeera Powder')
            ->first();

        if ($existing) {
            return [$existing, false];
        }

        $product = Product::create([
            'organization_id' => self::ORG_ID,
            'category_id' => $categoryId,
            'sku' => $this->uniqueSku('Jeera Powder'),
            'name' => 'Jeera Powder',
            'name_nepali' => 'जिरा पाउडर',
            'name_romanized' => 'Jeera Powder',
            'product_type' => 'others',
            'unit_type' => 'weight',
            'has_variants' => true,
            'requires_batch' => false,
            'requires_expiry' => false,
            'tax_category' => 'standard',
            'active' => true,
        ]);

        return [$product, true];
    }

    private function uniqueSku(string $name): string
    {
        $words = array_values(array_filter(preg_split('/\s+/', preg_replace('/[^a-zA-Z0-9 ]/', '', $name))));
        $abbr = '';
        foreach ($words as $w) {
            $abbr .= strtoupper(substr($w, 0, 2));
            if (strlen($abbr) >= 6) {
                break;
            }
        }
        $abbr = substr($abbr, 0, 6);
        $base = 'SHD-'.$abbr;
        $cand = $base;
        $i = 1;
        while (Product::where('sku', $cand)->exists()) {
            $cand = $base.$i++;
        }

        return $cand;
    }
}

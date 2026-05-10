<?php

/**
 * JeeraPowderSeeder — adds Jeera Powder if it does not already exist.
 *
 * Uses firstOrCreate for product AND variants → safe to re-run,
 * will never overwrite manually edited records.
 *
 * CP = 620 / kg.  MRP derived from same markup ratios as other spice powders
 * (Turmeric Powder CP=450 → MRP scale proportionally to CP=620).
 */

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class JeeraPowderSeeder extends Seeder
{
    private const ORG_ID = 1;

    public function run(): void
    {
        $category = Category::firstOrCreate(
            ['organization_id' => self::ORG_ID, 'name' => 'Spice Powders'],
            ['active' => true]
        );

        [$product, $created] = $this->firstOrCreateProduct($category->id);

        $this->command->info(($created ? '[NEW]' : '[EXISTS]') . " Jeera Powder (product_id={$product->id})");

        $packs = [
            // grams => [mrp, cost]
            20  => [35,  12],
            50  => [55,  31],
            100 => [95,  62],
            200 => [180, 124],
            500 => [415, 310],
            1000 => [775, 620],
        ];

        $variantsCreated = 0;
        foreach ($packs as $grams => [$mrp, $cost]) {
            if ($grams === 1000) {
                $packSize = 1.000;
                $unit     = 'KG';
                $sfx      = '1KG';
            } else {
                $packSize = (float) $grams;
                $unit     = 'GMS';
                $sfx      = $grams . 'G';
            }

            $sku = 'SHD-' . $product->id . '-' . $sfx;

            $variant = ProductVariant::firstOrCreate(
                ['sku' => $sku],
                [
                    'product_id' => $product->id,
                    'pack_size'  => $packSize,
                    'unit'       => $unit,
                    'cost_price' => $cost,
                    'mrp_india'  => $mrp,
                    'base_price' => $mrp,
                    'active'     => true,
                ]
            );

            if ($variant->wasRecentlyCreated) {
                $variantsCreated++;
            }
        }

        $this->command->info("  Variants created: {$variantsCreated} / " . count($packs));
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
            'category_id'     => $categoryId,
            'sku'             => $this->uniqueSku('Jeera Powder'),
            'name'            => 'Jeera Powder',
            'name_nepali'     => 'जिरा पाउडर',
            'name_romanized'  => 'Jeera Powder',
            'product_type'    => 'others',
            'unit_type'       => 'weight',
            'has_variants'    => true,
            'requires_batch'  => false,
            'requires_expiry' => false,
            'tax_category'    => 'standard',
            'active'          => true,
        ]);

        return [$product, true];
    }

    private function uniqueSku(string $name): string
    {
        $words = array_values(array_filter(preg_split('/\s+/', preg_replace('/[^a-zA-Z0-9 ]/', '', $name))));
        $abbr  = '';
        foreach ($words as $w) {
            $abbr .= strtoupper(substr($w, 0, 2));
            if (strlen($abbr) >= 6) break;
        }
        $abbr = substr($abbr, 0, 6);
        $base = 'SHD-' . $abbr;
        $cand = $base;
        $i    = 1;
        while (Product::where('sku', $cand)->exists()) {
            $cand = $base . $i++;
        }
        return $cand;
    }
}

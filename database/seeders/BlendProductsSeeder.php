<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductComposition;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

/**
 * Seeds the two house blends with their compositions:
 *
 *  - Garam Masala (existing product, gets its recipe attached)
 *  - Rishipeya (new spiced-tea blend product + variants)
 *
 * Also creates Black Cumin (Syah Jeera) — a Garam Masala component that was
 * missing from the catalog (rate list row 30: कालो जिरा / स्याँ, CP 600/kg).
 *
 * Idempotent: products/variants use firstOrCreate (never overwrite manual
 * edits); compositions use updateOrCreate keyed by (product_id, name).
 */
class BlendProductsSeeder extends Seeder
{
    private int $orgId;

    /**
     * Value-added processing cost per kg of finished blend, on top of the raw
     * ingredient cost: labour रू 50 + grinding रू 50 + transportation रू 50.
     */
    private const PROCESSING_PER_KG = 150.0;

    /** Target gross margin on the fully-loaded cost (material + processing). */
    private const MARGIN = 0.50;

    /** Pack sizes (grams) seeded for each house blend. */
    private const PACK_SIZES = [20, 50, 100, 250, 500, 1000];

    public function run(): void
    {
        $this->orgId = Organization::where('slug', 'rishipath')->firstOrFail()->id;

        $blackCumin = $this->seedBlackCumin();
        $this->seedGaramMasala($blackCumin);
        $this->seedRishipeya();
    }

    // ────────────────────────────────────────────────────────────────────
    private function seedBlackCumin(): Product
    {
        $category = Category::firstOrCreate(
            ['organization_id' => $this->orgId, 'name' => 'Spices'],
            ['active' => true, 'slug' => 'spices']
        );

        $product = Product::firstOrCreate(
            ['organization_id' => $this->orgId, 'name' => 'Black Cumin (Syah Jeera)'],
            [
                'category_id' => $category->id,
                'sku' => 'SP-SYJ',
                'name_nepali' => 'कालो जिरा / स्याँ',
                'name_hindi' => 'Kala Jeera',
                'description' => 'Nigella sativa — kalo jira / syah jeera. Used in Garam Masala and tempering.',
                'product_type' => 'others',
                'unit_type' => 'weight',
                'has_variants' => true,
                'active' => true,
            ]
        );

        // ProductCatalogSeeder deactivates products outside its own rate list
        // on every deploy — this product is intentionally additional, so
        // re-activate it (this seeder runs after it in DatabaseSeeder).
        if (! $product->active) {
            $product->update(['active' => true]);
        }

        // Rate list row 30: CP 600/kg, MRP 750, wholesale 680
        ProductVariant::firstOrCreate(
            ['sku' => 'SP-SYJ-1KG'],
            [
                'product_id' => $product->id,
                'pack_size' => 1.000,
                'unit' => 'KG',
                'cost_price' => 600,
                'mrp_india' => 750,
                'base_price' => 750,
                'selling_price_nepal' => 750,
                'active' => true,
            ]
        );

        // Link the KB entry so the calculator can price this ingredient
        Ingredient::where('organization_id', $this->orgId)
            ->where('code', 'SP-KALOJIRA')
            ->whereNull('product_id')
            ->update(['product_id' => $product->id]);

        $this->command->info("✅ Black Cumin (Syah Jeera) ready (product_id={$product->id})");

        return $product;
    }

    // ────────────────────────────────────────────────────────────────────
    private function seedGaramMasala(Product $blackCumin): void
    {
        $product = Product::where('organization_id', $this->orgId)
            ->where('name', 'Garam Masala')
            ->first();

        if (! $product) {
            $category = Category::firstOrCreate(
                ['organization_id' => $this->orgId, 'name' => 'Spice Powders & Masala'],
                ['active' => true, 'slug' => 'spice-powders-masala']
            );
            $product = Product::create([
                'organization_id' => $this->orgId,
                'category_id' => $category->id,
                'sku' => 'SP-GRM',
                'name' => 'Garam Masala',
                'name_nepali' => 'गरम मसला',
                'product_type' => 'others',
                'unit_type' => 'weight',
                'has_variants' => true,
                'active' => true,
            ]);
        }

        // Survives ProductCatalogSeeder's deactivation sweep (see seedBlackCumin)
        if (! $product->active) {
            $product->update(['active' => true]);
        }

        // parts by weight (grams per 245 g batch)
        $recipe = [
            ['Cinnamon (Dalchini)',        10.00, 'Cinnamon Stick'],
            ['Lavanga (Clove)',            30.00, 'Cloves'],
            ['Jeera (Cumin)',             100.00, 'Cumin Seeds'],
            ['Black Cardamom',             10.00, 'Black Cardamom'],
            ['Green Cardamom',             20.00, 'Green Cardamom Medium'],
            ['Nutmeg (Jaiphal)',            5.00, 'Nutmeg'],
            ['Mace (Javitri)',              5.00, 'Mace'],
            ['Maricha (Black Pepper)',     30.00, 'Black Pepper'],
            ['Black Cumin (Syah Jeera)',   15.00, 'Black Cumin (Syah Jeera)'],
            ['Shunthi (Dry Ginger Powder)', 10.00, 'Dry Ginger Powder'],
            ['Tejpatra (Bay Leaves)',      10.00, 'Bay Leaf (Tej Patta)'],
        ];

        $materialPerKg = $this->attachComposition($product, $recipe);
        $created = $this->priceVariants($product, 'SP-GRM', $materialPerKg);

        $this->command->info(sprintf(
            '✅ Garam Masala priced (material ≈ रू %.0f/kg + रू %.0f processing, %d%% margin, %d variant(s) upserted)',
            $materialPerKg,
            self::PROCESSING_PER_KG,
            (int) (self::MARGIN * 100),
            $created
        ));
    }

    // ────────────────────────────────────────────────────────────────────
    private function seedRishipeya(): void
    {
        $category = Category::firstOrCreate(
            ['organization_id' => $this->orgId, 'name' => 'Herbal Teas & Beverages'],
            ['active' => true, 'slug' => 'herbal-teas-beverages']
        );

        $product = Product::firstOrCreate(
            ['organization_id' => $this->orgId, 'name' => 'Rishipeya'],
            [
                'category_id' => $category->id,
                'sku' => 'HT-RSP',
                'name_nepali' => 'ऋषिपेय',
                'name_sanskrit' => 'Rishipeya',
                'description' => 'Shuddhidham signature herbal spiced-tea blend — clove, star anise, cardamom, cinnamon, bay leaf, black pepper, dry ginger and licorice.',
                'product_type' => 'others',
                'unit_type' => 'weight',
                'has_variants' => true,
                'active' => true,
            ]
        );

        // Survives ProductCatalogSeeder's deactivation sweep (see seedBlackCumin)
        if (! $product->active) {
            $product->update(['active' => true]);
        }

        // percentages of the blend (total 100)
        $recipe = [
            ['Lavanga (Clove)',        15.00, 'Cloves'],
            ['Star Anise',             15.00, 'Star Anise'],
            ['Ela (Cardamom)',         15.00, 'Green Cardamom Medium'],
            ['Dalchini (Cinnamon)',    15.00, 'Cinnamon Stick'],
            ['Tejpatra (Bay Leaf)',    15.00, 'Bay Leaf (Tej Patta)'],
            ['Maricha (Black Pepper)',  7.50, 'Black Pepper'],
            ['Shunthi (Dry Ginger)',    7.50, 'Dry Ginger'],
            ['Mulethi (Licorice)',      7.50, 'Licorice Root (Jethimadhu)'],
        ];

        $materialPerKg = $this->attachComposition($product, $recipe);
        $created = $this->priceVariants($product, 'HT-RSP', $materialPerKg);

        $this->command->info(sprintf(
            '✅ Rishipeya priced (product_id=%d, material ≈ रू %.0f/kg + रू %.0f processing, %d%% margin, %d variant(s) upserted)',
            $product->id,
            $materialPerKg,
            self::PROCESSING_PER_KG,
            (int) (self::MARGIN * 100),
            $created
        ));
    }

    // ────────────────────────────────────────────────────────────────────
    /**
     * Upsert the pack variants for a house blend, priced from the loaded cost
     * (raw material + fixed processing) at a flat target margin. A flat margin
     * across pack sizes means a 20 g pack carries the same profit rate as a
     * 50 g pack — no small-pack surcharge. Prices round up to रू 5.
     *
     * Uses updateOrCreate keyed by SKU so it overrides any flat rate-list price
     * ProductCatalogSeeder may have set for the same blend earlier in the run.
     */
    private function priceVariants(Product $product, string $skuPrefix, float $materialPerKg): int
    {
        $loadedPerKg = $materialPerKg + self::PROCESSING_PER_KG;
        $count = 0;

        foreach (self::PACK_SIZES as $grams) {
            $isKg = $grams === 1000;
            $cost = round($loadedPerKg * $grams / 1000, 2);
            $sell = (float) (ceil(($cost / (1 - self::MARGIN)) / 5) * 5);

            ProductVariant::updateOrCreate(
                ['sku' => $skuPrefix.'-'.($isKg ? '1KG' : $grams.'GMS')],
                [
                    'product_id' => $product->id,
                    'pack_size' => $isKg ? 1.000 : (float) $grams,
                    'unit' => $isKg ? 'KG' : 'GMS',
                    'cost_price' => $cost,
                    'mrp_india' => $sell,
                    'base_price' => $sell,
                    'selling_price_nepal' => $sell,
                    'active' => true,
                ]
            );

            $count++;
        }

        return $count;
    }

    // ────────────────────────────────────────────────────────────────────
    /**
     * Upsert composition rows and return the blend's raw-material cost per kg.
     */
    private function attachComposition(Product $product, array $recipe): float
    {
        $totalParts = array_sum(array_column($recipe, 1));
        $weightedCost = 0.0;

        foreach ($recipe as $sort => [$name, $parts, $componentName]) {
            $component = Product::where('organization_id', $this->orgId)
                ->where('name', $componentName)
                ->first();

            $ingredientId = $component
                ? Ingredient::where('organization_id', $this->orgId)
                    ->where('product_id', $component->id)
                    ->value('id')
                : null;

            ProductComposition::updateOrCreate(
                ['product_id' => $product->id, 'name' => $name],
                [
                    'component_product_id' => $component?->id,
                    'ingredient_id' => $ingredientId,
                    'quantity' => $parts,
                    'sort' => $sort,
                ]
            );

            if ($component && ($perKg = $this->costPerKg($component)) !== null) {
                $weightedCost += $perKg * $parts;
            } else {
                $this->command->warn("  ⚠ No cost found for component '{$componentName}' — blend cost will be underestimated.");
            }
        }

        return $totalParts > 0 ? $weightedCost / $totalParts : 0.0;
    }

    /**
     * Cost per kg from the product's variants (prefers the largest pack).
     */
    private function costPerKg(Product $product): ?float
    {
        $variant = $product->variants()
            ->where('active', true)
            ->where('cost_price', '>', 0)
            ->get()
            ->sortByDesc(fn ($v) => $this->packKg($v))
            ->first();

        if (! $variant) {
            return null;
        }

        $kg = $this->packKg($variant);

        return $kg > 0 ? (float) $variant->cost_price / $kg : null;
    }

    private function packKg(ProductVariant $variant): float
    {
        return strtoupper($variant->unit ?? 'GMS') === 'KG'
            ? (float) $variant->pack_size
            : (float) $variant->pack_size / 1000;
    }
}

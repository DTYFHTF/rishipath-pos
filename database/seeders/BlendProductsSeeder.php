<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductComposition;
use App\Models\ProductVariant;
use App\Services\PackPricing;
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
 * Pricing: each blend has its own flat रू/kg target (Garam Masala रू2000,
 * Rishipeya रू3000 — a deliberately premium position, not derived from Garam
 * Masala's margin). Every pack size is then priced from that one kilo figure
 * via PackPricing::packPrice(), the same formula every other product in the
 * catalog uses — so a 20 g pack of either blend carries the same modest
 * packet fee as a 20 g pack of anything else, not a bespoke calculation that
 * could quietly drift out of step with the rest of the catalogue.
 *
 * Idempotent: products/variants use firstOrCreate (never overwrite manual
 * edits); pack-variant pricing uses updateOrCreate keyed by SKU so price
 * changes here always take effect on reseed; compositions use updateOrCreate
 * keyed by (product_id, name).
 */
class BlendProductsSeeder extends Seeder
{
    private int $orgId;

    /**
     * Value-added processing cost per kg of finished blend, on top of the raw
     * ingredient cost: labour रू 50 + grinding रू 50 + transportation रू 50.
     */
    private const PROCESSING_PER_KG = 150.0;

    /** Garam Masala's flat target retail price per kg. */
    private const GARAM_MASALA_TARGET_PER_KG = 2000.0;

    /**
     * Rishipeya's flat target retail price per kg — a premium position on its
     * own merits (signature blend, nothing to price-compare against), not a
     * markup reused from Garam Masala.
     */
    private const RISHIPEYA_TARGET_PER_KG = 3000.0;

    /** Pack sizes (grams) seeded for each house blend. */
    private const PACK_SIZES = [20, 50, 100, 250, 500, 1000];

    public function run(): void
    {
        $this->orgId = Organization::where('slug', 'rishipath')->firstOrFail()->id;

        // Every component a recipe names must exist as a priced product before the
        // blends are costed - attachComposition() only warns when one is missing
        // and then treats it as free, silently under-pricing the blend.
        $blackCumin = $this->seedComponentProduct(
            name: 'Black Cumin (Syah Jeera)',
            sku: 'SP-SYJ',
            costPerKg: 600,
            mrp: 750,
            nepali: 'कालो जिरा / स्याँ',
            hindi: 'Kala Jeera',
            description: 'Nigella sativa — kalo jira / syah jeera. Used in Garam Masala and tempering.',
            ingredientCode: 'SP-KALOJIRA',
        );

        $this->seedComponentProduct(
            name: 'Bay Leaf (Tej Patta)',
            sku: 'SP-TEJ',
            costPerKg: 300,
            mrp: 375,
            nepali: 'तेजपात',
            hindi: 'तेज पत्ता',
            description: 'Dried bay leaf (tejpat). Aromatic and slightly sweet; used whole in Garam Masala, Rishipeya, pulao and slow-cooked gravies.',
            ingredientCode: 'SP-BAYLEAF',
        );

        $this->seedComponentProduct(
            name: 'Licorice Root (Jethimadhu)',
            sku: 'SP-JTM',
            costPerKg: 1100,
            mrp: 1375,
            nepali: 'जेठीमधु',
            hindi: 'मुलेठी',
            description: 'Dried licorice root (jethimadhu / mulethi). Naturally sweet and soothing; the finishing note in Rishipeya.',
            ingredientCode: 'SP-LICORICE',
        );

        $this->seedGaramMasala($blackCumin);
        $this->seedRishipeya();
    }

    // ────────────────────────────────────────────────────────────────────
    /**
     * Create (or re-activate) a blend component that the May 2026 rate list does
     * not carry as a line of its own.
     *
     * Each gets a single 1 kg pack. MRP is the rate list's own floor markup of
     * 1.25x cost - the median and minimum ratio across all 77 of its priced
     * products - so these sit consistently alongside the rest of the catalogue.
     *
     * firstOrCreate throughout: a later manual price correction in the admin
     * panel must survive the next deploy rather than being reset here.
     */
    private function seedComponentProduct(
        string $name,
        string $sku,
        float $costPerKg,
        float $mrp,
        ?string $nepali,
        ?string $hindi,
        string $description,
        ?string $ingredientCode = null,
    ): Product {
        $category = Category::firstOrCreate(
            ['organization_id' => $this->orgId, 'name' => 'Spices'],
            ['active' => true, 'slug' => 'spices']
        );

        $product = Product::firstOrCreate(
            ['organization_id' => $this->orgId, 'name' => $name],
            [
                'category_id' => $category->id,
                'sku' => $sku,
                'name_nepali' => $nepali,
                'name_hindi' => $hindi,
                'description' => $description,
                'product_type' => 'others',
                'unit_type' => 'weight',
                'has_variants' => true,
                'active' => true,
            ]
        );

        // ProductCatalogSeeder deactivates products outside its own rate list
        // on every deploy — these are intentionally additional, so re-activate
        // them (this seeder runs after it in DatabaseSeeder).
        if (! $product->active) {
            $product->update(['active' => true]);
        }

        ProductVariant::firstOrCreate(
            ['sku' => $sku.'-1KG'],
            [
                'product_id' => $product->id,
                'pack_size' => 1.000,
                'unit' => 'KG',
                'cost_price' => $costPerKg,
                'mrp_india' => $mrp,
                'base_price' => $mrp,
                'selling_price_nepal' => $mrp,
                'active' => true,
            ]
        );

        // Link the KB entry so the price calculator can cost this ingredient
        if ($ingredientCode) {
            Ingredient::where('organization_id', $this->orgId)
                ->where('code', $ingredientCode)
                ->whereNull('product_id')
                ->update(['product_id' => $product->id]);
        }

        $this->command->info("✅ {$name} ready (product_id={$product->id}, CP रू{$costPerKg}/kg)");

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
        $created = $this->priceToTarget($product, 'SP-GRM', $materialPerKg, self::GARAM_MASALA_TARGET_PER_KG);

        $this->command->info(sprintf(
            '✅ Garam Masala priced (material ≈ रू %.0f/kg + रू %.0f processing → रू %.0f/kg, %d variant(s) upserted)',
            $materialPerKg,
            self::PROCESSING_PER_KG,
            self::GARAM_MASALA_TARGET_PER_KG,
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
        $created = $this->priceToTarget($product, 'HT-RSP', $materialPerKg, self::RISHIPEYA_TARGET_PER_KG);

        $this->command->info(sprintf(
            '✅ Rishipeya priced (product_id=%d, material ≈ रू %.0f/kg + रू %.0f processing → रू %.0f/kg, %d variant(s) upserted)',
            $product->id,
            $materialPerKg,
            self::PROCESSING_PER_KG,
            self::RISHIPEYA_TARGET_PER_KG,
            $created
        ));
    }

    // ────────────────────────────────────────────────────────────────────
    /**
     * Upsert the pack variants for a house blend, priced to land its 1 kg pack
     * on a flat रू/kg target, with every smaller pack derived from that same
     * kilo figure via PackPricing::packPrice() — the one formula the whole
     * catalogue uses, so a blend's small packs get the same modest packet fee
     * as everything else instead of a bespoke calculation of their own.
     *
     * The markup needed to hit the target is solved from the *unrounded*
     * loaded cost and stored on the product as retail_markup (rounded to 2dp,
     * PackPricing's own precision) so PriceReview and the POS wholesale toggle
     * read the same figure between reseeds. The 1 kg price itself is computed
     * from the unrounded markup so it lands exactly on target rather than
     * drifting by a rounding step.
     */
    private function priceToTarget(Product $product, string $skuPrefix, float $materialPerKg, float $targetPerKg): int
    {
        // Rounded once, up front, to 2dp — the same precision the cost_price
        // column stores. previewProduct() re-derives cost per kg later by
        // reading that column back, so solving the markup against anything
        // more precise than what will actually be stored re-introduces the
        // exact float-rounding sensitivity this design is meant to avoid: a
        // markup solved against an unrounded cost, then applied to the
        // rounded one, can land a hair above a multiple of 5 and get pushed
        // a whole Rs5 higher by the final round-up.
        $loadedPerKg = round($materialPerKg + self::PROCESSING_PER_KG, 2);
        $exactMarkup = $targetPerKg / $loadedPerKg;

        $product->retail_markup = round($exactMarkup, 2);
        $product->save();

        // PASS 1 — structure and cost.
        $skus = [];

        foreach (self::PACK_SIZES as $grams) {
            $isKg = $grams === 1000;
            $sku = $skuPrefix.'-'.($isKg ? '1KG' : $grams.'GMS');
            $skus[] = $sku;

            $variant = ProductVariant::firstOrNew(['sku' => $sku]);
            $variant->product_id = $product->id;
            $variant->pack_size = $isKg ? 1.000 : (float) $grams;
            $variant->unit = $isKg ? 'KG' : 'GMS';
            $variant->cost_price = round($loadedPerKg * $grams / 1000, 2);
            $variant->active = true;
            $variant->save();
        }

        // PASS 2 — derive every price via PackPricing::previewProduct(), using
        // the unrounded markup so the 1kg pack lands exactly on target rather
        // than drifting by a rounding step. Going through previewProduct()
        // rather than calling packPrice() directly also means a manually
        // locked price is left alone, matching every other seeder.
        $count = 0;

        foreach (PackPricing::previewProduct($product->fresh('variants'), $exactMarkup, allowRises: true) as $entry) {
            if ($entry['derived'] === null || ! in_array($entry['variant']->sku, $skus, true)) {
                continue;
            }

            $variant = $entry['variant'];
            $variant->mrp_india = $entry['derived'];
            $variant->base_price = $entry['derived'];
            $variant->selling_price_nepal = $entry['derived'];
            $variant->save();
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

<?php

/**
 * ProductCatalogSeeder — May 2026 Rate List
 *
 * - Creates 9 categories
 * - Upserts 74 products (73 × 6 pack sizes + Saffron 1 g special)
 * - Preserves image_url on existing matched products
 * - Deactivates all products NOT in the CSV
 *
 * packs key = grams (1000 = 1 kg); the value is READ but no longer used as the
 * price — see the note above the pricing loop in seedProduct() for why.
 */

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PackPricing;
use Illuminate\Database\Seeder;

class ProductCatalogSeeder extends Seeder
{
    private const ORG_ID = 1;

    /**
     * Per-category baselines for the descriptive fields, overridable per product
     * via the 'shelf' / 'tax' keys in getProductData().
     *
     * shelf = shelf_life_months for an unopened pack kept dry and sealed. Whole
     * spices and salt keep far longer than ground powders (which lose volatile
     * oils) and than nuts/dry fruits (whose oils go rancid).
     *
     * tax = the tax_category enum (essential|standard|luxury). NOTE: nothing in
     * the app computes tax from this today - it is a classification shown in the
     * product form only - so these are commercial groupings, not a VAT ruling.
     * Have an accountant confirm before wiring it to any tax calculation.
     */
    private const CATEGORY_DEFAULTS = [
        'Spices' => ['shelf' => 24, 'tax' => 'standard'],
        'Spice Powders' => ['shelf' => 12, 'tax' => 'standard'],
        // 'Premium Spices' is a merchandising group, not a price tier - it holds
        // everyday cloves and cinnamon alongside saffron. Only the genuinely
        // high-value items carry a 'luxury' override in getProductDetails().
        'Premium Spices' => ['shelf' => 24, 'tax' => 'standard'],
        'Peppers & Chillis' => ['shelf' => 24, 'tax' => 'standard'],
        'Seeds & Grains' => ['shelf' => 18, 'tax' => 'essential'],
        'Dry Fruits & Nuts' => ['shelf' => 12, 'tax' => 'standard'],
        'Seeds & Superfoods' => ['shelf' => 12, 'tax' => 'standard'],
        'Sweeteners & Snacks' => ['shelf' => 12, 'tax' => 'essential'],
        'Salt' => ['shelf' => 36, 'tax' => 'essential'],
    ];

    private array $catIds = [];

    /** Descriptive detail keyed by product name; built once in run(). */
    private array $productDetails = [];

    // -------------------------------------------------------------------------
    public function run(): void
    {
        $this->command->info('=== ProductCatalogSeeder — May 2026 Rate List ===');

        $this->seedCategories();
        $this->productDetails = $this->getProductDetails();

        Product::where('organization_id', self::ORG_ID)->update(['active' => false]);
        $this->command->info('All products deactivated — re-activating CSV products…');

        foreach ($this->getProductData() as $data) {
            $this->seedProduct($data);
        }

        $active = Product::where('organization_id', self::ORG_ID)->where('active', true)->count();
        $this->command->info('');
        $this->command->info("=== Done! Active products: {$active} ===");
    }

    // -------------------------------------------------------------------------
    private function seedCategories(): void
    {
        foreach (['Spices', 'Spice Powders', 'Premium Spices', 'Peppers & Chillis',
            'Seeds & Grains', 'Dry Fruits & Nuts', 'Seeds & Superfoods',
            'Sweeteners & Snacks', 'Salt'] as $name) {
            $cat = Category::firstOrCreate(
                ['organization_id' => self::ORG_ID, 'name' => $name],
                ['active' => true, 'slug' => \Illuminate\Support\Str::slug($name)]
            );
            $cat->active = true;
            $cat->save();
            $this->catIds[$name] = $cat->id;
        }
        $this->command->info('Categories ready.');
    }

    // -------------------------------------------------------------------------
    private function seedProduct(array $d): void
    {
        $catId = $this->catIds[$d['category']];

        // Find by old name (for renaming) or current name
        $product = null;
        if (! empty($d['existing_name'])) {
            $product = Product::where('organization_id', self::ORG_ID)
                ->where('name', $d['existing_name'])->first();
        }
        if (! $product) {
            $product = Product::where('organization_id', self::ORG_ID)
                ->where('name', $d['name'])->first();
        }

        $isNew = ! $product;
        if ($isNew) {
            $product = new Product;
            $product->organization_id = self::ORG_ID;
            $product->sku = $this->generateProductSku($d['name']);
        }

        // Merge in the descriptive detail. Union (+=) rather than array_merge so a
        // price row always wins on any key it defines itself.
        $d += $this->productDetails[$d['name']] ?? [];

        $defaults = self::CATEGORY_DEFAULTS[$d['category']] ?? ['shelf' => 24, 'tax' => 'standard'];

        $product->name = $d['name'];
        $product->category_id = $catId;
        $product->name_nepali = $d['nepali'] ?? null;
        $product->name_romanized = $d['romanized'] ?? null;
        $product->name_hindi = $d['hindi'] ?? null;
        // product_type is deliberately left as 'others'. Its vocabulary is the
        // Ayurvedic dosage-form list in ProductResource (choorna/tailam/ghritam/…),
        // which has no term for a whole spice or nut - and Product::updating()
        // regenerates the SKU whenever product_type changes, via a generator with
        // no uniqueness check against the UNIQUE products.sku column. For this
        // catalog that regeneration collides on 5 SKUs across 11 products, so
        // changing it here would abort the seed run mid-deploy.
        $product->product_type = 'others';
        $product->unit_type = 'weight';
        $product->has_variants = true;
        $product->requires_batch = false;
        $product->requires_expiry = false;
        $product->tax_category = $d['tax'] ?? $defaults['tax'];
        $product->shelf_life_months = $d['shelf'] ?? $defaults['shelf'];

        // Prose fields are seeded but never clobbered: staff edit these in the
        // admin panel, and this seeder re-runs on every deploy. Structured fields
        // above are reference data, so those do get refreshed each run.
        if (blank($product->description) && ! empty($d['desc'])) {
            $product->description = $d['desc'];
        }
        if (blank($product->usage_instructions) && ! empty($d['usage'])) {
            $product->usage_instructions = $d['usage'];
        }

        $product->active = true;
        $product->save();

        // Upsert variants by SKU (matched, not deleted+recreated) so IDs — and the
        // stock_levels/product_batches/inventory_movements/purchase_items rows that
        // cascade-delete on variant removal — survive repeated seeding on every deploy.
        $cpPerKg = $d['cp'];
        $costOverrides = $d['cost_overrides'] ?? [];
        $keepSkus = [];

        // PASS 1 — structure and cost only. The $d['packs'] array's values
        // used to be read as the MRP directly: a table of ~80 products × 6
        // hand-entered numbers that could not agree with each other by
        // construction, which is why a 20g pack could carry a 150% markup
        // while its own 1kg pack carried 25%. Only the array's KEYS (which
        // pack sizes this product comes in) are used below; the price comes
        // from pass 2.
        foreach ($d['packs'] as $grams => $ignoredLegacyMrp) {
            if ($grams === 1000) {
                $packSize = 1.000;
                $unit = 'KG';
                $cost = (float) $cpPerKg;
                $sfx = '1KG';
            } elseif ($grams === 1) {
                // Special: a 1 g packet (Saffron) where cp is the cost of that
                // one packet, not a real per-kilogram rate. No special-casing
                // is needed for its PRICE below — costPerKg() infers a
                // per-kilogram rate proportionally from this single pack, the
                // same way it would from any other, and prices it correctly.
                $packSize = 1.000;
                $unit = 'GMS';
                $cost = (float) $cpPerKg;
                $sfx = '1G';
            } else {
                $packSize = (float) $grams;
                $unit = 'GMS';
                $cost = round($cpPerKg * $grams / 1000, 2);
                $sfx = $grams.'G';
            }

            if (array_key_exists($grams, $costOverrides)) {
                $cost = (float) $costOverrides[$grams];
            }

            $sku = 'SHD-'.$product->id.'-'.$sfx;
            $keepSkus[] = $sku;

            $variant = ProductVariant::firstOrNew(['sku' => $sku]);
            $variant->product_id = $product->id;
            $variant->pack_size = $packSize;
            $variant->unit = $unit;
            $variant->cost_price = $cost;
            $variant->active = true;
            $variant->save();
        }

        // PASS 2 — derive every price from the cost pass 1 just wrote, through
        // the one formula the whole catalogue shares. Going through
        // PackPricing::previewProduct() here (rather than calling
        // packPrice()/kilogramPrice() directly) matters: it is also the only
        // place that knows to leave a manually locked price alone, and to
        // never raise a pack that already sells at or below Rs20 — the
        // protection built for exactly the customers who buy the smallest
        // packets. Calling the lower-level functions directly, as an earlier
        // version of this seeder did, bypassed both and would have pushed a
        // Rs5 packet of gud to Rs10 on every single deploy.
        //
        // The product's own markup is passed (null for almost everything here),
        // NOT the headline rate: a concrete number is applied flat to every
        // pack, which would flatten away the 25%/30% size tier.
        $markup = PackPricing::explicitMarkupFor($product);

        foreach (PackPricing::previewProduct($product->fresh('variants'), $markup, allowRises: true) as $entry) {
            if ($entry['derived'] === null || ! in_array($entry['variant']->sku, $keepSkus, true)) {
                continue;
            }

            $variant = $entry['variant'];
            $variant->mrp_india = $entry['derived'];
            $variant->base_price = $entry['derived'];
            // Must be set explicitly: the live org is Nepal, so POS and the
            // price list read selling_price_nepal. The model's
            // fillSuggestedPrices hook only fills it when null, so on a
            // reseed a variant that already had a (now stale) Nepal price
            // would keep it while mrp/base updated — leaving the
            // customer-facing price wrong. Keep all three in sync.
            $variant->selling_price_nepal = $entry['derived'];
            $variant->save();
        }

        // Deactivate (don't hard-delete) variants no longer in this product's
        // pack list. A delete would throw on any variant already referenced by
        // sale_items (restrictOnDelete), aborting the whole seed run; the POS
        // and price list both hide inactive variants, so this is equivalent for
        // display while preserving sales history.
        ProductVariant::where('product_id', $product->id)
            ->whereNotIn('sku', $keepSkus)
            ->update(['active' => false]);

        $flag = $isNew ? '[NEW]' : '[UPD]';
        $mrp1 = $product->variants()->where('unit', 'KG')->value('selling_price_nepal')
            ?? $product->variants()->where('sku', $keepSkus[0] ?? null)->value('selling_price_nepal');
        $this->command->line("  {$flag} {$d['name']}  CP={$cpPerKg}  MRP(1kg)={$mrp1}  ".count($d['packs']).' variants');
    }

    // -------------------------------------------------------------------------
    private function generateProductSku(string $name): string
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

    // =========================================================================
    // PRODUCT DATA  (prices verified against Combined Rate List.csv)
    // packs: {grams => MRP for that pack}  |  1000 = 1 KG variant
    // =========================================================================
    private function getProductData(): array
    {
        return [

            // =================================================================
            // SPICES
            // =================================================================
            ['name' => 'Cumin Seeds', 'existing_name' => 'Cumin Seeds', 'nepali' => 'जिरा', 'romanized' => 'Jira', 'category' => 'Spices', 'cp' => 465,
                'packs' => [1000 => 585, 500 => 315, 200 => 135, 100 => 75, 50 => 45, 20 => 25]],

            ['name' => 'Coriander Seeds Large', 'existing_name' => 'Coriander Seeds', 'nepali' => 'धनियाँ', 'romanized' => 'Dhaniya', 'category' => 'Spices', 'cp' => 280,
                'packs' => [1000 => 350, 500 => 190, 200 => 85, 100 => 45, 50 => 25, 20 => 15]],

            ['name' => 'Coriander Seeds Small', 'nepali' => 'धनियाँ', 'romanized' => 'Dhaniya', 'category' => 'Spices', 'cp' => 270,
                'packs' => [1000 => 340, 500 => 185, 200 => 80, 100 => 45, 50 => 25, 20 => 15]],

            ['name' => 'Star Anise', 'existing_name' => 'Star Anise', 'nepali' => 'बडियान', 'romanized' => 'Badiya', 'category' => 'Spices', 'cp' => 900,
                'packs' => [1000 => 1125, 500 => 600, 200 => 260, 100 => 135, 50 => 80, 20 => 45]],

            ['name' => 'Asafoetida', 'existing_name' => 'Asafoetida (Hing)', 'nepali' => 'हिङ', 'romanized' => 'Hing', 'category' => 'Spices', 'cp' => 1575,
                'packs' => [1000 => 1970, 500 => 1045, 200 => 455, 100 => 240, 50 => 140, 20 => 80],
                'cost_overrides' => [100 => 160, 20 => 40]],

            ['name' => 'Dried Fenugreek Leaves', 'nepali' => 'मेथी पात', 'romanized' => 'Methi Paat', 'category' => 'Spices', 'cp' => 400,
                'packs' => [1000 => 500, 500 => 265, 200 => 115, 100 => 60, 50 => 35, 20 => 20]],

            ['name' => 'Dry Ginger', 'nepali' => 'सुठो', 'romanized' => 'Sutho', 'category' => 'Spices', 'cp' => 600,
                'packs' => [1000 => 750, 500 => 400, 200 => 175, 100 => 90, 50 => 55, 20 => 30]],

            ['name' => 'Whole Turmeric Pieces', 'nepali' => 'कच्चो बेसार', 'romanized' => 'Kacho Besar', 'category' => 'Spices', 'cp' => 320,
                'packs' => [1000 => 400, 500 => 215, 200 => 95, 100 => 50, 50 => 30, 20 => 20]],

            // =================================================================
            // SPICE POWDERS
            // =================================================================
            ['name' => 'Coriander Powder', 'existing_name' => 'Coriander Powder (Dhaniya Powder)', 'nepali' => 'धनियाँ पाउडर', 'romanized' => 'Dhaniya Powder', 'category' => 'Spice Powders', 'cp' => 320,
                'packs' => [1000 => 400, 500 => 215, 200 => 95, 100 => 50, 50 => 30, 20 => 20]],

            ['name' => 'Cinnamon Powder', 'existing_name' => 'Cinnamon Powder (Dalchini Powder)', 'nepali' => 'दालचिनी पाउडर', 'romanized' => 'Dalchini Powder', 'category' => 'Spice Powders', 'cp' => 550,
                'packs' => [1000 => 690, 500 => 370, 200 => 160, 100 => 85, 50 => 50, 20 => 30]],

            ['name' => 'Turmeric Powder', 'existing_name' => 'Turmeric Powder', 'nepali' => 'बेसार', 'romanized' => 'Besar', 'category' => 'Spice Powders', 'cp' => 450,
                'packs' => [1000 => 565, 500 => 300, 200 => 130, 100 => 70, 50 => 40, 20 => 25]],

            ['name' => 'Dry Ginger Powder', 'existing_name' => 'Dried Ginger Powder (Sutho)', 'nepali' => 'सुठो पाउडर', 'romanized' => 'Sutho Powder', 'category' => 'Spice Powders', 'cp' => 700,
                'packs' => [1000 => 875, 500 => 465, 200 => 205, 100 => 105, 50 => 65, 20 => 35]],

            ['name' => 'Red Chilli Powder', 'existing_name' => 'Mirchi Dhulo', 'nepali' => 'रातो मिर्ची धुलो', 'romanized' => 'Rato Mirchi Dhulo', 'category' => 'Spice Powders', 'cp' => 550,
                'packs' => [1000 => 690, 500 => 370, 200 => 165, 100 => 90, 50 => 55, 20 => 30]],

            // =================================================================
            // PREMIUM SPICES
            // =================================================================
            ['name' => 'Nutmeg', 'existing_name' => 'Nutmeg (Jaiphal)', 'nepali' => 'जायफल', 'romanized' => 'Jaiphal', 'category' => 'Premium Spices', 'cp' => 1550,
                'packs' => [1000 => 1940, 500 => 1030, 200 => 450, 100 => 235, 50 => 140, 20 => 80]],

            ['name' => 'Cloves', 'existing_name' => 'Cloves (Lwang)', 'nepali' => 'लवङ्ग', 'romanized' => 'Lwang', 'category' => 'Premium Spices', 'cp' => 1475,
                'packs' => [1000 => 1845, 500 => 980, 200 => 425, 100 => 225, 50 => 130, 20 => 75]],

            ['name' => 'Green Cardamom Medium', 'existing_name' => 'Green Cardamom (Elaichi)', 'nepali' => 'सुकमेल', 'romanized' => 'Sukumel', 'category' => 'Premium Spices', 'cp' => 4700,
                'packs' => [1000 => 5875, 500 => 3115, 200 => 1355, 100 => 705, 50 => 415, 20 => 235]],

            ['name' => 'Green Cardamom Large', 'nepali' => 'सुकमेल', 'romanized' => 'Sukumel', 'category' => 'Premium Spices', 'cp' => 5200,
                'packs' => [1000 => 6500, 500 => 3445, 200 => 1495, 100 => 780, 50 => 455, 20 => 260]],

            ['name' => 'Black Cardamom', 'existing_name' => 'Black Cardamom (Badi Elaichi)', 'nepali' => 'ठूलो सुकमेल', 'romanized' => 'Thulo Sukumel', 'category' => 'Premium Spices', 'cp' => 2800,
                'packs' => [1000 => 3500, 500 => 1855, 200 => 805, 100 => 420, 50 => 245, 20 => 140]],

            ['name' => 'Cinnamon Roll', 'existing_name' => 'Cinnamon (Dalchini)', 'nepali' => 'दालचिनी', 'romanized' => 'Dalchini', 'category' => 'Premium Spices', 'cp' => 875,
                'packs' => [1000 => 1095, 500 => 585, 200 => 255, 100 => 135, 50 => 80, 20 => 45]],

            ['name' => 'Cinnamon Stick', 'nepali' => 'दालचिनी', 'romanized' => 'Dalchini', 'category' => 'Premium Spices', 'cp' => 450,
                'packs' => [1000 => 565, 500 => 300, 200 => 130, 100 => 70, 50 => 40, 20 => 25]],

            ['name' => 'Mace', 'existing_name' => 'Mace (Javitri)', 'nepali' => 'जायपत्री', 'romanized' => 'Jaipattri', 'category' => 'Premium Spices', 'cp' => 3700,
                'packs' => [1000 => 4625, 500 => 2455, 200 => 1065, 100 => 555, 50 => 325, 20 => 185]],

            ['name' => 'Long Pepper', 'nepali' => 'पिपला', 'romanized' => 'Pipla', 'category' => 'Premium Spices', 'cp' => 1800,
                'packs' => [1000 => 2250, 500 => 1195, 200 => 520, 100 => 270, 50 => 160, 20 => 90]],

            // Saffron: sold as 1 g packets; CP=270/packet, MRP=375/packet (user-confirmed)
            ['name' => 'Saffron', 'existing_name' => 'Saffron (Kesar)', 'nepali' => 'केशर', 'romanized' => 'Keshar', 'category' => 'Premium Spices', 'cp' => 270,
                'packs' => [1 => 375]],

            // =================================================================
            // PEPPERS & CHILLIS
            // =================================================================
            ['name' => 'Black Pepper', 'existing_name' => 'Black Pepper (Whole)', 'nepali' => 'मरिच', 'romanized' => 'Marich', 'category' => 'Peppers & Chillis', 'cp' => 1450,
                'packs' => [1000 => 1815, 500 => 965, 200 => 420, 100 => 220, 50 => 130, 20 => 75]],

            ['name' => 'White Pepper', 'existing_name' => 'White Pepper', 'nepali' => 'सेतो मरिच', 'romanized' => 'Seto Marich', 'category' => 'Peppers & Chillis', 'cp' => 2300,
                'packs' => [1000 => 2875, 500 => 1525, 200 => 665, 100 => 345, 50 => 205, 20 => 115]],

            ['name' => 'Sichuan Pepper', 'existing_name' => 'Sichuan Pepper (Timur)', 'nepali' => 'टिमुर', 'romanized' => 'Timur', 'category' => 'Peppers & Chillis', 'cp' => 1200,
                'packs' => [1000 => 1500, 500 => 795, 200 => 345, 100 => 180, 50 => 105, 20 => 60]],

            ['name' => 'Red Chilli', 'existing_name' => 'Red Chilli (Whole)', 'nepali' => 'रातो खोर्सानी', 'romanized' => 'Rato Khorsani', 'category' => 'Peppers & Chillis', 'cp' => 480,
                'packs' => [1000 => 600, 500 => 320, 200 => 140, 100 => 75, 50 => 45, 20 => 25]],

            // =================================================================
            // SEEDS & GRAINS
            // =================================================================
            ['name' => 'Fenugreek Seeds', 'existing_name' => 'Fenugreek Seeds', 'nepali' => 'मेथी', 'romanized' => 'Methi', 'category' => 'Seeds & Grains', 'cp' => 150,
                'packs' => [1000 => 190, 500 => 105, 200 => 45, 100 => 25, 50 => 15, 20 => 10]],

            ['name' => 'Fennel Seeds Sweet', 'nepali' => 'सौंफ', 'romanized' => 'Saunf', 'category' => 'Seeds & Grains', 'cp' => 440,
                'packs' => [1000 => 550, 500 => 295, 200 => 130, 100 => 70, 50 => 40, 20 => 25]],

            ['name' => 'Fennel Seeds Normal', 'existing_name' => 'Fennel Seeds', 'nepali' => 'सौंफ', 'romanized' => 'Saunf', 'category' => 'Seeds & Grains', 'cp' => 300,
                'packs' => [1000 => 375, 500 => 200, 200 => 90, 100 => 45, 50 => 30, 20 => 15]],

            ['name' => 'Fennel Seeds Normal Local', 'nepali' => 'सौंफ', 'romanized' => 'Saunf', 'category' => 'Seeds & Grains', 'cp' => 270,
                'packs' => [1000 => 340, 500 => 185, 200 => 80, 100 => 45, 50 => 25, 20 => 15]],

            ['name' => 'Ajwain Premium', 'nepali' => 'जवानो', 'romanized' => 'Jwano', 'category' => 'Seeds & Grains', 'cp' => 380,
                'packs' => [1000 => 475, 500 => 255, 200 => 110, 100 => 60, 50 => 35, 20 => 20]],

            ['name' => 'Ajwain Normal', 'existing_name' => 'Ajwain (Carom Seeds)', 'nepali' => 'जवानो', 'romanized' => 'Jwano', 'category' => 'Seeds & Grains', 'cp' => 275,
                'packs' => [1000 => 345, 500 => 185, 200 => 80, 100 => 45, 50 => 25, 20 => 15]],

            ['name' => 'Black Mustard Seeds', 'existing_name' => 'Mustard Seeds (Black)', 'nepali' => 'कालो राई', 'romanized' => 'Kalo Rai', 'category' => 'Seeds & Grains', 'cp' => 215,
                'packs' => [1000 => 270, 500 => 145, 200 => 65, 100 => 35, 50 => 20, 20 => 15]],

            ['name' => 'Mustard Seeds', 'nepali' => 'राई', 'romanized' => 'Rai', 'category' => 'Seeds & Grains', 'cp' => 215,
                'packs' => [1000 => 270, 500 => 145, 200 => 65, 100 => 35, 50 => 20, 20 => 15]],

            ['name' => 'Yellow Mustard', 'existing_name' => 'Mustard (Yellow)', 'nepali' => 'पहेँलो राई', 'romanized' => 'Pahelo Rai', 'category' => 'Seeds & Grains', 'cp' => 230,
                'packs' => [1000 => 290, 500 => 155, 200 => 70, 100 => 35, 50 => 25, 20 => 15]],

            ['name' => 'Brown Sesame Seeds', 'nepali' => 'खैरो तिल', 'romanized' => 'Khairo Til', 'category' => 'Seeds & Grains', 'cp' => 270,
                'packs' => [1000 => 340, 500 => 185, 200 => 80, 100 => 45, 50 => 25, 20 => 15]],

            ['name' => 'Black Sesame Seeds', 'existing_name' => 'Black Sesame Seeds', 'nepali' => 'कालो तिल', 'romanized' => 'Kalo Til', 'category' => 'Seeds & Grains', 'cp' => 320,
                'packs' => [1000 => 400, 500 => 215, 200 => 95, 100 => 50, 50 => 30, 20 => 20]],

            ['name' => 'Black Mix Sesame Seeds', 'nepali' => 'मिश्रित तिल', 'romanized' => 'Mishrit Til', 'category' => 'Seeds & Grains', 'cp' => 320,
                'packs' => [1000 => 400, 500 => 215, 200 => 95, 100 => 50, 50 => 30, 20 => 20]],

            ['name' => 'White Sesame Seeds', 'existing_name' => 'Sesame Seeds (White)', 'nepali' => 'सेतो तिल', 'romanized' => 'Seto Til', 'category' => 'Seeds & Grains', 'cp' => 270,
                'packs' => [1000 => 340, 500 => 185, 200 => 80, 100 => 45, 50 => 25, 20 => 15]],

            ['name' => 'Kalonji / Nigella Sativa', 'existing_name' => 'Kalonji (Nigella Seeds)', 'nepali' => 'मुग्री', 'romanized' => 'Mugri', 'category' => 'Seeds & Grains', 'cp' => 600,
                'packs' => [1000 => 750, 500 => 400, 200 => 175, 100 => 90, 50 => 55, 20 => 30]],

            ['name' => 'Garden Cress Seeds', 'existing_name' => 'Garden Cress Seeds (Halim)', 'nepali' => 'चंसुर', 'romanized' => 'Chansur', 'category' => 'Seeds & Grains', 'cp' => 300,
                'packs' => [1000 => 375, 500 => 200, 200 => 90, 100 => 45, 50 => 30, 20 => 15]],

            ['name' => 'Ban Silam Seeds', 'existing_name' => 'Silam Seeds', 'nepali' => 'बन सिलाम', 'romanized' => 'Ban Silam', 'category' => 'Seeds & Grains', 'cp' => 400,
                'packs' => [1000 => 500, 500 => 265, 200 => 115, 100 => 60, 50 => 35, 20 => 20]],

            // =================================================================
            // DRY FRUITS & NUTS
            // =================================================================
            ['name' => 'Walnut Premium', 'existing_name' => 'Walnut Premium (Okhar Premium)', 'nepali' => 'अखरोट', 'romanized' => 'Akharot', 'category' => 'Dry Fruits & Nuts', 'cp' => 660,
                'packs' => [1000 => 825, 500 => 430, 200 => 200, 100 => 100, 50 => 55, 20 => 25]],

            ['name' => 'Walnut Standard', 'existing_name' => 'Walnut Standard (Okhar Standard)', 'nepali' => 'अखरोट', 'romanized' => 'Akharot', 'category' => 'Dry Fruits & Nuts', 'cp' => 440,
                'packs' => [1000 => 550, 500 => 295, 200 => 130, 100 => 70, 50 => 40, 20 => 25]],

            ['name' => 'Walnut Kernels', 'existing_name' => 'Walnut Kernels (Okhar)', 'nepali' => 'अखरोट दाना', 'romanized' => 'Akharot Dana', 'category' => 'Dry Fruits & Nuts', 'cp' => 1240,
                'packs' => [1000 => 1550, 500 => 825, 200 => 360, 100 => 190, 50 => 110, 20 => 65]],

            ['name' => 'Cashew Premium', 'existing_name' => 'Cashew (Kaju)', 'nepali' => 'काजु', 'romanized' => 'Kaju', 'category' => 'Dry Fruits & Nuts', 'cp' => 1700,
                'packs' => [1000 => 2125, 500 => 1130, 200 => 490, 100 => 255, 50 => 150, 20 => 85]],

            ['name' => 'Cashew Standard', 'nepali' => 'काजु', 'romanized' => 'Kaju', 'category' => 'Dry Fruits & Nuts', 'cp' => 1650,
                'packs' => [1000 => 2065, 500 => 1095, 200 => 475, 100 => 250, 50 => 145, 20 => 85]],

            ['name' => 'Pistachio', 'existing_name' => 'Pistachio (Pista)', 'nepali' => 'पिस्ता', 'romanized' => 'Pista', 'category' => 'Dry Fruits & Nuts', 'cp' => 2450,
                'packs' => [1000 => 3065, 500 => 1625, 200 => 705, 100 => 370, 50 => 215, 20 => 125]],

            // Anjeer split into Premium/Standard grades. The former single
            // "Anjeer / Figs" product is renamed to Premium (its CP was closest);
            // Standard is added as a new, cheaper grade.
            ['name' => 'Anjeer Premium / Figs', 'existing_name' => 'Anjeer / Figs', 'nepali' => 'अञ्जीर', 'romanized' => 'Anjir', 'category' => 'Dry Fruits & Nuts', 'cp' => 1500,
                'packs' => [1000 => 1875, 500 => 975, 200 => 450, 100 => 225, 50 => 125, 20 => 50]],

            ['name' => 'Anjeer Standard / Figs', 'nepali' => 'अञ्जीर', 'romanized' => 'Anjir', 'category' => 'Dry Fruits & Nuts', 'cp' => 1400,
                'packs' => [1000 => 1750, 500 => 910, 200 => 420, 100 => 210, 50 => 120, 20 => 50]],

            ['name' => 'Almond', 'nepali' => 'बादाम', 'romanized' => 'Badam', 'category' => 'Dry Fruits & Nuts', 'cp' => 1600,
                'packs' => [1000 => 2000, 500 => 1065, 200 => 465, 100 => 240, 50 => 145, 20 => 80]],

            ['name' => 'Kishmish / Raisins', 'existing_name' => 'Raisins (Kishmish)', 'nepali' => 'किसमिस', 'romanized' => 'Kishmish', 'category' => 'Dry Fruits & Nuts', 'cp' => 580,
                'packs' => [1000 => 725, 500 => 385, 200 => 170, 100 => 90, 50 => 55, 20 => 30]],

            ['name' => 'Dates / Khajur', 'existing_name' => 'Dates (Khajur)', 'nepali' => 'खजुर', 'romanized' => 'Khajur', 'category' => 'Dry Fruits & Nuts', 'cp' => 350,
                'packs' => [1000 => 440, 500 => 235, 200 => 105, 100 => 55, 50 => 35, 20 => 20]],

            // Brand-neutral (was "AlKhalifa Dates Pkt" — brand keeps changing).
            // Sold only as the sealed 500 g packet: CP रू180/pkt (= रू360/kg).
            ['name' => 'Premium Dates Pkt', 'existing_name' => 'AlKhalifa Dates Pkt', 'nepali' => 'खजुर', 'romanized' => 'Khajur', 'category' => 'Dry Fruits & Nuts', 'cp' => 360,
                'packs' => [500 => 235]],

            ['name' => 'Coconut', 'existing_name' => 'Coconut', 'nepali' => 'नरिवल', 'romanized' => 'Narival', 'category' => 'Dry Fruits & Nuts', 'cp' => 780,
                'packs' => [1000 => 975, 500 => 520, 200 => 225, 100 => 120, 50 => 70, 20 => 40]],

            ['name' => 'Makhana Madhur', 'existing_name' => 'Fox Nuts Regular (Makhana Madhur)', 'nepali' => 'मखाना', 'romanized' => 'Makhana', 'category' => 'Dry Fruits & Nuts', 'cp' => 1840,
                'packs' => [1000 => 2300, 500 => 1200, 200 => 555, 100 => 280, 50 => 155, 20 => 65]],

            ['name' => 'Makhana Kanaiya', 'existing_name' => 'Fox Nuts Large (Makhana Kanaiya)', 'nepali' => 'मखाना', 'romanized' => 'Makhana', 'category' => 'Dry Fruits & Nuts', 'cp' => 1360,
                'packs' => [1000 => 1700, 500 => 885, 200 => 410, 100 => 205, 50 => 115, 20 => 45]],

            ['name' => 'Makhana Balgopal', 'nepali' => 'मखाना', 'romanized' => 'Makhana', 'category' => 'Dry Fruits & Nuts', 'cp' => 2000,
                'packs' => [1000 => 2500, 500 => 1300, 200 => 600, 100 => 300, 50 => 165, 20 => 70]],

            ['name' => 'Areca Nut', 'existing_name' => 'Areca Nut (Supari)', 'nepali' => 'सुपारी', 'romanized' => 'Supari', 'category' => 'Dry Fruits & Nuts', 'cp' => 875,
                'packs' => [1000 => 1095, 500 => 585, 200 => 255, 100 => 135, 50 => 80, 20 => 45]],

            ['name' => 'Pooja Supari', 'nepali' => 'पूजा सुपारी', 'romanized' => 'Pooja Supari', 'category' => 'Dry Fruits & Nuts', 'cp' => 1250,
                'packs' => [1000 => 1565, 500 => 830, 200 => 360, 100 => 190, 50 => 110, 20 => 65]],

            // =================================================================
            // SEEDS & SUPERFOODS
            // =================================================================
            ['name' => 'Pumpkin Seeds', 'existing_name' => 'Pumpkin Seeds', 'nepali' => 'फर्सी बिउ', 'romanized' => 'Pharsi Biu', 'category' => 'Seeds & Superfoods', 'cp' => 825,
                'packs' => [1000 => 1035, 500 => 550, 200 => 240, 100 => 125, 50 => 75, 20 => 45]],

            ['name' => 'Watermelon Seeds', 'existing_name' => 'Watermelon Seeds Large (Tarbuza Biya Thulo)', 'nepali' => 'तरबुजा बिउ', 'romanized' => 'Tarbuza Biu', 'category' => 'Seeds & Superfoods', 'cp' => 900,
                'packs' => [1000 => 1125, 500 => 600, 200 => 260, 100 => 135, 50 => 80, 20 => 45]],

            ['name' => 'Cucumber Seeds', 'nepali' => 'काक्रो बिउ', 'romanized' => 'Kakro Biu', 'category' => 'Seeds & Superfoods', 'cp' => 800,
                'packs' => [1000 => 1000, 500 => 530, 200 => 230, 100 => 120, 50 => 70, 20 => 40]],

            ['name' => 'Sunflower Seeds', 'nepali' => 'सूर्यमुखी बिउ', 'romanized' => 'Suryamukhi Biu', 'category' => 'Seeds & Superfoods', 'cp' => 400,
                'packs' => [1000 => 500, 500 => 265, 200 => 115, 100 => 60, 50 => 35, 20 => 20]],

            ['name' => 'Hemp Seeds', 'nepali' => 'भाङ बिउ', 'romanized' => 'Bhang Biu', 'category' => 'Seeds & Superfoods', 'cp' => 380,
                'packs' => [1000 => 475, 500 => 255, 200 => 110, 100 => 60, 50 => 35, 20 => 20]],

            ['name' => 'Chiya Seeds', 'existing_name' => 'Chia Seeds (Chiya Seeds)', 'nepali' => 'चिया बिउ', 'romanized' => 'Chiya Biu', 'category' => 'Seeds & Superfoods', 'cp' => 550,
                'packs' => [1000 => 690, 500 => 370, 200 => 160, 100 => 85, 50 => 50, 20 => 30]],

            ['name' => 'Sabudana Large', 'existing_name' => 'Sabudana Large (Sabudana Thulo)', 'nepali' => 'साबुदाना', 'romanized' => 'Sabudana', 'category' => 'Seeds & Superfoods', 'cp' => 160,
                'packs' => [1000 => 200, 500 => 110, 200 => 50, 100 => 25, 50 => 15, 20 => 10]],

            ['name' => 'Sabudana Small', 'existing_name' => 'Sabudana Small (Sabudana Sana)', 'nepali' => 'साबुदाना', 'romanized' => 'Sabudana', 'category' => 'Seeds & Superfoods', 'cp' => 160,
                'packs' => [1000 => 200, 500 => 110, 200 => 50, 100 => 25, 50 => 15, 20 => 10]],

            // =================================================================
            // SWEETENERS & SNACKS
            // =================================================================
            ['name' => 'Dhikka Mishri', 'nepali' => 'ढिक्का मिश्री', 'romanized' => 'Dhikka Mishri', 'category' => 'Sweeteners & Snacks', 'cp' => 160,
                'packs' => [1000 => 200, 500 => 110, 200 => 50, 100 => 25, 50 => 15, 20 => 10]],

            ['name' => 'Cutting Mishri', 'nepali' => 'कटिङ मिश्री', 'romanized' => 'Cutting Mishri', 'category' => 'Sweeteners & Snacks', 'cp' => 200,
                'packs' => [1000 => 250, 500 => 135, 200 => 60, 100 => 30, 50 => 20, 20 => 10]],

            ['name' => 'Gond', 'existing_name' => 'Gum Resin (Gond)', 'nepali' => 'गोंद', 'romanized' => 'Gond', 'category' => 'Sweeteners & Snacks', 'cp' => 470,
                'packs' => [1000 => 590, 500 => 315, 200 => 140, 100 => 75, 50 => 45, 20 => 25]],

            ['name' => 'Gud Bites', 'nepali' => 'गुड', 'romanized' => 'Gud', 'category' => 'Sweeteners & Snacks', 'cp' => 500,
                'packs' => [1000 => 625, 500 => 335, 200 => 145, 100 => 75, 50 => 45, 20 => 25]],

            ['name' => 'Gud Normal', 'nepali' => 'गुड', 'romanized' => 'Gud', 'category' => 'Sweeteners & Snacks', 'cp' => 100,
                'packs' => [1000 => 125, 500 => 70, 200 => 30, 100 => 15, 50 => 10, 20 => 5]],

            ['name' => 'Gulmeli Gud', 'existing_name' => 'Jaggery / Sugar (Gulmeli Gud Sakar)', 'nepali' => 'गुड', 'romanized' => 'Gud', 'category' => 'Sweeteners & Snacks', 'cp' => 230,
                'packs' => [1000 => 290, 500 => 155, 200 => 70, 100 => 35, 50 => 25, 20 => 15]],

            ['name' => 'Chana Fry', 'existing_name' => 'Roasted Chickpeas (Chana Fry)', 'nepali' => 'भुटेको चना', 'romanized' => 'Bhuteko Chana', 'category' => 'Sweeteners & Snacks', 'cp' => 210,
                'packs' => [1000 => 265, 500 => 145, 200 => 65, 100 => 35, 50 => 20, 20 => 15]],

            ['name' => 'Lapsi Powder', 'existing_name' => 'Lapsi Powder', 'nepali' => 'लप्सी', 'romanized' => 'Lapsi', 'category' => 'Sweeteners & Snacks', 'cp' => 450,
                'packs' => [1000 => 565, 500 => 300, 200 => 130, 100 => 70, 50 => 40, 20 => 25]],

            // =================================================================
            // SALT
            // =================================================================
            ['name' => 'Balk Salt', 'nepali' => 'बिट नुन', 'romanized' => 'Bit Nun', 'category' => 'Salt', 'cp' => 80,
                'packs' => [1000 => 100, 500 => 55, 200 => 25, 100 => 15, 50 => 10, 20 => 5]],

            ['name' => 'White Salt', 'existing_name' => 'White Salt (Seto Nun)', 'nepali' => 'सिधे नुन', 'romanized' => 'Sidhe Nun', 'category' => 'Salt', 'cp' => 80,
                'packs' => [1000 => 100, 500 => 55, 200 => 25, 100 => 15, 50 => 10, 20 => 5]],

        ]; // end return
    }

    // =========================================================================
    // DESCRIPTIVE DETAIL  (keyed by product name)
    //
    // Deliberately kept separate from getProductData() so the rate-list rows
    // above stay a clean, auditable price table. Keys here are merged in by
    // seedProduct() and never overwrite a price row's own keys.
    //
    //   hindi  → name_hindi          desc  → description (seeded once, then
    //   usage  → usage_instructions          left alone for staff to edit)
    //   tax    → tax_category override       shelf → shelf_life_months override
    //
    // 'usage' is only filled where there is real preparation guidance worth
    // printing; a generic "use as required" line on every product would just be
    // noise on the label and in the catalogue.
    // =========================================================================
    private function getProductDetails(): array
    {
        return [
            // ---- Spices ----------------------------------------------------
            'Cumin Seeds' => ['hindi' => 'जीरा',
                'desc' => 'Whole cumin seed with a warm, earthy aroma. Tempered at the start of dal, sabzi and rice dishes.',
                'usage' => 'Splutter in hot oil or ghee before adding other ingredients; dry-roast and grind for jeera powder.'],
            'Coriander Seeds Large' => ['hindi' => 'धनिया',
                'desc' => 'Large-grade coriander seed, mild and citrusy. Ground fresh for curry masala or used whole for pickling.'],
            'Coriander Seeds Small' => ['hindi' => 'धनिया',
                'desc' => 'Smaller, denser coriander seed — the everyday grinding grade for household masala.'],
            'Star Anise' => ['hindi' => 'चक्र फूल',
                'desc' => 'Eight-pointed star pod with a sweet liquorice note. A backbone of garam masala and slow-cooked meat dishes.'],
            'Asafoetida' => ['hindi' => 'हींग',
                'desc' => 'Pungent dried resin used by the pinch. Mellows into a savoury onion-garlic depth once it hits hot fat.',
                'usage' => 'Add a pinch to hot oil for a second before the other spices. Keep tightly sealed — the aroma migrates.'],
            'Dried Fenugreek Leaves' => ['hindi' => 'कसूरी मेथी',
                'desc' => 'Sun-dried fenugreek leaf (kasuri methi), slightly bitter and buttery.',
                'usage' => 'Crush between the palms and stir in during the last minute of cooking.'],
            'Dry Ginger' => ['hindi' => 'सोंठ',
                'desc' => 'Whole dried ginger rhizome — sharper and more astringent than fresh ginger.'],
            'Whole Turmeric Pieces' => ['hindi' => 'साबुत हल्दी',
                'desc' => 'Unground dried turmeric fingers. Ground as needed for maximum colour and aroma, or used whole in pickles.'],

            // ---- Spice Powders ---------------------------------------------
            'Coriander Powder' => ['hindi' => 'धनिया पाउडर',
                'desc' => 'Freshly ground coriander seed — the bulk base of most Nepali and North Indian curry masalas.'],
            'Cinnamon Powder' => ['hindi' => 'दालचीनी पाउडर',
                'desc' => 'Finely ground cinnamon bark for baking, masala chiya and sweet spice mixes.'],
            'Turmeric Powder' => ['hindi' => 'हल्दी',
                'desc' => 'Ground turmeric root giving everyday cooking its yellow colour and warm, bitter base note.'],
            'Dry Ginger Powder' => ['hindi' => 'सोंठ पाउडर',
                'desc' => 'Ground sonth — warming and pungent. Used in masala chiya, sweets and winter preparations.'],
            'Red Chilli Powder' => ['hindi' => 'लाल मिर्च पाउडर',
                'desc' => 'Ground dried red chilli for heat and colour.',
                'usage' => 'Potency varies between batches — season gradually and taste as you go.'],

            // ---- Premium Spices --------------------------------------------
            'Nutmeg' => ['hindi' => 'जायफल',
                'desc' => 'Whole nutmeg kernel, grated sparingly into sweets, garam masala and milk preparations.'],
            'Cloves' => ['hindi' => 'लौंग',
                'desc' => 'Whole dried flower buds, intensely aromatic. Used in garam masala, pulao and chiya.'],
            'Green Cardamom Medium' => ['hindi' => 'छोटी इलायची', 'tax' => 'luxury',
                'desc' => 'Medium-grade green cardamom pods — the everyday grade for chiya, kheer and masala.'],
            'Green Cardamom Large' => ['hindi' => 'छोटी इलायची', 'tax' => 'luxury',
                'desc' => 'Large, plump green cardamom pods with a fuller aroma, for fine cooking and gifting.'],
            'Black Cardamom' => ['hindi' => 'बड़ी इलायची', 'tax' => 'luxury',
                'desc' => 'Smoke-dried large cardamom. Deeply resinous; used whole in rice, dal and meat dishes.'],
            'Cinnamon Roll' => ['hindi' => 'दालचीनी',
                'desc' => 'Tightly rolled cinnamon quills — thicker cassia-type bark suited to long simmering.'],
            'Cinnamon Stick' => ['hindi' => 'दालचीनी',
                'desc' => 'Loose cinnamon bark pieces, snapped into pulao, chiya and slow-cooked gravies.'],
            'Mace' => ['hindi' => 'जावित्री', 'tax' => 'luxury',
                'desc' => 'The lacy red aril around the nutmeg seed — more delicate than nutmeg itself, and a garam masala essential.'],
            'Long Pepper' => ['hindi' => 'पिप्पली',
                'desc' => 'Slender catkin-shaped pepper, hotter and sweeter than black pepper. A long-standing Ayurvedic staple.'],
            'Saffron' => ['hindi' => 'केसर', 'tax' => 'luxury',
                'desc' => 'Hand-picked saffron threads. A few strands colour and perfume an entire dish.',
                'usage' => 'Steep in a spoon of warm milk or water for 10 minutes, then add the liquid and threads together.'],

            // ---- Peppers & Chillis -----------------------------------------
            'Black Pepper' => ['hindi' => 'काली मिर्च',
                'desc' => 'Whole black peppercorns — the "king of spices". Cracked fresh for the sharpest bite.'],
            'White Pepper' => ['hindi' => 'सफेद मिर्च',
                'desc' => 'Peppercorns with the outer skin removed: cleaner heat without dark specks, for pale sauces and soups.'],
            'Sichuan Pepper' => ['hindi' => 'तिमूर',
                'desc' => 'Nepali timur — citrusy and numbing rather than simply hot. Essential to chutney and momo achar.'],
            'Red Chilli' => ['hindi' => 'सूखी लाल मिर्च',
                'desc' => 'Whole dried red chillies for tempering, pickling and grinding into fresh powder.'],

            // ---- Seeds & Grains --------------------------------------------
            'Fenugreek Seeds' => ['hindi' => 'मेथी दाना',
                'desc' => 'Hard amber seeds, bitter until tempered. A few in hot oil open most Nepali vegetable dishes.',
                'usage' => 'Fry only until they darken a shade — burnt fenugreek turns harshly bitter.'],
            'Fennel Seeds Sweet' => ['hindi' => 'मीठी सौंफ',
                'desc' => 'Sweet, plump fennel — the after-meal mouth-freshener grade.'],
            'Fennel Seeds Normal' => ['hindi' => 'सौंफ',
                'desc' => 'Standard cooking fennel with a clean anise aroma, for masalas and pickles.'],
            'Fennel Seeds Normal Local' => ['hindi' => 'सौंफ',
                'desc' => 'Locally sourced fennel — smaller seed and sharper aroma, everyday cooking grade.'],
            'Ajwain Premium' => ['hindi' => 'अजवायन',
                'desc' => 'Selected carom seed, thymol-rich and sharply pungent. Used in breads, fried snacks and lentils.'],
            'Ajwain Normal' => ['hindi' => 'अजवायन',
                'desc' => 'Everyday carom seed for paratha, namkeen and digestive preparations.'],
            'Black Mustard Seeds' => ['hindi' => 'काली सरसों',
                'desc' => 'Small black mustard for tempering — pops and turns nutty in hot oil.'],
            'Mustard Seeds' => ['hindi' => 'सरसों',
                'desc' => 'Standard mustard seed for tadka, pickling and grinding into paste.'],
            'Yellow Mustard' => ['hindi' => 'पीली सरसों',
                'desc' => 'Larger yellow mustard, milder than the black variety. Used in pickling and mustard paste.'],
            'Brown Sesame Seeds' => ['hindi' => 'भूरा तिल',
                'desc' => 'Unhulled brown sesame with the seed coat intact — nuttier, and richer in minerals than hulled seed.'],
            'Black Sesame Seeds' => ['hindi' => 'काला तिल',
                'desc' => 'Black sesame, prized in Nepali kitchens for til ko achar and winter sweets.'],
            'Black Mix Sesame Seeds' => ['hindi' => 'मिश्रित तिल',
                'desc' => 'A blend of black and light sesame seed for chutneys and laddu.'],
            'White Sesame Seeds' => ['hindi' => 'सफेद तिल',
                'desc' => 'Hulled white sesame — toasted for garnish, ground for til ko achar and sweets.'],
            'Kalonji / Nigella Sativa' => ['hindi' => 'कलौंजी',
                'desc' => 'Angular black nigella seed with an oregano-like bite. Used on breads and in pickle masala.'],
            'Garden Cress Seeds' => ['hindi' => 'हलीम',
                'desc' => 'Chansur seed, traditionally cooked with ghee and jaggery into a postnatal strengthening laddu.'],
            'Ban Silam Seeds' => ['hindi' => 'बन सिलाम',
                'desc' => 'Wild Himalayan perilla seed, toasted and ground into a distinctive Nepali chutney.'],

            // ---- Dry Fruits & Nuts -----------------------------------------
            'Walnut Premium' => ['hindi' => 'अखरोट',
                'desc' => 'Large in-shell walnuts, selected for size and kernel fill.'],
            'Walnut Standard' => ['hindi' => 'अखरोट',
                'desc' => 'Everyday in-shell walnuts for daily use and festive gifting.'],
            'Walnut Kernels' => ['hindi' => 'अखरोट दाना',
                'desc' => 'Shelled walnut halves and pieces, ready to use in baking and sweets.'],
            'Cashew Premium' => ['hindi' => 'काजू',
                'desc' => 'Whole unbroken cashew kernels — pale, evenly sized and suited to gifting.'],
            'Cashew Standard' => ['hindi' => 'काजू',
                'desc' => 'Cashew kernels with some breakage: the same nut at a keener price for gravies and cooking.'],
            'Pistachio' => ['hindi' => 'पिस्ता',
                'desc' => 'Roasted and salted pistachios in shell.'],
            'Anjeer Premium / Figs' => ['hindi' => 'अंजीर',
                'desc' => 'Large soft dried figs, selected grade.'],
            'Anjeer Standard / Figs' => ['hindi' => 'अंजीर',
                'desc' => 'Everyday dried figs for soaking, sweets and daily snacking.'],
            'Almond' => ['hindi' => 'बादाम',
                'desc' => 'Whole raw almonds, for eating soaked or using whole in sweets and masalas.',
                'usage' => 'Soak overnight and slip off the skins for the softest texture and easiest digestion.'],
            'Kishmish / Raisins' => ['hindi' => 'किशमिश',
                'desc' => 'Seedless dried grapes, soft and sweet. Used in pulao, kheer and festive sweets.'],
            'Dates / Khajur' => ['hindi' => 'खजूर',
                'desc' => 'Soft dried dates for daily eating and religious offerings.'],
            'Premium Dates Pkt' => ['hindi' => 'खजूर',
                'desc' => 'Selected large dates in a sealed retail pack — a ready gift and festival item.'],
            'Coconut' => ['hindi' => 'नारियल',
                'desc' => 'Dried coconut (copra) for grating into chutneys and sweets, and for puja use.'],
            'Makhana Madhur' => ['hindi' => 'मखाना',
                'desc' => 'Popped fox-nut, Madhur grade — light and crisp.',
                'usage' => 'Roast in a little ghee until it crackles, then salt. Also simmered into kheer.'],
            'Makhana Kanaiya' => ['hindi' => 'मखाना',
                'desc' => 'Kanaiya-grade popped fox-nut — smaller puffs, everyday value grade.'],
            'Makhana Balgopal' => ['hindi' => 'मखाना',
                'desc' => 'Balgopal-grade fox-nut: the largest, whitest puffs, for fasting food and premium snacking.'],
            'Areca Nut' => ['hindi' => 'सुपारी',
                'desc' => 'Dried areca nut pieces for paan and traditional use.'],
            'Pooja Supari' => ['hindi' => 'पूजा सुपारी',
                'desc' => 'Whole betel nut reserved for ritual and puja offerings.'],

            // ---- Seeds & Superfoods ----------------------------------------
            'Pumpkin Seeds' => ['hindi' => 'कद्दू के बीज',
                'desc' => 'Hulled green pumpkin seed, rich in zinc and magnesium. Eaten roasted or stirred through granola.'],
            'Watermelon Seeds' => ['hindi' => 'मगज',
                'desc' => 'Hulled watermelon kernel (magaz), ground into thickening pastes for rich gravies and sweets.'],
            'Cucumber Seeds' => ['hindi' => 'खीरे के बीज',
                'desc' => 'Hulled cucumber kernels — cooling and mild, used in thandai and sweet pastes.'],
            'Sunflower Seeds' => ['hindi' => 'सूरजमुखी के बीज',
                'desc' => 'Hulled sunflower kernels for daily snacking, salads and baking.'],
            'Hemp Seeds' => ['hindi' => 'भांग के बीज',
                'desc' => 'Toasted Himalayan hemp seed, ground into the classic Nepali bhang ko achar.'],
            'Chiya Seeds' => ['hindi' => 'चिया बीज',
                'desc' => 'Chia seed — swells to a gel in water. Used in drinks, puddings and as a plant omega-3 source.',
                'usage' => 'Stir a spoonful into water or juice and leave 10 minutes before drinking.'],
            'Sabudana Large' => ['hindi' => 'साबूदाना',
                'desc' => 'Large tapioca pearls for khichdi, kheer and fasting dishes.'],
            'Sabudana Small' => ['hindi' => 'साबूदाना',
                'desc' => 'Small tapioca pearls — quicker to soak, ideal for kheer and vada.'],

            // ---- Sweeteners & Snacks ---------------------------------------
            'Dhikka Mishri' => ['hindi' => 'ढेला मिश्री',
                'desc' => 'Large crystallised rock-sugar lumps. Slow-dissolving; offered as prasad and taken with fennel after meals.'],
            'Cutting Mishri' => ['hindi' => 'कटिंग मिश्री',
                'desc' => 'Cut rock-sugar crystals, evenly sized for mouth-freshener mixes and daily use.'],
            'Gond' => ['hindi' => 'गोंद',
                'desc' => 'Edible acacia gum crystals.',
                'usage' => 'Fry in ghee over low heat until the crystals puff and turn pale, then bind into winter laddu.'],
            'Gud Bites' => ['hindi' => 'गुड़ की डली',
                'desc' => 'Bite-sized jaggery pieces, portioned for after-meal use and easy retail.'],
            'Gud Normal' => ['hindi' => 'गुड़',
                'desc' => 'Everyday cane jaggery block — an unrefined, mineral-rich sweetener for tea, sweets and daily cooking.'],
            'Gulmeli Gud' => ['hindi' => 'गुड़',
                'desc' => 'Gulmeli-style jaggery: softer and darker, with a deeper molasses note.'],
            'Chana Fry' => ['hindi' => 'भुना चना',
                'desc' => 'Roasted split chickpeas — crisp, salted and protein-rich. An everyday snack.'],
            'Lapsi Powder' => ['hindi' => 'लप्सी पाउडर',
                'desc' => 'Ground Nepali hog plum. Sharply sour; used in chutneys, candies and as a souring agent.'],

            // ---- Salt ------------------------------------------------------
            'Balk Salt' => ['hindi' => 'काला नमक',
                'desc' => 'Black salt (bit nun) — sulphurous and tangy. Essential to chaat, jeera water and fruit salads.'],
            'White Salt' => ['hindi' => 'सफेद नमक',
                'desc' => 'Refined everyday table and cooking salt.'],
        ];
    }
}

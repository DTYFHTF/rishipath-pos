<?php

/**
 * ProductCatalogSeeder — May 2026 Rate List
 *
 * - Creates 9 categories
 * - Upserts 74 products (73 × 6 pack sizes + Saffron 1 g special)
 * - Preserves image_url on existing matched products
 * - Deactivates all products NOT in the CSV
 *
 * Prices verified against "Combined Rate List.csv".
 * packs key = grams (1000 = 1 kg); value = MRP for that pack.
 * Wholesale shown live = ceil(cost_price × 1.13).
 */

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductCatalogSeeder extends Seeder
{
    private const ORG_ID = 1;

    private array $catIds = [];

    // -------------------------------------------------------------------------
    public function run(): void
    {
        $this->command->info('=== ProductCatalogSeeder — May 2026 Rate List ===');

        $this->seedCategories();

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

        $product->name = $d['name'];
        $product->category_id = $catId;
        $product->name_nepali = $d['nepali'] ?? null;
        $product->name_romanized = $d['romanized'] ?? null;
        $product->product_type = 'others';
        $product->unit_type = 'weight';
        $product->has_variants = true;
        $product->requires_batch = false;
        $product->requires_expiry = false;
        $product->tax_category = 'standard';
        $product->active = true;
        $product->save();

        // Upsert variants by SKU (matched, not deleted+recreated) so IDs — and the
        // stock_levels/product_batches/inventory_movements/purchase_items rows that
        // cascade-delete on variant removal — survive repeated seeding on every deploy.
        $cpPerKg = $d['cp'];
        $costOverrides = $d['cost_overrides'] ?? [];
        $keepSkus = [];

        foreach ($d['packs'] as $grams => $mrp) {
            if ($grams === 1000) {
                $packSize = 1.000;
                $unit = 'KG';
                $cost = (float) $cpPerKg;
                $sfx = '1KG';
            } elseif ($grams === 1) {
                // Special: Saffron 1 g packet
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

            ProductVariant::updateOrCreate(
                ['sku' => $sku],
                [
                    'product_id' => $product->id,
                    'pack_size' => $packSize,
                    'unit' => $unit,
                    'cost_price' => $cost,
                    'mrp_india' => $mrp,
                    'base_price' => $mrp,
                    'active' => true,
                ]
            );
        }

        // Remove only variants no longer present in this product's pack list
        ProductVariant::where('product_id', $product->id)
            ->whereNotIn('sku', $keepSkus)
            ->delete();

        $flag = $isNew ? '[NEW]' : '[UPD]';
        $mrp1 = $d['packs'][1000] ?? $d['packs'][array_key_first($d['packs'])];
        $this->command->line("  {$flag} {$d['name']}  CP={$cpPerKg}  MRP(1kg/top)={$mrp1}  ".count($d['packs']).' variants');
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
            ['name' => 'Walnut Premium', 'existing_name' => 'Walnut Premium (Okhar Premium)', 'nepali' => 'अखरोट', 'romanized' => 'Akharot', 'category' => 'Dry Fruits & Nuts', 'cp' => 620,
                'packs' => [1000 => 775, 500 => 415, 200 => 180, 100 => 95, 50 => 55, 20 => 35]],

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

            ['name' => 'Anjeer / Figs', 'existing_name' => 'Figs (Anjeer)', 'nepali' => 'अञ्जीर', 'romanized' => 'Anjir', 'category' => 'Dry Fruits & Nuts', 'cp' => 1600,
                'packs' => [1000 => 2000, 500 => 1065, 200 => 465, 100 => 240, 50 => 145, 20 => 80]],

            ['name' => 'Almond', 'nepali' => 'बादाम', 'romanized' => 'Badam', 'category' => 'Dry Fruits & Nuts', 'cp' => 1600,
                'packs' => [1000 => 2000, 500 => 1065, 200 => 465, 100 => 240, 50 => 145, 20 => 80]],

            ['name' => 'Kishmish / Raisins', 'existing_name' => 'Raisins (Kishmish)', 'nepali' => 'किसमिस', 'romanized' => 'Kishmish', 'category' => 'Dry Fruits & Nuts', 'cp' => 580,
                'packs' => [1000 => 725, 500 => 385, 200 => 170, 100 => 90, 50 => 55, 20 => 30]],

            ['name' => 'Dates / Khajur', 'existing_name' => 'Dates (Khajur)', 'nepali' => 'खजुर', 'romanized' => 'Khajur', 'category' => 'Dry Fruits & Nuts', 'cp' => 350,
                'packs' => [1000 => 440, 500 => 235, 200 => 105, 100 => 55, 50 => 35, 20 => 20]],

            ['name' => 'AlKhalifa Dates Pkt', 'nepali' => 'अलखलिफा खजुर', 'romanized' => 'AlKhalifa Khajur', 'category' => 'Dry Fruits & Nuts', 'cp' => 160,
                'packs' => [1000 => 200, 500 => 110, 200 => 50, 100 => 25, 50 => 15, 20 => 10]],

            ['name' => 'Coconut', 'existing_name' => 'Coconut', 'nepali' => 'नरिवल', 'romanized' => 'Narival', 'category' => 'Dry Fruits & Nuts', 'cp' => 780,
                'packs' => [1000 => 975, 500 => 520, 200 => 225, 100 => 120, 50 => 70, 20 => 40]],

            ['name' => 'Makhana Madhur', 'existing_name' => 'Fox Nuts Regular (Makhana Madhur)', 'nepali' => 'मखाना', 'romanized' => 'Makhana', 'category' => 'Dry Fruits & Nuts', 'cp' => 460,
                'packs' => [1000 => 575, 500 => 305, 200 => 135, 100 => 70, 50 => 45, 20 => 25]],

            ['name' => 'Makhana Kanaiya', 'existing_name' => 'Fox Nuts Large (Makhana Kanaiya)', 'nepali' => 'मखाना', 'romanized' => 'Makhana', 'category' => 'Dry Fruits & Nuts', 'cp' => 340,
                'packs' => [1000 => 425, 500 => 230, 200 => 100, 100 => 55, 50 => 30, 20 => 20]],

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
}

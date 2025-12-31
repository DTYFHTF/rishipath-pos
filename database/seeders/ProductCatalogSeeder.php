<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StockLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCatalogSeeder extends Seeder
{
    private Organization $org;
    private Store $store;

    public function run(): void
    {
        $this->org = Organization::where('slug', 'rishipath')->first();
        $this->store = Store::where('code', 'MAIN')->first();

        if (!$this->org || !$this->store) {
            $this->command->error('Please run InitialSetupSeeder first!');
            return;
        }

        $this->createCategories();
        $this->createProducts();

        $this->command->info('✅ Product catalog seeded successfully!');
    }

    private function createCategories(): void
    {
        $categories = [
            [
                'name' => 'Ayurvedic Choornas (Powders)',
                'name_nepali' => 'आयुर्वेदिक चूर्ण',
                'name_hindi' => 'आयुर्वेदिक चूर्ण',
                'slug' => 'ayurvedic-choornas',
                'product_type' => 'choorna',
                'config' => [
                    'unit_type' => 'weight',
                    'default_unit' => 'GMS',
                    'common_sizes' => [50, 100, 250, 500],
                    'tax_category' => 'essential',
                    'requires_batch' => true,
                    'requires_expiry' => true,
                    'shelf_life_months' => 24,
                ],
            ],
            [
                'name' => 'Ayurvedic Tailams (Medicated Oils)',
                'name_nepali' => 'आयुर्वेदिक तेल',
                'name_hindi' => 'आयुर्वेदिक तेल',
                'slug' => 'ayurvedic-tailams',
                'product_type' => 'tailam',
                'config' => [
                    'unit_type' => 'volume',
                    'default_unit' => 'ML',
                    'common_sizes' => [10, 50, 125, 250, 550],
                    'tax_category' => 'essential',
                    'requires_batch' => true,
                    'requires_expiry' => true,
                    'shelf_life_months' => 36,
                ],
            ],
            [
                'name' => 'Ayurvedic Ghritams (Medicated Ghee)',
                'name_nepali' => 'आयुर्वेदिक घृत',
                'name_hindi' => 'आयुर्वेदिक घी',
                'slug' => 'ayurvedic-ghritams',
                'product_type' => 'ghritam',
                'config' => [
                    'unit_type' => 'volume',
                    'default_unit' => 'ML',
                    'common_sizes' => [125, 250, 550],
                    'tax_category' => 'essential',
                    'requires_batch' => true,
                    'requires_expiry' => true,
                    'shelf_life_months' => 24,
                ],
            ],
            [
                'name' => 'Ayurvedic Capsules & Tablets',
                'name_nepali' => 'आयुर्वेदिक क्याप्सुल',
                'name_hindi' => 'आयुर्वेदिक कैप्सूल',
                'slug' => 'ayurvedic-capsules',
                'product_type' => 'capsules',
                'config' => [
                    'unit_type' => 'piece',
                    'default_unit' => 'CAPSULES',
                    'common_sizes' => [30, 60, 90],
                    'tax_category' => 'essential',
                    'requires_batch' => true,
                    'requires_expiry' => true,
                    'shelf_life_months' => 36,
                ],
            ],
            [
                'name' => 'Herbal Teas & Beverages',
                'name_nepali' => 'हर्बल चिया',
                'name_hindi' => 'हर्बल चाय',
                'slug' => 'herbal-teas',
                'product_type' => 'tea',
                'config' => [
                    'unit_type' => 'weight',
                    'default_unit' => 'GMS',
                    'common_sizes' => [100, 250, 500],
                    'tax_category' => 'essential',
                    'requires_batch' => true,
                    'requires_expiry' => true,
                    'shelf_life_months' => 12,
                ],
            ],
            [
                'name' => 'Natural Honey & Sweeteners',
                'name_nepali' => 'प्राकृतिक मह',
                'name_hindi' => 'प्राकृतिक शहद',
                'slug' => 'honey-sweeteners',
                'product_type' => 'honey',
                'config' => [
                    'unit_type' => 'volume',
                    'default_unit' => 'ML',
                    'common_sizes' => [250, 500],
                    'tax_category' => 'essential',
                    'requires_batch' => true,
                    'requires_expiry' => true,
                    'shelf_life_months' => 24,
                ],
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::create([
                'organization_id' => $this->org->id,
                'name' => $categoryData['name'],
                'name_nepali' => $categoryData['name_nepali'] ?? null,
                'name_hindi' => $categoryData['name_hindi'] ?? null,
                'slug' => $categoryData['slug'],
                'product_type' => $categoryData['product_type'],
                'config' => $categoryData['config'],
                'active' => true,
            ]);
        }
    }

    private function createProducts(): void
    {
        $products = [
            // Herbal Teas
            [
                'name' => '100% Pure Organic Assam Tea',
                'category_slug' => 'herbal-teas',
                'product_type' => 'tea',
                'variants' => [
                    ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 200.00],
                ],
            ],
            // Ayurvedic Oils
            [
                'name' => 'Akshi Bindu',
                'name_sanskrit' => 'अक्षि बिन्दु',
                'category_slug' => 'ayurvedic-tailams',
                'product_type' => 'tailam',
                'description' => 'Ayurvedic eye drops for eye health',
                'variants' => [
                    ['pack_size' => 10, 'unit' => 'ML', 'mrp' => 70.00],
                ],
            ],
            [
                'name' => 'Almond Oil',
                'category_slug' => 'ayurvedic-tailams',
                'product_type' => 'tailam',
                'variants' => [
                    ['pack_size' => 50, 'unit' => 'ML', 'mrp' => 240.00],
                    ['pack_size' => 250, 'unit' => 'ML', 'mrp' => 825.00],
                ],
            ],
            [
                'name' => 'Ashwagandha Oil',
                'name_sanskrit' => 'अश्वगन्धा तैलम्',
                'category_slug' => 'ayurvedic-tailams',
                'product_type' => 'tailam',
                'variants' => [
                    ['pack_size' => 250, 'unit' => 'ML', 'mrp' => 340.00],
                ],
            ],
            // Choornas
            [
                'name' => 'Triphala Choorna',
                'name_sanskrit' => 'त्रिफला चूर्ण',
                'category_slug' => 'ayurvedic-choornas',
                'product_type' => 'choorna',
                'description' => 'Traditional Ayurvedic powder for digestive health',
                'ingredients' => ['Amalaki', 'Haritaki', 'Bibhitaki'],
                'variants' => [
                    ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 80.00],
                    ['pack_size' => 250, 'unit' => 'GMS', 'mrp' => 180.00],
                ],
            ],
            [
                'name' => 'Ashwagandha Choorna',
                'name_sanskrit' => 'अश्वगन्धा चूर्ण',
                'category_slug' => 'ayurvedic-choornas',
                'product_type' => 'choorna',
                'description' => 'Rejuvenating herb for strength and vitality',
                'variants' => [
                    ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 150.00],
                    ['pack_size' => 250, 'unit' => 'GMS', 'mrp' => 350.00],
                ],
            ],
            // Ghritams
            [
                'name' => 'Brahmi Ghrita',
                'name_sanskrit' => 'ब्राह्मी घृत',
                'category_slug' => 'ayurvedic-ghritams',
                'product_type' => 'ghritam',
                'description' => 'Medicated ghee for mental clarity',
                'variants' => [
                    ['pack_size' => 125, 'unit' => 'ML', 'mrp' => 280.00],
                    ['pack_size' => 250, 'unit' => 'ML', 'mrp' => 520.00],
                ],
            ],
            // Honey
            [
                'name' => 'Pure Wild Honey',
                'name_hindi' => 'शुद्ध जंगली शहद',
                'category_slug' => 'honey-sweeteners',
                'product_type' => 'honey',
                'variants' => [
                    ['pack_size' => 250, 'unit' => 'ML', 'mrp' => 250.00],
                    ['pack_size' => 500, 'unit' => 'ML', 'mrp' => 480.00],
                ],
            ],
            // Capsules
            [
                'name' => 'Turmeric Capsules',
                'name_sanskrit' => 'हरिद्रा कैप्सूल',
                'category_slug' => 'ayurvedic-capsules',
                'product_type' => 'capsules',
                'description' => 'Anti-inflammatory support',
                'variants' => [
                    ['pack_size' => 60, 'unit' => 'CAPSULES', 'mrp' => 320.00],
                ],
            ],
        ];

        foreach ($products as $productData) {
            $category = Category::where('slug', $productData['category_slug'])->first();
            
            $sku = 'RSH-' . strtoupper(substr($productData['product_type'], 0, 3)) . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

            $product = Product::create([
                'organization_id' => $this->org->id,
                'category_id' => $category->id,
                'sku' => $sku,
                'name' => $productData['name'],
                'name_sanskrit' => $productData['name_sanskrit'] ?? null,
                'name_hindi' => $productData['name_hindi'] ?? null,
                'description' => $productData['description'] ?? null,
                'product_type' => $productData['product_type'],
                'unit_type' => $category->config['unit_type'],
                'has_variants' => count($productData['variants']) > 1,
                'tax_category' => $category->config['tax_category'],
                'requires_batch' => $category->config['requires_batch'],
                'requires_expiry' => $category->config['requires_expiry'],
                'shelf_life_months' => $category->config['shelf_life_months'],
                'ingredients' => $productData['ingredients'] ?? null,
                'active' => true,
            ]);

            foreach ($productData['variants'] as $index => $variantData) {
                $variantSku = $sku . '-' . $variantData['pack_size'] . $variantData['unit'];
                
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $variantSku,
                    'pack_size' => $variantData['pack_size'],
                    'unit' => $variantData['unit'],
                    'mrp_india' => $variantData['mrp'],
                    'base_price' => $variantData['mrp'],
                    'cost_price' => $variantData['mrp'] * 0.6, // 40% margin
                    'hsn_code' => $this->getHsnCode($productData['product_type']),
                    'active' => true,
                ]);

                // Create initial stock level
                StockLevel::create([
                    'product_variant_id' => $variant->id,
                    'store_id' => $this->store->id,
                    'quantity' => rand(10, 50),
                    'reserved_quantity' => 0,
                    'reorder_level' => 10,
                    'last_counted_at' => now(),
                ]);
            }
        }
    }

    private function getHsnCode(string $productType): string
    {
        return match($productType) {
            'choorna', 'tailam', 'ghritam', 'capsules' => '30049099', // Ayurvedic medicines
            'tea' => '09024000', // Black tea
            'honey' => '04090000', // Natural honey
            default => '30049099',
        };
    }
}

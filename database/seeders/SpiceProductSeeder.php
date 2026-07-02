<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\PricingService;
use Illuminate\Database\Seeder;

/**
 * Seeds real spice, herb, and Ayurvedic raw materials sold by Rishipath.
 * Products are sourced from the verified product list (spices/herbs image).
 */
class SpiceProductSeeder extends Seeder
{
    private Organization $org;

    private Store $store;

    public function run(): void
    {
        $this->org = Organization::where('slug', 'rishipath')->first();
        $this->store = Store::where('code', 'MAIN')->first();

        if (! $this->org || ! $this->store) {
            $this->command->error('Please run InitialSetupSeeder first!');

            return;
        }

        $this->createCategories();
        $this->createProducts();

        $this->command->info('✅ Spice & herb product catalog seeded successfully!');
    }

    // ─── Categories ───────────────────────────────────────────────────────────

    private function createCategories(): void
    {
        $categories = [
            [
                'name' => 'Spices & Masala',
                'name_nepali' => 'मसला तथा मसाला',
                'name_hindi' => 'मसाले',
                'slug' => 'spices-masala',
                'product_type' => 'others',
                'description' => 'Whole and ground spices including turmeric, cumin, coriander and blended masalas.',
                'config' => [
                    'unit_type' => 'weight',
                    'default_unit' => 'GMS',
                    'common_sizes' => [50, 100, 250, 500, 1000],
                    'tax_category' => 'essential',
                    'requires_batch' => true,
                    'requires_expiry' => true,
                    'shelf_life_months' => 24,
                ],
            ],
            [
                'name' => 'Peppers & Chillis',
                'name_nepali' => 'खुर्सानी तथा मिर्च',
                'name_hindi' => 'मिर्च',
                'slug' => 'peppers-chillis',
                'product_type' => 'others',
                'description' => 'Black pepper, white pepper, Sichuan pepper, red chilli, dalle and jyanmara varieties.',
                'config' => [
                    'unit_type' => 'weight',
                    'default_unit' => 'GMS',
                    'common_sizes' => [50, 100, 250, 500],
                    'tax_category' => 'essential',
                    'requires_batch' => true,
                    'requires_expiry' => true,
                    'shelf_life_months' => 18,
                ],
            ],
            [
                'name' => 'Seeds & Grains',
                'name_nepali' => 'बिउ तथा दाना',
                'name_hindi' => 'बीज',
                'slug' => 'seeds-grains',
                'product_type' => 'others',
                'description' => 'Mustard, fenugreek, sesame, poppy, ajwain and other medicinal seeds.',
                'config' => [
                    'unit_type' => 'weight',
                    'default_unit' => 'GMS',
                    'common_sizes' => [50, 100, 250, 500],
                    'tax_category' => 'essential',
                    'requires_batch' => true,
                    'requires_expiry' => true,
                    'shelf_life_months' => 18,
                ],
            ],
            [
                'name' => 'Herbs & Leaves',
                'name_nepali' => 'जडिबुटी तथा पात',
                'name_hindi' => 'जड़ी-बूटी',
                'slug' => 'herbs-leaves',
                'product_type' => 'others',
                'description' => 'Dried curry leaves, bay leaf, lemongrass, dried amla and other medicinal herbs.',
                'config' => [
                    'unit_type' => 'weight',
                    'default_unit' => 'GMS',
                    'common_sizes' => [25, 50, 100, 250],
                    'tax_category' => 'essential',
                    'requires_batch' => true,
                    'requires_expiry' => true,
                    'shelf_life_months' => 12,
                ],
            ],
            [
                'name' => 'Aromatic Roots & Bark',
                'name_nepali' => 'जरा तथा बोक्रा',
                'name_hindi' => 'जड़ और छाल',
                'slug' => 'aromatic-roots-bark',
                'product_type' => 'others',
                'description' => 'Cinnamon, licorice root, sweet flag, and other bark/root spices.',
                'config' => [
                    'unit_type' => 'weight',
                    'default_unit' => 'GMS',
                    'common_sizes' => [25, 50, 100, 250],
                    'tax_category' => 'essential',
                    'requires_batch' => true,
                    'requires_expiry' => true,
                    'shelf_life_months' => 36,
                ],
            ],
            [
                'name' => 'Nuts & Dried Fruits',
                'name_nepali' => 'सुकेको फल तथा दाना',
                'name_hindi' => 'मेवा',
                'slug' => 'nuts-dried-fruits',
                'product_type' => 'others',
                'description' => 'Areca nut, coconut, dried amla and other nuts/dried fruits.',
                'config' => [
                    'unit_type' => 'weight',
                    'default_unit' => 'GMS',
                    'common_sizes' => [50, 100, 250, 500],
                    'tax_category' => 'essential',
                    'requires_batch' => true,
                    'requires_expiry' => true,
                    'shelf_life_months' => 12,
                ],
            ],
            [
                'name' => 'Premium & Rare Spices',
                'name_nepali' => 'दुर्लभ मसला',
                'name_hindi' => 'दुर्लभ मसाले',
                'slug' => 'premium-rare-spices',
                'product_type' => 'others',
                'description' => 'Saffron, mace, nutmeg, green and black cardamom, and other premium spices.',
                'config' => [
                    'unit_type' => 'weight',
                    'default_unit' => 'GMS',
                    'common_sizes' => [5, 10, 25, 50, 100],
                    'tax_category' => 'essential',
                    'requires_batch' => true,
                    'requires_expiry' => true,
                    'shelf_life_months' => 36,
                ],
            ],
        ];

        foreach ($categories as $data) {
            Category::updateOrCreate(
                [
                    'organization_id' => $this->org->id,
                    'slug' => $data['slug'],
                ],
                [
                    'name' => $data['name'],
                    'name_nepali' => $data['name_nepali'] ?? null,
                    'name_hindi' => $data['name_hindi'] ?? null,
                    'description' => $data['description'] ?? null,
                    'product_type' => $data['product_type'],
                    'config' => $data['config'],
                    'active' => true,
                ]
            );
        }
    }

    // ─── Products ─────────────────────────────────────────────────────────────

    private function createProducts(): void
    {
        $products = [

            // ── Spices & Masala ──────────────────────────────────────────────
            [
                'name' => 'Turmeric Powder',
                'name_nepali' => 'बेसार',
                'name_hindi' => 'हल्दी',
                'category' => 'spices-masala',
                'sku_code' => 'TUR',
                'variants' => [
                    ['pack_size' => 100,  'mrp' => 60.00,  'cost' => 35.00],
                    ['pack_size' => 250,  'mrp' => 130.00, 'cost' => 78.00],
                    ['pack_size' => 500,  'mrp' => 240.00, 'cost' => 145.00],
                    ['pack_size' => 1000, 'mrp' => 450.00, 'cost' => 275.00],
                ],
            ],
            [
                'name' => 'Cumin Seeds',
                'name_nepali' => 'जिरा',
                'name_hindi' => 'जीरा',
                'category' => 'spices-masala',
                'sku_code' => 'CUM',
                'variants' => [
                    ['pack_size' => 100,  'mrp' => 90.00,  'cost' => 55.00],
                    ['pack_size' => 250,  'mrp' => 210.00, 'cost' => 130.00],
                    ['pack_size' => 500,  'mrp' => 400.00, 'cost' => 245.00],
                ],
            ],
            [
                'name' => 'Coriander Seeds',
                'name_nepali' => 'धनियाँ',
                'name_hindi' => 'धनिया',
                'category' => 'spices-masala',
                'sku_code' => 'COR',
                'variants' => [
                    ['pack_size' => 100,  'mrp' => 40.00, 'cost' => 24.00],
                    ['pack_size' => 250,  'mrp' => 90.00, 'cost' => 56.00],
                    ['pack_size' => 500,  'mrp' => 170.00, 'cost' => 105.00],
                ],
            ],
            [
                'name' => 'Asafoetida (Hing)',
                'name_nepali' => 'हिङ',
                'name_hindi' => 'हींग',
                'category' => 'spices-masala',
                'sku_code' => 'ASF',
                'variants' => [
                    ['pack_size' => 10,  'mrp' => 80.00,  'cost' => 48.00],
                    ['pack_size' => 25,  'mrp' => 180.00, 'cost' => 110.00],
                    ['pack_size' => 50,  'mrp' => 340.00, 'cost' => 205.00],
                ],
            ],
            [
                'name' => 'Star Anise',
                'name_nepali' => 'चक्रफूल',
                'name_hindi' => 'चक्र फूल',
                'category' => 'spices-masala',
                'sku_code' => 'STA',
                'variants' => [
                    ['pack_size' => 50,  'mrp' => 120.00, 'cost' => 72.00],
                    ['pack_size' => 100, 'mrp' => 220.00, 'cost' => 135.00],
                ],
            ],
            [
                'name' => 'Kalonji (Nigella Seeds)',
                'name_nepali' => 'कालोजिरा',
                'name_hindi' => 'कलौंजी',
                'category' => 'spices-masala',
                'sku_code' => 'KAL',
                'variants' => [
                    ['pack_size' => 50,  'mrp' => 70.00,  'cost' => 42.00],
                    ['pack_size' => 100, 'mrp' => 130.00, 'cost' => 80.00],
                    ['pack_size' => 250, 'mrp' => 300.00, 'cost' => 185.00],
                ],
            ],

            // ── Peppers & Chillis ────────────────────────────────────────────
            [
                'name' => 'Black Pepper (Whole)',
                'name_nepali' => 'कालो मिर्च',
                'name_hindi' => 'काली मिर्च',
                'category' => 'peppers-chillis',
                'sku_code' => 'BKP',
                'variants' => [
                    ['pack_size' => 50,  'mrp' => 90.00,  'cost' => 54.00],
                    ['pack_size' => 100, 'mrp' => 170.00, 'cost' => 104.00],
                    ['pack_size' => 250, 'mrp' => 400.00, 'cost' => 245.00],
                ],
            ],
            [
                'name' => 'White Pepper',
                'name_nepali' => 'सेतो मिर्च',
                'name_hindi' => 'सफेद मिर्च',
                'category' => 'peppers-chillis',
                'sku_code' => 'WTP',
                'variants' => [
                    ['pack_size' => 50,  'mrp' => 110.00, 'cost' => 66.00],
                    ['pack_size' => 100, 'mrp' => 210.00, 'cost' => 128.00],
                ],
            ],
            [
                'name' => 'Sichuan Pepper (Timur)',
                'name_nepali' => 'टिमुर',
                'name_hindi' => 'तेजपत्ता मिर्च',
                'category' => 'peppers-chillis',
                'sku_code' => 'TIM',
                'variants' => [
                    ['pack_size' => 25,  'mrp' => 80.00,  'cost' => 48.00],
                    ['pack_size' => 50,  'mrp' => 150.00, 'cost' => 92.00],
                    ['pack_size' => 100, 'mrp' => 280.00, 'cost' => 172.00],
                ],
            ],
            [
                'name' => 'Red Chilli (Whole)',
                'name_nepali' => 'रातो खुर्सानी',
                'name_hindi' => 'साबुत लाल मिर्च',
                'category' => 'peppers-chillis',
                'sku_code' => 'RCH',
                'variants' => [
                    ['pack_size' => 100, 'mrp' => 60.00,  'cost' => 36.00],
                    ['pack_size' => 250, 'mrp' => 140.00, 'cost' => 86.00],
                    ['pack_size' => 500, 'mrp' => 260.00, 'cost' => 158.00],
                ],
            ],
            [
                'name' => 'Dalle Khursani (Dalle Chilli)',
                'name_nepali' => 'डल्ले खुर्सानी',
                'name_hindi' => 'डल्ले मिर्च',
                'category' => 'peppers-chillis',
                'sku_code' => 'DAL',
                'variants' => [
                    ['pack_size' => 50,  'mrp' => 150.00, 'cost' => 92.00],
                    ['pack_size' => 100, 'mrp' => 280.00, 'cost' => 172.00],
                ],
            ],
            [
                'name' => 'Jyanmara Chilli',
                'name_nepali' => 'ज्यानमारा खुर्सानी',
                'name_hindi' => 'ज्यानमारा मिर्च',
                'category' => 'peppers-chillis',
                'sku_code' => 'JYA',
                'variants' => [
                    ['pack_size' => 50,  'mrp' => 120.00, 'cost' => 72.00],
                    ['pack_size' => 100, 'mrp' => 220.00, 'cost' => 135.00],
                ],
            ],

            // ── Seeds & Grains ───────────────────────────────────────────────
            [
                'name' => 'Mustard Seeds (Black)',
                'name_nepali' => 'रायो',
                'name_hindi' => 'सरसों',
                'category' => 'seeds-grains',
                'sku_code' => 'MUS',
                'variants' => [
                    ['pack_size' => 100, 'mrp' => 30.00,  'cost' => 18.00],
                    ['pack_size' => 250, 'mrp' => 65.00,  'cost' => 40.00],
                    ['pack_size' => 500, 'mrp' => 120.00, 'cost' => 74.00],
                ],
            ],
            [
                'name' => 'Mustard (Yellow)',
                'name_nepali' => 'तोरी',
                'name_hindi' => 'पीली सरसों',
                'category' => 'seeds-grains',
                'sku_code' => 'MUY',
                'variants' => [
                    ['pack_size' => 100, 'mrp' => 35.00,  'cost' => 21.00],
                    ['pack_size' => 250, 'mrp' => 75.00,  'cost' => 46.00],
                    ['pack_size' => 500, 'mrp' => 140.00, 'cost' => 86.00],
                ],
            ],
            [
                'name' => 'Fenugreek Seeds',
                'name_nepali' => 'मेथी',
                'name_hindi' => 'मेथी',
                'category' => 'seeds-grains',
                'sku_code' => 'FEN',
                'variants' => [
                    ['pack_size' => 100, 'mrp' => 50.00,  'cost' => 30.00],
                    ['pack_size' => 250, 'mrp' => 115.00, 'cost' => 70.00],
                    ['pack_size' => 500, 'mrp' => 210.00, 'cost' => 130.00],
                ],
            ],
            [
                'name' => 'Fennel Seeds',
                'name_nepali' => 'सौंफ',
                'name_hindi' => 'सौंफ',
                'category' => 'seeds-grains',
                'sku_code' => 'FES',
                'variants' => [
                    ['pack_size' => 50,  'mrp' => 40.00,  'cost' => 24.00],
                    ['pack_size' => 100, 'mrp' => 75.00,  'cost' => 46.00],
                    ['pack_size' => 250, 'mrp' => 170.00, 'cost' => 104.00],
                ],
            ],
            [
                'name' => 'Sesame Seeds (White)',
                'name_nepali' => 'तिल',
                'name_hindi' => 'तिल',
                'category' => 'seeds-grains',
                'sku_code' => 'SES',
                'variants' => [
                    ['pack_size' => 100, 'mrp' => 55.00,  'cost' => 33.00],
                    ['pack_size' => 250, 'mrp' => 125.00, 'cost' => 77.00],
                    ['pack_size' => 500, 'mrp' => 230.00, 'cost' => 142.00],
                ],
            ],
            [
                'name' => 'Black Sesame Seeds',
                'name_nepali' => 'कालो तिल',
                'name_hindi' => 'काला तिल',
                'category' => 'seeds-grains',
                'sku_code' => 'BSS',
                'variants' => [
                    ['pack_size' => 100, 'mrp' => 70.00,  'cost' => 42.00],
                    ['pack_size' => 250, 'mrp' => 165.00, 'cost' => 101.00],
                ],
            ],
            [
                'name' => 'Poppy Seeds',
                'name_nepali' => 'पोस्ता दाना',
                'name_hindi' => 'खसखस',
                'category' => 'seeds-grains',
                'sku_code' => 'POP',
                'variants' => [
                    ['pack_size' => 50,  'mrp' => 85.00,  'cost' => 51.00],
                    ['pack_size' => 100, 'mrp' => 160.00, 'cost' => 98.00],
                ],
            ],
            [
                'name' => 'Garden Cress Seeds (Halim)',
                'name_nepali' => 'चन्द्रसूर',
                'name_hindi' => 'हलीम',
                'category' => 'seeds-grains',
                'sku_code' => 'GCS',
                'variants' => [
                    ['pack_size' => 100, 'mrp' => 80.00,  'cost' => 48.00],
                    ['pack_size' => 250, 'mrp' => 185.00, 'cost' => 114.00],
                ],
            ],
            [
                'name' => 'Ajwain (Carom Seeds)',
                'name_nepali' => 'जवानो',
                'name_hindi' => 'अजवाइन',
                'category' => 'seeds-grains',
                'sku_code' => 'AJW',
                'variants' => [
                    ['pack_size' => 100, 'mrp' => 65.00,  'cost' => 39.00],
                    ['pack_size' => 250, 'mrp' => 150.00, 'cost' => 92.00],
                ],
            ],
            [
                'name' => 'Ajmoda (Celery Seeds)',
                'name_nepali' => 'अजमोद',
                'name_hindi' => 'अजमोद',
                'category' => 'seeds-grains',
                'sku_code' => 'AJM',
                'variants' => [
                    ['pack_size' => 50,  'mrp' => 60.00,  'cost' => 36.00],
                    ['pack_size' => 100, 'mrp' => 110.00, 'cost' => 68.00],
                ],
            ],
            [
                'name' => 'Silam Seeds',
                'name_nepali' => 'सिलाम दाना',
                'name_hindi' => 'सिलाम',
                'category' => 'seeds-grains',
                'sku_code' => 'SIL',
                'variants' => [
                    ['pack_size' => 50,  'mrp' => 90.00,  'cost' => 54.00],
                    ['pack_size' => 100, 'mrp' => 170.00, 'cost' => 104.00],
                ],
            ],
            [
                'name' => 'Wild Fennel Seeds (Ban Saunf)',
                'name_nepali' => 'बन सौंफ',
                'name_hindi' => 'जंगली सौंफ',
                'category' => 'seeds-grains',
                'sku_code' => 'WFS',
                'variants' => [
                    ['pack_size' => 50,  'mrp' => 75.00,  'cost' => 45.00],
                    ['pack_size' => 100, 'mrp' => 140.00, 'cost' => 86.00],
                ],
            ],

            // ── Herbs & Leaves ───────────────────────────────────────────────
            [
                'name' => 'Dried Curry Leaves',
                'name_nepali' => 'करीपत्ता',
                'name_hindi' => 'करी पत्ता',
                'category' => 'herbs-leaves',
                'sku_code' => 'CRL',
                'variants' => [
                    ['pack_size' => 25,  'mrp' => 70.00,  'cost' => 42.00],
                    ['pack_size' => 50,  'mrp' => 130.00, 'cost' => 80.00],
                    ['pack_size' => 100, 'mrp' => 240.00, 'cost' => 148.00],
                ],
            ],
            [
                'name' => 'Bay Leaf (Tej Patta)',
                'name_nepali' => 'तेजपत्ता',
                'name_hindi' => 'तेज पत्ता',
                'category' => 'herbs-leaves',
                'sku_code' => 'BAY',
                'variants' => [
                    ['pack_size' => 25,  'mrp' => 50.00,  'cost' => 30.00],
                    ['pack_size' => 50,  'mrp' => 95.00,  'cost' => 58.00],
                    ['pack_size' => 100, 'mrp' => 180.00, 'cost' => 110.00],
                ],
            ],
            [
                'name' => 'Lemongrass (Dried)',
                'name_nepali' => 'लेमनग्रास',
                'name_hindi' => 'लेमनग्रास',
                'category' => 'herbs-leaves',
                'sku_code' => 'LGR',
                'variants' => [
                    ['pack_size' => 50,  'mrp' => 80.00,  'cost' => 48.00],
                    ['pack_size' => 100, 'mrp' => 150.00, 'cost' => 92.00],
                ],
            ],

            // ── Aromatic Roots & Bark ────────────────────────────────────────
            [
                'name' => 'Cinnamon (Dalchini)',
                'name_nepali' => 'दालचिनी',
                'name_hindi' => 'दालचीनी',
                'category' => 'aromatic-roots-bark',
                'sku_code' => 'CIN',
                'variants' => [
                    ['pack_size' => 50,  'mrp' => 80.00,  'cost' => 48.00],
                    ['pack_size' => 100, 'mrp' => 150.00, 'cost' => 92.00],
                    ['pack_size' => 250, 'mrp' => 350.00, 'cost' => 215.00],
                ],
            ],
            [
                'name' => 'Licorice Root (Jethimadhu)',
                'name_nepali' => 'जेठीमधु',
                'name_hindi' => 'मुलेठी',
                'category' => 'aromatic-roots-bark',
                'sku_code' => 'LIC',
                'variants' => [
                    ['pack_size' => 50,  'mrp' => 100.00, 'cost' => 60.00],
                    ['pack_size' => 100, 'mrp' => 190.00, 'cost' => 117.00],
                    ['pack_size' => 250, 'mrp' => 440.00, 'cost' => 272.00],
                ],
            ],
            [
                'name' => 'Sweet Flag (Bojho)',
                'name_nepali' => 'बोझो',
                'name_hindi' => 'वचा',
                'category' => 'aromatic-roots-bark',
                'sku_code' => 'SWF',
                'variants' => [
                    ['pack_size' => 50,  'mrp' => 120.00, 'cost' => 72.00],
                    ['pack_size' => 100, 'mrp' => 220.00, 'cost' => 135.00],
                ],
            ],

            // ── Premium & Rare Spices ────────────────────────────────────────
            [
                'name' => 'Saffron (Kesar)',
                'name_nepali' => 'केसर',
                'name_hindi' => 'केसर',
                'category' => 'premium-rare-spices',
                'sku_code' => 'SAF',
                'variants' => [
                    ['pack_size' => 1,  'mrp' => 350.00,  'cost' => 210.00],
                    ['pack_size' => 2,  'mrp' => 650.00,  'cost' => 390.00],
                    ['pack_size' => 5,  'mrp' => 1500.00, 'cost' => 900.00],
                ],
            ],
            [
                'name' => 'Green Cardamom (Elaichi)',
                'name_nepali' => 'सानो अलैंची',
                'name_hindi' => 'इलायची',
                'category' => 'premium-rare-spices',
                'sku_code' => 'GCA',
                'variants' => [
                    ['pack_size' => 10,  'mrp' => 90.00,  'cost' => 54.00],
                    ['pack_size' => 25,  'mrp' => 210.00, 'cost' => 128.00],
                    ['pack_size' => 50,  'mrp' => 400.00, 'cost' => 245.00],
                    ['pack_size' => 100, 'mrp' => 780.00, 'cost' => 480.00],
                ],
            ],
            [
                'name' => 'Black Cardamom (Badi Elaichi)',
                'name_nepali' => 'ठूलो अलैंची',
                'name_hindi' => 'बड़ी इलायची',
                'category' => 'premium-rare-spices',
                'sku_code' => 'BCA',
                'variants' => [
                    ['pack_size' => 25,  'mrp' => 120.00, 'cost' => 72.00],
                    ['pack_size' => 50,  'mrp' => 220.00, 'cost' => 135.00],
                    ['pack_size' => 100, 'mrp' => 420.00, 'cost' => 258.00],
                ],
            ],
            [
                'name' => 'Nutmeg (Jaiphal)',
                'name_nepali' => 'जाइफल',
                'name_hindi' => 'जायफल',
                'category' => 'premium-rare-spices',
                'sku_code' => 'NTM',
                'variants' => [
                    ['pack_size' => 25,  'mrp' => 140.00, 'cost' => 84.00],
                    ['pack_size' => 50,  'mrp' => 260.00, 'cost' => 160.00],
                ],
            ],
            [
                'name' => 'Mace (Javitri)',
                'name_nepali' => 'जावित्री',
                'name_hindi' => 'जावित्री',
                'category' => 'premium-rare-spices',
                'sku_code' => 'MAC',
                'variants' => [
                    ['pack_size' => 10,  'mrp' => 120.00, 'cost' => 72.00],
                    ['pack_size' => 25,  'mrp' => 280.00, 'cost' => 172.00],
                ],
            ],
            [
                'name' => 'Cloves (Lwang)',
                'name_nepali' => 'ल्वाङ',
                'name_hindi' => 'लौंग',
                'category' => 'premium-rare-spices',
                'sku_code' => 'CLV',
                'variants' => [
                    ['pack_size' => 25,  'mrp' => 100.00, 'cost' => 60.00],
                    ['pack_size' => 50,  'mrp' => 185.00, 'cost' => 114.00],
                    ['pack_size' => 100, 'mrp' => 360.00, 'cost' => 221.00],
                ],
            ],

            // ── Nuts & Dried Fruits ──────────────────────────────────────────
            [
                'name' => 'Dried Amla (Indian Gooseberry)',
                'name_nepali' => 'सुकेको अमला',
                'name_hindi' => 'सूखा आंवला',
                'category' => 'nuts-dried-fruits',
                'sku_code' => 'AML',
                'variants' => [
                    ['pack_size' => 100, 'mrp' => 80.00,  'cost' => 48.00],
                    ['pack_size' => 250, 'mrp' => 180.00, 'cost' => 110.00],
                    ['pack_size' => 500, 'mrp' => 340.00, 'cost' => 208.00],
                ],
            ],
            [
                'name' => 'Areca Nut (Supari)',
                'name_nepali' => 'सुपारी',
                'name_hindi' => 'सुपारी',
                'category' => 'nuts-dried-fruits',
                'sku_code' => 'ARC',
                'variants' => [
                    ['pack_size' => 100, 'mrp' => 90.00,  'cost' => 54.00],
                    ['pack_size' => 250, 'mrp' => 200.00, 'cost' => 123.00],
                ],
            ],
            [
                'name' => 'Desiccated Coconut',
                'name_nepali' => 'नरिवल',
                'name_hindi' => 'नारियल',
                'category' => 'nuts-dried-fruits',
                'sku_code' => 'COC',
                'variants' => [
                    ['pack_size' => 100, 'mrp' => 60.00,  'cost' => 36.00],
                    ['pack_size' => 250, 'mrp' => 135.00, 'cost' => 83.00],
                    ['pack_size' => 500, 'mrp' => 250.00, 'cost' => 154.00],
                ],
            ],
        ];

        foreach ($products as $productData) {
            $category = Category::where('organization_id', $this->org->id)
                ->where('slug', $productData['category'])
                ->first();

            if (! $category) {
                $this->command->warn("Category '{$productData['category']}' not found, skipping {$productData['name']}");

                continue;
            }

            $sku = 'RSH-'.$productData['sku_code'];

            // Skip if a product with this name already exists (may have been created
            // by another seeder, e.g. ProductCatalogSeeder, under a different SKU scheme)
            if (Product::where('organization_id', $this->org->id)->where('name', $productData['name'])->exists()) {
                $this->command->line("  Skipping existing: {$productData['name']}");

                continue;
            }

            $product = Product::create([
                'organization_id' => $this->org->id,
                'category_id' => $category->id,
                'sku' => $sku,
                'name' => $productData['name'],
                'name_nepali' => $productData['name_nepali'] ?? null,
                'name_romanized' => $productData['name_romanized'] ?? null,
                'name_hindi' => $productData['name_hindi'] ?? null,
                'description' => $productData['description'] ?? null,
                'product_type' => $category->product_type,
                'unit_type' => $category->config['unit_type'] ?? 'weight',
                'has_variants' => count($productData['variants']) > 1,
                'tax_category' => $category->config['tax_category'] ?? 'essential',
                'requires_batch' => $category->config['requires_batch'] ?? true,
                'requires_expiry' => $category->config['requires_expiry'] ?? true,
                'shelf_life_months' => $category->config['shelf_life_months'] ?? 24,
                'active' => true,
            ]);

            foreach ($productData['variants'] as $variantData) {
                $unit = $category->config['default_unit'] ?? 'GMS';
                $variantSku = $sku.'-'.$variantData['pack_size'].$unit;
                $suggestedPrices = PricingService::suggestVariantPrices(
                    (float) $variantData['cost'],
                    (float) $variantData['pack_size'],
                    $unit,
                );

                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $variantSku,
                    'pack_size' => $variantData['pack_size'],
                    'unit' => $unit,
                    'mrp_india' => $suggestedPrices['mrp_india'],
                    'base_price' => $suggestedPrices['base_price'],
                    'selling_price_nepal' => $suggestedPrices['selling_price_nepal'],
                    'cost_price' => $variantData['cost'],
                    'hsn_code' => '09109900', // Spices HSN code
                    'active' => true,
                ]);

                StockLevel::firstOrCreate(
                    [
                        'product_variant_id' => $variant->id,
                        'store_id' => $this->store->id,
                    ],
                    [
                        'quantity' => 0,
                        'reserved_quantity' => 0,
                        'reorder_level' => 10,
                        'last_counted_at' => now(),
                    ]
                );
            }

            $this->command->line("  ✓ {$productData['name']} (".count($productData['variants']).' variants)');
        }
    }
}

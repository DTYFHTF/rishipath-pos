<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Store;
use Illuminate\Database\Seeder;

/**
 * Seeds dry fruits, nuts, seeds, and additional spice products.
 * Product names are stored as bilingual: English + Nepali script + Romanized Nepali.
 * Display format for price lists: "Almond (बादाम / Badam)"
 */
class DryFruitsSeeder extends Seeder
{
    private Organization $org;
    private Store $store;

    public function run(): void
    {
        $this->org   = Organization::where('slug', 'rishipath')->first();
        $this->store = Store::where('code', 'MAIN')->first();

        if (! $this->org || ! $this->store) {
            $this->command->error('Run InitialSetupSeeder first!');
            return;
        }

        $this->ensureCategories();
        $this->seedProducts();

        $this->command->info('✅ Dry fruits & new products seeded!');
    }

    // ─── Categories ───────────────────────────────────────────────────────────

    private function ensureCategories(): void
    {
        $cats = [
            [
                'name'         => 'Dry Fruits & Nuts',
                'name_nepali'  => 'सुकेको फल तथा दाना',
                'name_hindi'   => 'मेवा',
                'slug'         => 'dry-fruits-nuts',
                'product_type' => 'others',
                'description'  => 'Premium dry fruits, nuts, and mixed mewa: almonds, cashew, walnut, pista, raisins, etc.',
                'config' => [
                    'unit_type'         => 'weight',
                    'default_unit'      => 'GMS',
                    'common_sizes'      => [50, 100, 250, 500, 1000],
                    'tax_category'      => 'essential',
                    'requires_batch'    => true,
                    'requires_expiry'   => true,
                    'shelf_life_months' => 12,
                ],
            ],
            [
                'name'         => 'Seeds & Superfoods',
                'name_nepali'  => 'बिउ तथा सुपरफूड',
                'name_hindi'   => 'बीज',
                'slug'         => 'seeds-superfoods',
                'product_type' => 'others',
                'description'  => 'Chia seeds, pumpkin seeds, watermelon seeds, sabudana, makhana and more.',
                'config' => [
                    'unit_type'         => 'weight',
                    'default_unit'      => 'GMS',
                    'common_sizes'      => [50, 100, 250, 500],
                    'tax_category'      => 'essential',
                    'requires_batch'    => true,
                    'requires_expiry'   => true,
                    'shelf_life_months' => 12,
                ],
            ],
            [
                'name'         => 'Spice Powders & Masala',
                'name_nepali'  => 'मसला पाउडर',
                'name_hindi'   => 'मसाला पाउडर',
                'slug'         => 'spice-powders-masala',
                'product_type' => 'others',
                'description'  => 'Ground spice powders: turmeric, chilli, coriander, garam masala, dalchini, etc.',
                'config' => [
                    'unit_type'         => 'weight',
                    'default_unit'      => 'GMS',
                    'common_sizes'      => [50, 100, 250, 500, 1000],
                    'tax_category'      => 'essential',
                    'requires_batch'    => true,
                    'requires_expiry'   => true,
                    'shelf_life_months' => 18,
                ],
            ],
            [
                'name'         => 'Salts & Sweeteners',
                'name_nepali'  => 'नुन तथा मिठाई',
                'name_hindi'   => 'नमक और मिठास',
                'slug'         => 'salts-sweeteners',
                'product_type' => 'others',
                'description'  => 'Rock salt, black salt, jaggery, sakar and natural sweeteners.',
                'config' => [
                    'unit_type'         => 'weight',
                    'default_unit'      => 'GMS',
                    'common_sizes'      => [100, 250, 500, 1000],
                    'tax_category'      => 'essential',
                    'requires_batch'    => true,
                    'requires_expiry'   => true,
                    'shelf_life_months' => 36,
                ],
            ],
            [
                'name'         => 'Other Items',
                'name_nepali'  => 'अन्य सामान',
                'name_hindi'   => 'अन्य वस्तु',
                'slug'         => 'other-items',
                'product_type' => 'others',
                'description'  => 'Miscellaneous items: candles, gond, lapsi powder, etc.',
                'config' => [
                    'unit_type'         => 'weight',
                    'default_unit'      => 'GMS',
                    'common_sizes'      => [50, 100, 250, 500],
                    'tax_category'      => 'standard',
                    'requires_batch'    => false,
                    'requires_expiry'   => false,
                    'shelf_life_months' => 24,
                ],
            ],
        ];

        foreach ($cats as $data) {
            Category::firstOrCreate(
                ['organization_id' => $this->org->id, 'slug' => $data['slug']],
                [
                    'name'         => $data['name'],
                    'name_nepali'  => $data['name_nepali'],
                    'name_hindi'   => $data['name_hindi'],
                    'description'  => $data['description'],
                    'product_type' => $data['product_type'],
                    'config'       => $data['config'],
                    'active'       => true,
                ]
            );
        }
    }

    // ─── Products ─────────────────────────────────────────────────────────────

    private function seedProducts(): void
    {
        /*
         * Format:
         *   name         – English display name
         *   name_nepali  – Nepali script (Devanagari)
         *   name_hindi   – Romanized Nepali (used as transliteration in price list)
         *   category     – slug of category
         *   sku_code     – 3–6 letter prefix for SKU generation
         *   variants     – pack_size in grams, mrp in NPR, cost in NPR
         *
         * Price list display: "Almond (बादाम / Badam)"
         */
        $products = [

            // ══ DRY FRUITS & NUTS ════════════════════════════════════════════

            [
                'name'        => 'Coconut',
                'name_nepali' => 'नरिवल',
                'name_hindi'  => 'Nariwal',
                'category'    => 'dry-fruits-nuts',
                'sku_code'    => 'NRW',
                'variants'    => [
                    ['pack_size' => 100,  'mrp' => 60.00,  'cost' => 36.00],
                    ['pack_size' => 250,  'mrp' => 130.00, 'cost' => 78.00],
                    ['pack_size' => 500,  'mrp' => 240.00, 'cost' => 144.00],
                    ['pack_size' => 1000, 'mrp' => 440.00, 'cost' => 264.00],
                ],
            ],
            [
                'name'        => 'Cashew (Kaju)',
                'name_nepali' => 'काजु',
                'name_hindi'  => 'Kaju',
                'category'    => 'dry-fruits-nuts',
                'sku_code'    => 'KJU',
                'variants'    => [
                    ['pack_size' => 100,  'mrp' => 220.00, 'cost' => 132.00],
                    ['pack_size' => 250,  'mrp' => 520.00, 'cost' => 312.00],
                    ['pack_size' => 500,  'mrp' => 980.00, 'cost' => 588.00],
                ],
            ],
            [
                'name'        => 'Almond (Badam)',
                'name_nepali' => 'बादाम',
                'name_hindi'  => 'Badam',
                'category'    => 'dry-fruits-nuts',
                'sku_code'    => 'BDM',
                'variants'    => [
                    ['pack_size' => 100,  'mrp' => 280.00, 'cost' => 168.00],
                    ['pack_size' => 250,  'mrp' => 650.00, 'cost' => 390.00],
                    ['pack_size' => 500,  'mrp' => 1200.00, 'cost' => 720.00],
                ],
            ],
            [
                'name'        => 'Raw Almond (Badam Kacho)',
                'name_nepali' => 'बदाम काचो',
                'name_hindi'  => 'Badam Kacho',
                'category'    => 'dry-fruits-nuts',
                'sku_code'    => 'BDK',
                'variants'    => [
                    ['pack_size' => 100,  'mrp' => 180.00, 'cost' => 108.00],
                    ['pack_size' => 250,  'mrp' => 420.00, 'cost' => 252.00],
                    ['pack_size' => 500,  'mrp' => 780.00, 'cost' => 468.00],
                ],
            ],
            [
                'name'        => 'Raisins (Kishmish)',
                'name_nepali' => 'किसमिस',
                'name_hindi'  => 'Kishmish',
                'category'    => 'dry-fruits-nuts',
                'sku_code'    => 'KSM',
                'variants'    => [
                    ['pack_size' => 100,  'mrp' => 120.00, 'cost' => 72.00],
                    ['pack_size' => 250,  'mrp' => 280.00, 'cost' => 168.00],
                    ['pack_size' => 500,  'mrp' => 520.00, 'cost' => 312.00],
                ],
            ],
            [
                'name'        => 'Walnut Kernels (Okhar)',
                'name_nepali' => 'ओखर गिरी',
                'name_hindi'  => 'Okhar Giri',
                'category'    => 'dry-fruits-nuts',
                'sku_code'    => 'WNT',
                'variants'    => [
                    ['pack_size' => 100,  'mrp' => 300.00, 'cost' => 180.00],
                    ['pack_size' => 250,  'mrp' => 700.00, 'cost' => 420.00],
                    ['pack_size' => 500,  'mrp' => 1300.00, 'cost' => 780.00],
                ],
            ],
            [
                'name'        => 'Walnut Premium (Okhar Premium)',
                'name_nepali' => 'ओखर प्रिमियम',
                'name_hindi'  => 'Okhar Premium',
                'category'    => 'dry-fruits-nuts',
                'sku_code'    => 'WNP',
                'variants'    => [
                    ['pack_size' => 100,  'mrp' => 380.00, 'cost' => 228.00],
                    ['pack_size' => 250,  'mrp' => 880.00, 'cost' => 528.00],
                    ['pack_size' => 500,  'mrp' => 1600.00, 'cost' => 960.00],
                ],
            ],
            [
                'name'        => 'Walnut Standard (Okhar Standard)',
                'name_nepali' => 'ओखर स्ट्यान्डर्ड',
                'name_hindi'  => 'Okhar Standard',
                'category'    => 'dry-fruits-nuts',
                'sku_code'    => 'WNS',
                'variants'    => [
                    ['pack_size' => 100,  'mrp' => 280.00, 'cost' => 168.00],
                    ['pack_size' => 250,  'mrp' => 650.00, 'cost' => 390.00],
                    ['pack_size' => 500,  'mrp' => 1200.00, 'cost' => 720.00],
                ],
            ],
            [
                'name'        => 'Pistachio (Pista)',
                'name_nepali' => 'पिस्ता',
                'name_hindi'  => 'Pista',
                'category'    => 'dry-fruits-nuts',
                'sku_code'    => 'PST',
                'variants'    => [
                    ['pack_size' => 50,  'mrp' => 280.00, 'cost' => 168.00],
                    ['pack_size' => 100, 'mrp' => 520.00, 'cost' => 312.00],
                    ['pack_size' => 250, 'mrp' => 1200.00, 'cost' => 720.00],
                ],
            ],
            [
                'name'        => 'Dates (Khajur)',
                'name_nepali' => 'खजुर',
                'name_hindi'  => 'Khajur',
                'category'    => 'dry-fruits-nuts',
                'sku_code'    => 'KHJ',
                'variants'    => [
                    ['pack_size' => 100, 'mrp' => 130.00, 'cost' => 78.00],
                    ['pack_size' => 250, 'mrp' => 300.00, 'cost' => 180.00],
                    ['pack_size' => 500, 'mrp' => 560.00, 'cost' => 336.00],
                ],
            ],
            [
                'name'        => 'Figs (Anjeer)',
                'name_nepali' => 'अन्जीर',
                'name_hindi'  => 'Anjeer',
                'category'    => 'dry-fruits-nuts',
                'sku_code'    => 'ANJ',
                'variants'    => [
                    ['pack_size' => 100, 'mrp' => 280.00, 'cost' => 168.00],
                    ['pack_size' => 250, 'mrp' => 650.00, 'cost' => 390.00],
                ],
            ],
            [
                'name'        => 'Wheat Bran (Chokara)',
                'name_nepali' => 'चोकर',
                'name_hindi'  => 'Chokara',
                'category'    => 'dry-fruits-nuts',
                'sku_code'    => 'CHK',
                'variants'    => [
                    ['pack_size' => 250, 'mrp' => 60.00,  'cost' => 36.00],
                    ['pack_size' => 500, 'mrp' => 110.00, 'cost' => 66.00],
                    ['pack_size' => 1000,'mrp' => 200.00, 'cost' => 120.00],
                ],
            ],

            // ══ SEEDS & SUPERFOODS ════════════════════════════════════════════

            [
                'name'        => 'Pumpkin Seeds',
                'name_nepali' => 'कद्दुको बियाँ',
                'name_hindi'  => 'Kaddu ko Biya',
                'category'    => 'seeds-superfoods',
                'sku_code'    => 'PKS',
                'variants'    => [
                    ['pack_size' => 50,  'mrp' => 120.00, 'cost' => 72.00],
                    ['pack_size' => 100, 'mrp' => 220.00, 'cost' => 132.00],
                    ['pack_size' => 250, 'mrp' => 500.00, 'cost' => 300.00],
                ],
            ],
            [
                'name'        => 'Chia Seeds (Chiya Seeds)',
                'name_nepali' => 'चिया सिड्स',
                'name_hindi'  => 'Chiya Seeds',
                'category'    => 'seeds-superfoods',
                'sku_code'    => 'CHS',
                'variants'    => [
                    ['pack_size' => 50,  'mrp' => 130.00, 'cost' => 78.00],
                    ['pack_size' => 100, 'mrp' => 240.00, 'cost' => 144.00],
                    ['pack_size' => 250, 'mrp' => 560.00, 'cost' => 336.00],
                ],
            ],
            [
                'name'        => 'Watermelon Seeds Large (Tarbuza Biya Thulo)',
                'name_nepali' => 'तरबुजाको बियाँ ठूलो',
                'name_hindi'  => 'Tarbuza Biya Thulo',
                'category'    => 'seeds-superfoods',
                'sku_code'    => 'WMT',
                'variants'    => [
                    ['pack_size' => 50,  'mrp' => 90.00,  'cost' => 54.00],
                    ['pack_size' => 100, 'mrp' => 160.00, 'cost' => 96.00],
                    ['pack_size' => 250, 'mrp' => 380.00, 'cost' => 228.00],
                ],
            ],
            [
                'name'        => 'Watermelon Seeds Small (Tarbuza Biya Sana)',
                'name_nepali' => 'तरबुजाको बियाँ साना',
                'name_hindi'  => 'Tarbuza Biya Sana',
                'category'    => 'seeds-superfoods',
                'sku_code'    => 'WMS',
                'variants'    => [
                    ['pack_size' => 50,  'mrp' => 80.00,  'cost' => 48.00],
                    ['pack_size' => 100, 'mrp' => 140.00, 'cost' => 84.00],
                    ['pack_size' => 250, 'mrp' => 320.00, 'cost' => 192.00],
                ],
            ],
            [
                'name'        => 'Fox Nuts Large (Makhana Kanaiya)',
                'name_nepali' => 'मखाना कनैया',
                'name_hindi'  => 'Makhana Kanaiya',
                'category'    => 'seeds-superfoods',
                'sku_code'    => 'MKK',
                'variants'    => [
                    ['pack_size' => 50,  'mrp' => 120.00, 'cost' => 72.00],
                    ['pack_size' => 100, 'mrp' => 220.00, 'cost' => 132.00],
                    ['pack_size' => 250, 'mrp' => 500.00, 'cost' => 300.00],
                ],
            ],
            [
                'name'        => 'Fox Nuts Regular (Makhana Madhur)',
                'name_nepali' => 'मखाना मधुर',
                'name_hindi'  => 'Makhana Madhur',
                'category'    => 'seeds-superfoods',
                'sku_code'    => 'MKM',
                'variants'    => [
                    ['pack_size' => 50,  'mrp' => 100.00, 'cost' => 60.00],
                    ['pack_size' => 100, 'mrp' => 180.00, 'cost' => 108.00],
                    ['pack_size' => 250, 'mrp' => 420.00, 'cost' => 252.00],
                ],
            ],
            [
                'name'        => 'Sabudana Large (Sabudana Thulo)',
                'name_nepali' => 'साबुदाना ठूलो',
                'name_hindi'  => 'Sabudana Thulo',
                'category'    => 'seeds-superfoods',
                'sku_code'    => 'SBT',
                'variants'    => [
                    ['pack_size' => 100, 'mrp' => 60.00,  'cost' => 36.00],
                    ['pack_size' => 250, 'mrp' => 140.00, 'cost' => 84.00],
                    ['pack_size' => 500, 'mrp' => 260.00, 'cost' => 156.00],
                ],
            ],
            [
                'name'        => 'Sabudana Small (Sabudana Sana)',
                'name_nepali' => 'साबुदाना साना',
                'name_hindi'  => 'Sabudana Sana',
                'category'    => 'seeds-superfoods',
                'sku_code'    => 'SBS',
                'variants'    => [
                    ['pack_size' => 100, 'mrp' => 55.00,  'cost' => 33.00],
                    ['pack_size' => 250, 'mrp' => 130.00, 'cost' => 78.00],
                    ['pack_size' => 500, 'mrp' => 240.00, 'cost' => 144.00],
                ],
            ],

            // ══ SPICE POWDERS & MASALA ════════════════════════════════════════

            [
                'name'        => 'Hing Small (Hing Sana)',
                'name_nepali' => 'हिंग साना',
                'name_hindi'  => 'Hing Sana',
                'category'    => 'spice-powders-masala',
                'sku_code'    => 'HGS',
                'variants'    => [
                    ['pack_size' => 10,  'mrp' => 80.00,  'cost' => 48.00],
                    ['pack_size' => 25,  'mrp' => 180.00, 'cost' => 108.00],
                    ['pack_size' => 50,  'mrp' => 340.00, 'cost' => 204.00],
                ],
            ],
            [
                'name'        => 'Hing Large (Hing Thulo)',
                'name_nepali' => 'हिंग ठूलो',
                'name_hindi'  => 'Hing Thulo',
                'category'    => 'spice-powders-masala',
                'sku_code'    => 'HGT',
                'variants'    => [
                    ['pack_size' => 50,  'mrp' => 400.00, 'cost' => 240.00],
                    ['pack_size' => 100, 'mrp' => 750.00, 'cost' => 450.00],
                ],
            ],
            [
                'name'        => 'Dried Ginger Powder (Sutho)',
                'name_nepali' => 'सुठो पाउडर',
                'name_hindi'  => 'Sutho Powder',
                'category'    => 'spice-powders-masala',
                'sku_code'    => 'STH',
                'variants'    => [
                    ['pack_size' => 50,  'mrp' => 80.00,  'cost' => 48.00],
                    ['pack_size' => 100, 'mrp' => 150.00, 'cost' => 90.00],
                    ['pack_size' => 250, 'mrp' => 340.00, 'cost' => 204.00],
                ],
            ],
            [
                'name'        => 'Coriander Powder (Dhaniya Powder)',
                'name_nepali' => 'धनिया पाउडर',
                'name_hindi'  => 'Dhaniya Powder',
                'category'    => 'spice-powders-masala',
                'sku_code'    => 'DNP',
                'variants'    => [
                    ['pack_size' => 100, 'mrp' => 60.00,  'cost' => 36.00],
                    ['pack_size' => 250, 'mrp' => 130.00, 'cost' => 78.00],
                    ['pack_size' => 500, 'mrp' => 240.00, 'cost' => 144.00],
                ],
            ],
            [
                'name'        => 'Chilli Powder (Khorsani Powder)',
                'name_nepali' => 'खोर्सानी पाउडर',
                'name_hindi'  => 'Khorsani Powder',
                'category'    => 'spice-powders-masala',
                'sku_code'    => 'CHP',
                'variants'    => [
                    ['pack_size' => 100, 'mrp' => 80.00,  'cost' => 48.00],
                    ['pack_size' => 250, 'mrp' => 180.00, 'cost' => 108.00],
                    ['pack_size' => 500, 'mrp' => 340.00, 'cost' => 204.00],
                ],
            ],
            [
                'name'        => 'Cinnamon Powder (Dalchini Powder)',
                'name_nepali' => 'दालचिनी पाउडर',
                'name_hindi'  => 'Dalchini Powder',
                'category'    => 'spice-powders-masala',
                'sku_code'    => 'DCP',
                'variants'    => [
                    ['pack_size' => 50,  'mrp' => 90.00,  'cost' => 54.00],
                    ['pack_size' => 100, 'mrp' => 170.00, 'cost' => 102.00],
                    ['pack_size' => 250, 'mrp' => 380.00, 'cost' => 228.00],
                ],
            ],
            [
                'name'        => 'Garam Masala',
                'name_nepali' => 'गरम मसाला',
                'name_hindi'  => 'Garam Masala',
                'category'    => 'spice-powders-masala',
                'sku_code'    => 'GRM',
                'variants'    => [
                    ['pack_size' => 50,  'mrp' => 100.00, 'cost' => 60.00],
                    ['pack_size' => 100, 'mrp' => 180.00, 'cost' => 108.00],
                    ['pack_size' => 250, 'mrp' => 420.00, 'cost' => 252.00],
                ],
            ],
            [
                'name'        => 'Lapsi Powder',
                'name_nepali' => 'लप्सी पाउडर',
                'name_hindi'  => 'Lapsi Powder',
                'category'    => 'spice-powders-masala',
                'sku_code'    => 'LPS',
                'variants'    => [
                    ['pack_size' => 100, 'mrp' => 100.00, 'cost' => 60.00],
                    ['pack_size' => 250, 'mrp' => 230.00, 'cost' => 138.00],
                ],
            ],

            // ══ SALTS & SWEETENERS ════════════════════════════════════════════

            [
                'name'        => 'White Salt (Seto Nun)',
                'name_nepali' => 'सेतो नुन',
                'name_hindi'  => 'Seto Nun',
                'category'    => 'salts-sweeteners',
                'sku_code'    => 'SNN',
                'variants'    => [
                    ['pack_size' => 250,  'mrp' => 35.00,  'cost' => 21.00],
                    ['pack_size' => 500,  'mrp' => 60.00,  'cost' => 36.00],
                    ['pack_size' => 1000, 'mrp' => 110.00, 'cost' => 66.00],
                ],
            ],
            [
                'name'        => 'Black Salt (Kalo Nun)',
                'name_nepali' => 'कालो नुन',
                'name_hindi'  => 'Kalo Nun',
                'category'    => 'salts-sweeteners',
                'sku_code'    => 'KNN',
                'variants'    => [
                    ['pack_size' => 100, 'mrp' => 40.00, 'cost' => 24.00],
                    ['pack_size' => 250, 'mrp' => 90.00, 'cost' => 54.00],
                    ['pack_size' => 500, 'mrp' => 160.00, 'cost' => 96.00],
                ],
            ],
            [
                'name'        => 'Jaggery / Sugar (Gulmeli Gud Sakar)',
                'name_nepali' => 'गुलमेली गुड/साकर',
                'name_hindi'  => 'Gulmeli Gud Sakar',
                'category'    => 'salts-sweeteners',
                'sku_code'    => 'GUD',
                'variants'    => [
                    ['pack_size' => 250,  'mrp' => 80.00,  'cost' => 48.00],
                    ['pack_size' => 500,  'mrp' => 150.00, 'cost' => 90.00],
                    ['pack_size' => 1000, 'mrp' => 280.00, 'cost' => 168.00],
                ],
            ],

            // ══ OTHER ITEMS ════════════════════════════════════════════════════

            [
                'name'        => 'Gum Resin (Gond)',
                'name_nepali' => 'गोंद',
                'name_hindi'  => 'Gond',
                'category'    => 'other-items',
                'sku_code'    => 'GND',
                'variants'    => [
                    ['pack_size' => 50,  'mrp' => 120.00, 'cost' => 72.00],
                    ['pack_size' => 100, 'mrp' => 220.00, 'cost' => 132.00],
                    ['pack_size' => 250, 'mrp' => 500.00, 'cost' => 300.00],
                ],
            ],
            [
                'name'        => 'Roasted Chickpeas (Chana Fry)',
                'name_nepali' => 'चना फ्राई',
                'name_hindi'  => 'Chana Fry',
                'category'    => 'other-items',
                'sku_code'    => 'CNF',
                'variants'    => [
                    ['pack_size' => 100, 'mrp' => 50.00,  'cost' => 30.00],
                    ['pack_size' => 250, 'mrp' => 110.00, 'cost' => 66.00],
                    ['pack_size' => 500, 'mrp' => 200.00, 'cost' => 120.00],
                ],
            ],
            [
                'name'        => 'Candles (Mainbatti)',
                'name_nepali' => 'मैनबत्ती',
                'name_hindi'  => 'Mainbatti',
                'category'    => 'other-items',
                'sku_code'    => 'CNL',
                'variants'    => [
                    ['pack_size' => 100, 'mrp' => 120.00, 'cost' => 72.00],
                    ['pack_size' => 250, 'mrp' => 280.00, 'cost' => 168.00],
                ],
            ],
        ];

        foreach ($products as $productData) {
            $category = Category::where('organization_id', $this->org->id)
                ->where('slug', $productData['category'])
                ->first();

            if (! $category) {
                $this->command->warn("  ⚠ Category '{$productData['category']}' not found, skipping {$productData['name']}");
                continue;
            }

            $sku = 'SP-' . $productData['sku_code'];

            $product = Product::firstOrCreate(
                ['organization_id' => $this->org->id, 'sku' => $sku],
                [
                    'category_id'       => $category->id,
                    'name'              => $productData['name'],
                    'name_nepali'       => $productData['name_nepali'] ?? null,
                    'name_romanized'    => $productData['name_romanized'] ?? ($productData['name_hindi'] ?? null),
                    'name_hindi'        => $productData['name_hindi'] ?? null,
                    'sku'               => $sku,
                    'product_type'      => $category->product_type,
                    'unit_type'         => $category->config['unit_type'] ?? 'weight',
                    'has_variants'      => count($productData['variants']) > 1,
                    'tax_category'      => $category->config['tax_category'] ?? 'essential',
                    'requires_batch'    => $category->config['requires_batch'] ?? true,
                    'requires_expiry'   => $category->config['requires_expiry'] ?? true,
                    'shelf_life_months' => $category->config['shelf_life_months'] ?? 24,
                    'active'            => true,
                ]
            );

            foreach ($productData['variants'] as $variantData) {
                $unit = $category->config['default_unit'] ?? 'GMS';
                $variantSku = $sku . '-' . $variantData['pack_size'] . $unit;

                $variant = ProductVariant::firstOrCreate(
                    ['sku' => $variantSku],
                    [
                        'product_id'  => $product->id,
                        'sku'         => $variantSku,
                        'pack_size'   => $variantData['pack_size'],
                        'unit'        => $unit,
                        'mrp_india'   => $variantData['mrp'],
                        'base_price'  => $variantData['mrp'],
                        'cost_price'  => $variantData['cost'],
                        'hsn_code'    => '09109900',
                        'active'      => true,
                    ]
                );

                StockLevel::firstOrCreate(
                    ['product_variant_id' => $variant->id, 'store_id' => $this->store->id],
                    ['quantity' => 0, 'reserved_quantity' => 0, 'reorder_level' => 5, 'last_counted_at' => now()]
                );
            }

            $this->command->line("  ✓ {$productData['name']} (" . count($productData['variants']) . ' variants)');
        }
    }
}

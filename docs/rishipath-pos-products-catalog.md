# Rishipath POS - Product Catalog Seeder

> **Complete product catalog for Rishipath Bhaisajyashala**  
> **Ready to import into Laravel seeders**  
> **Based on actual price list**

---

## 📦 Product Categories Configuration

```php
// database/seeders/ProductCategorySeeder.php

return [
    [
        'name' => 'Ayurvedic Choornas (Powders)',
        'name_nepali' => 'आयुर्वेदिक चूर्ण',
        'name_sanskrit' => 'चूर्णानि',
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
        'name_sanskrit' => 'तैलानि',
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
        'name_sanskrit' => 'घृतानि',
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
        'name' => 'Ayurvedic Rasayanas (Rejuvenatives)',
        'name_nepali' => 'आयुर्वेदिक रसायन',
        'name_sanskrit' => 'रसायनानि',
        'slug' => 'ayurvedic-rasayanas',
        'product_type' => 'rasayana',
        'config' => [
            'unit_type' => 'weight',
            'default_unit' => 'GMS',
            'common_sizes' => [100, 250, 500],
            'tax_category' => 'essential',
            'requires_batch' => true,
            'requires_expiry' => true,
            'shelf_life_months' => 18,
        ],
    ],
    [
        'name' => 'Ayurvedic Capsules & Tablets',
        'name_nepali' => 'आयुर्वेदिक क्याप्सुल',
        'name_sanskrit' => 'वटी',
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
        'name_sanskrit' => 'मधु',
        'slug' => 'honey-sweeteners',
        'product_type' => 'honey',
        'config' => [
            'unit_type' => 'volume',
            'default_unit' => 'ML',
            'common_sizes' => [10, 50, 250, 500],
            'tax_category' => 'essential',
            'requires_batch' => true,
            'requires_expiry' => true,
            'shelf_life_months' => 24,
        ],
    ],
    [
        'name' => 'Pottalis & Herbal Bundles',
        'name_nepali' => 'पोटली',
        'name_sanskrit' => 'पोटली',
        'slug' => 'pottalis',
        'product_type' => 'pottali',
        'config' => [
            'unit_type' => 'weight',
            'default_unit' => 'GMS',
            'common_sizes' => [650],
            'tax_category' => 'standard',
            'requires_batch' => true,
            'requires_expiry' => false,
            'shelf_life_months' => 12,
        ],
    ],
];
```

---

## 🌿 Complete Product Catalog

```php
// database/seeders/RishipathProductSeeder.php

return [
    // ========================================
    // HERBAL TEAS & BEVERAGES
    // ========================================
    [
        'sku' => 'RSH-TEA-001',
        'name' => '100% Pure Organic Assam Tea',
        'category' => 'Herbal Teas & Beverages',
        'product_type' => 'tea',
        'variants' => [
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 200.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 5,
    ],

    // ========================================
    // AYURVEDIC TAILAMS (MEDICATED OILS)
    // ========================================
    [
        'sku' => 'RSH-TL-001',
        'name' => 'Akshi Bindu',
        'name_sanskrit' => 'अक्षि बिन्दु',
        'category' => 'Ayurvedic Tailams',
        'product_type' => 'tailam',
        'variants' => [
            ['pack_size' => 10, 'unit' => 'ML', 'mrp' => 70.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-002',
        'name' => 'Akshi Prashanam',
        'name_sanskrit' => 'अक्षि प्राशनम्',
        'category' => 'Ayurvedic Tailams',
        'product_type' => 'tailam',
        'variants' => [
            ['pack_size' => 250, 'unit' => 'ML', 'mrp' => 320.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-003',
        'name' => 'Almond Oil',
        'category' => 'Ayurvedic Tailams',
        'product_type' => 'tailam',
        'variants' => [
            ['pack_size' => 10, 'unit' => 'ML', 'mrp' => 70.00],
        ],
        'tax_category' => 'standard',
        'tax_rate_india' => 18,
    ],
    [
        'sku' => 'RSH-TL-004',
        'name' => 'Anjanam',
        'name_sanskrit' => 'अञ्जनम्',
        'category' => 'Ayurvedic Tailams',
        'product_type' => 'tailam',
        'variants' => [
            ['pack_size' => 10, 'unit' => 'GMS', 'mrp' => 80.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-005',
        'name' => 'Anu Taila',
        'name_sanskrit' => 'अनु तैलम्',
        'category' => 'Ayurvedic Tailams',
        'product_type' => 'tailam',
        'variants' => [
            ['pack_size' => 10, 'unit' => 'ML', 'mrp' => 180.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-006',
        'name' => 'Asthi Poshaka Ghritam',
        'name_sanskrit' => 'अस्थि पोषक घृतम्',
        'category' => 'Ayurvedic Ghritams',
        'product_type' => 'ghritam',
        'variants' => [
            ['pack_size' => 250, 'unit' => 'ML', 'mrp' => 320.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-007',
        'name' => 'Gargle Mix',
        'category' => 'Ayurvedic Tailams',
        'product_type' => 'tailam',
        'variants' => [
            ['pack_size' => 30, 'unit' => 'GMS', 'mrp' => 90.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-008',
        'name' => 'Jatyadi Ghritam',
        'name_sanskrit' => 'जात्यादि घृतम्',
        'category' => 'Ayurvedic Ghritams',
        'product_type' => 'ghritam',
        'variants' => [
            ['pack_size' => 90, 'unit' => 'ML', 'mrp' => 150.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-009',
        'name' => 'Jatyadi Tailam',
        'name_sanskrit' => 'जात्यादि तैलम्',
        'category' => 'Ayurvedic Tailams',
        'product_type' => 'tailam',
        'variants' => [
            ['pack_size' => 250, 'unit' => 'ML', 'mrp' => 260.00],
            ['pack_size' => 125, 'unit' => 'ML', 'mrp' => 130.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-010',
        'name' => 'Kavala Tailam',
        'name_sanskrit' => 'कवल तैलम्',
        'category' => 'Ayurvedic Tailams',
        'product_type' => 'tailam',
        'variants' => [
            ['pack_size' => 125, 'unit' => 'ML', 'mrp' => 155.00],
            ['pack_size' => 250, 'unit' => 'ML', 'mrp' => 390.00],
            ['pack_size' => 550, 'unit' => 'ML', 'mrp' => 800.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-011',
        'name' => 'Keshyam Tailam',
        'name_sanskrit' => 'केश्यम् तैलम्',
        'category' => 'Ayurvedic Tailams',
        'product_type' => 'tailam',
        'variants' => [
            ['pack_size' => 125, 'unit' => 'ML', 'mrp' => 211.00],
            ['pack_size' => 250, 'unit' => 'ML', 'mrp' => 399.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-012',
        'name' => 'Medha Ghritam',
        'name_sanskrit' => 'मेधा घृतम्',
        'category' => 'Ayurvedic Ghritams',
        'product_type' => 'ghritam',
        'variants' => [
            ['pack_size' => 250, 'unit' => 'ML', 'mrp' => 380.00],
            ['pack_size' => 550, 'unit' => 'ML', 'mrp' => 975.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-013',
        'name' => 'Svarnaprashan',
        'name_sanskrit' => 'स्वर्णप्राशन',
        'category' => 'Ayurvedic Rasayanas',
        'product_type' => 'rasayana',
        'variants' => [
            ['pack_size' => 200, 'unit' => 'ML', 'mrp' => 70.00],
            ['pack_size' => 10, 'unit' => 'ML', 'mrp' => 1100.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-014',
        'name' => 'Tvakdoshahara Ghritam',
        'name_sanskrit' => 'त्वक्दोषहर घृतम्',
        'category' => 'Ayurvedic Ghritams',
        'product_type' => 'ghritam',
        'variants' => [
            ['pack_size' => 125, 'unit' => 'ML', 'mrp' => 190.00],
            ['pack_size' => 250, 'unit' => 'ML', 'mrp' => 380.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-015',
        'name' => 'Tvakdoshahara Tailam',
        'name_sanskrit' => 'त्वक्दोषहर तैलम्',
        'category' => 'Ayurvedic Tailams',
        'product_type' => 'tailam',
        'variants' => [
            ['pack_size' => 125, 'unit' => 'ML', 'mrp' => 211.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-016',
        'name' => 'Vatahar Tailam (Coconut Oil Based)',
        'name_sanskrit' => 'वातहर तैलम्',
        'category' => 'Ayurvedic Tailams',
        'product_type' => 'tailam',
        'variants' => [
            ['pack_size' => 125, 'unit' => 'ML', 'mrp' => 150.00],
            ['pack_size' => 250, 'unit' => 'ML', 'mrp' => 270.00],
            ['pack_size' => 550, 'unit' => 'ML', 'mrp' => 750.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-017',
        'name' => 'Vatahar Tailam (Cold Wood Pressed Sesame Oil Based)',
        'name_sanskrit' => 'वातहर तैलम्',
        'category' => 'Ayurvedic Tailams',
        'product_type' => 'tailam',
        'variants' => [
            ['pack_size' => 250, 'unit' => 'ML', 'mrp' => 310.00],
            ['pack_size' => 550, 'unit' => 'ML', 'mrp' => 899.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-018',
        'name' => 'Vatahara Ghritam',
        'name_sanskrit' => 'वातहर घृतम्',
        'category' => 'Ayurvedic Ghritams',
        'product_type' => 'ghritam',
        'variants' => [
            ['pack_size' => 125, 'unit' => 'ML', 'mrp' => 199.00],
            ['pack_size' => 550, 'unit' => 'ML', 'mrp' => 975.00],
            ['pack_size' => 250, 'unit' => 'ML', 'mrp' => 380.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-TL-019',
        'name' => 'Vedanahari Tailam (Coconut Oil Based)',
        'name_sanskrit' => 'वेदनाहारी तैलम्',
        'category' => 'Ayurvedic Tailams',
        'product_type' => 'tailam',
        'variants' => [
            ['pack_size' => 125, 'unit' => 'ML', 'mrp' => 225.00],
            ['pack_size' => 250, 'unit' => 'ML', 'mrp' => 360.00],
            ['pack_size' => 550, 'unit' => 'ML', 'mrp' => 650.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],

    // ========================================
    // AYURVEDIC CHOORNAS (POWDERS)
    // ========================================
    [
        'sku' => 'RSH-CH-001',
        'name' => 'Akshiprakshala',
        'name_sanskrit' => 'अक्षिप्रक्षाल',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 50, 'unit' => 'GMS', 'mrp' => 90.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-002',
        'name' => 'Amalaki Choorna',
        'name_sanskrit' => 'आमलकी चूर्ण',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 176.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-003',
        'name' => 'Amalaki Rasayana',
        'name_sanskrit' => 'आमलकी रसायन',
        'category' => 'Ayurvedic Rasayanas',
        'product_type' => 'rasayana',
        'variants' => [
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 350.00],
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 380.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-004',
        'name' => 'Ashwagandha Choorna',
        'name_sanskrit' => 'अश्वगंधा चूर्ण',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 150, 'unit' => 'GMS', 'mrp' => 180.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-005',
        'name' => 'Avipattikara Choorna',
        'name_sanskrit' => 'अविपत्तिकर चूर्ण',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 176.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-006',
        'name' => 'Balalakshadi Tailam',
        'name_sanskrit' => 'बाललक्षादि तैलम्',
        'category' => 'Ayurvedic Tailams',
        'product_type' => 'tailam',
        'variants' => [
            ['pack_size' => 100, 'unit' => 'ML', 'mrp' => 120.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-007',
        'name' => 'Chitrak Haritaki',
        'name_sanskrit' => 'चित्रक हरीतकी',
        'category' => 'Ayurvedic Rasayanas',
        'product_type' => 'rasayana',
        'variants' => [
            ['pack_size' => 500, 'unit' => 'GMS', 'mrp' => 700.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-008',
        'name' => 'Dashmoola Kvatha',
        'name_sanskrit' => 'दशमूल क्वाथ',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 50, 'unit' => 'GMS', 'mrp' => 65.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-009',
        'name' => 'Erandadi Choorna',
        'name_sanskrit' => 'एरण्डादि चूर्ण',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 160.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-010',
        'name' => 'Erandahastadi Pottali',
        'name_sanskrit' => 'एरण्डहस्तादि पोटली',
        'category' => 'Pottalis & Herbal Bundles',
        'product_type' => 'pottali',
        'variants' => [
            ['pack_size' => 650, 'unit' => 'GMS', 'mrp' => 180.00],
        ],
        'tax_category' => 'standard',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-011',
        'name' => 'Kapikachchhu Choorna',
        'name_sanskrit' => 'कपिकच्छु चूर्ण',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 200.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-012',
        'name' => 'Kesha Prasadhanam',
        'name_sanskrit' => 'केश प्रसाधनम्',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 130.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-013',
        'name' => 'Liver D-Tox',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 180.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-014',
        'name' => 'Panchakola Choorna',
        'name_sanskrit' => 'पञ्चकोल चूर्ण',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 50, 'unit' => 'GMS', 'mrp' => 96.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-015',
        'name' => 'Parisheka Kvatha',
        'name_sanskrit' => 'परिषेक क्वाथ',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 50, 'unit' => 'GMS', 'mrp' => 100.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-016',
        'name' => 'Paushtik Choorna',
        'name_sanskrit' => 'पौष्टिक चूर्ण',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 350.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-017',
        'name' => 'Pushyanug Choorna',
        'name_sanskrit' => 'पुष्यानुग चूर्ण',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 180.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-018',
        'name' => 'Rechaka Choorna',
        'name_sanskrit' => 'रेचक चूर्ण',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 192.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-PR-001',
        'name' => 'Rishipeya',
        'category' => 'Ayurvedic Rasayanas',
        'product_type' => 'rasayana',
        'variants' => [
            ['pack_size' => 50, 'unit' => 'GMS', 'mrp' => 150.00],
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 250.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-019',
        'name' => 'Sattvam Choorna',
        'name_sanskrit' => 'सत्त्वम् चूर्ण',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 150.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-020',
        'name' => 'Shatavari Choorna',
        'name_sanskrit' => 'शतावरी चूर्ण',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 200.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-VB-001',
        'name' => 'Shuddha Vibhooti',
        'name_sanskrit' => 'शुद्ध विभूति',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 15, 'unit' => 'GMS', 'mrp' => 30.00],
            ['pack_size' => 50, 'unit' => 'GMS', 'mrp' => 80.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 5,
    ],
    [
        'sku' => 'RSH-BH-001',
        'name' => 'Sphatika Bhasma',
        'name_sanskrit' => 'स्फटिक भस्म',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 50, 'unit' => 'GMS', 'mrp' => 200.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-021',
        'name' => 'Stree Rasayan Choorna',
        'name_sanskrit' => 'स्त्री रसायन चूर्ण',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 200.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-GH-001',
        'name' => 'Stree Rasayana Ghritam',
        'name_sanskrit' => 'स्त्री रसायन घृतम्',
        'category' => 'Ayurvedic Ghritams',
        'product_type' => 'ghritam',
        'variants' => [
            ['pack_size' => 250, 'unit' => 'ML', 'mrp' => 380.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-022',
        'name' => 'Tankan Bhasma',
        'name_sanskrit' => 'टंकण भस्म',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 50, 'unit' => 'GMS', 'mrp' => 250.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CH-023',
        'name' => 'Tvak Prasadanam',
        'name_sanskrit' => 'त्वक् प्रसादनम्',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 100, 'unit' => 'GMS', 'mrp' => 160.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CR-001',
        'name' => 'Tvakdoshahara Cream',
        'category' => 'Ayurvedic Creams',
        'product_type' => 'cream',
        'variants' => [
            ['pack_size' => 25, 'unit' => 'GMS', 'mrp' => 120.00],
        ],
        'tax_category' => 'standard',
        'tax_rate_india' => 18,
    ],
    [
        'sku' => 'RSH-CH-024',
        'name' => 'Varnyam',
        'name_sanskrit' => 'वर्ण्यम्',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 30, 'unit' => 'GMS', 'mrp' => 60.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],

    // ========================================
    // CAPSULES & TABLETS
    // ========================================
    [
        'sku' => 'RSH-CP-001',
        'name' => 'Mineral Mix Capsules (Bottle)',
        'category' => 'Ayurvedic Capsules',
        'product_type' => 'capsules',
        'variants' => [
            ['pack_size' => 30, 'unit' => 'CAPSULES', 'mrp' => 144.00, 'packaging' => 'Bottle'],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CP-002',
        'name' => 'Mineral Mix Capsules (Pouch)',
        'category' => 'Ayurvedic Capsules',
        'product_type' => 'capsules',
        'variants' => [
            ['pack_size' => 30, 'unit' => 'CAPSULES', 'mrp' => 120.00, 'packaging' => 'Pouch'],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-PW-001',
        'name' => 'Mineral Mix Powder',
        'category' => 'Ayurvedic Choornas',
        'product_type' => 'choorna',
        'variants' => [
            ['pack_size' => 20, 'unit' => 'GMS', 'mrp' => 90.00],
            ['pack_size' => 50, 'unit' => 'GMS', 'mrp' => 96.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CP-003',
        'name' => 'Vedanahari Capsules (Bottle)',
        'category' => 'Ayurvedic Capsules',
        'product_type' => 'capsules',
        'variants' => [
            ['pack_size' => 30, 'unit' => 'CAPSULES', 'mrp' => 144.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],
    [
        'sku' => 'RSH-CP-004',
        'name' => 'Vedanahari Capsules (Pouch)',
        'category' => 'Ayurvedic Capsules',
        'product_type' => 'capsules',
        'variants' => [
            ['pack_size' => 60, 'unit' => 'CAPSULES', 'mrp' => 240.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 12,
    ],

    // ========================================
    // HONEY & NATURAL PRODUCTS
    // ========================================
    [
        'sku' => 'RSH-HON-001',
        'name' => 'Madhu (Honey)',
        'name_sanskrit' => 'मधु',
        'category' => 'Natural Honey',
        'product_type' => 'honey',
        'variants' => [
            ['pack_size' => 10, 'unit' => 'ML', 'mrp' => 50.00],
        ],
        'tax_category' => 'essential',
        'tax_rate_india' => 5,
    ],
];
```

---

## 🔄 Seeder Implementation

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;

class RishipathProductSeeder extends Seeder
{
    public function run()
    {
        $categories = include database_path('seeders/data/categories.php');
        $products = include database_path('seeders/data/products.php');
        
        // Get default organization (Rishipath)
        $orgId = 1; // Adjust based on your setup
        
        // Seed Categories
        foreach ($categories as $categoryData) {
            Category::create([
                'organization_id' => $orgId,
                ...$categoryData,
            ]);
        }
        
        // Seed Products & Variants
        foreach ($products as $productData) {
            // Get category ID
            $category = Category::where('slug', \Str::slug($productData['category']))->first();
            
            // Create base product
            $product = Product::create([
                'organization_id' => $orgId,
                'category_id' => $category->id,
                'sku' => $productData['sku'],
                'name' => $productData['name'],
                'name_sanskrit' => $productData['name_sanskrit'] ?? null,
                'product_type' => $productData['product_type'],
                'unit_type' => $this->getUnitType($productData['product_type']),
                'has_variants' => count($productData['variants']) > 1,
                'tax_category' => $productData['tax_category'],
                'requires_batch' => true,
                'requires_expiry' => true,
                'active' => true,
            ]);
            
            // Create variants
            foreach ($productData['variants'] as $variantData) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $product->sku . '-' . $variantData['pack_size'],
                    'pack_size' => $variantData['pack_size'],
                    'unit' => $variantData['unit'],
                    'mrp_india' => $variantData['mrp'],
                    'selling_price_nepal' => $this->convertToNepaliPrice($variantData['mrp']),
                    'active' => true,
                ]);
            }
        }
    }
    
    private function getUnitType($productType)
    {
        return match($productType) {
            'choorna', 'rasayana', 'pottali' => 'weight',
            'tailam', 'ghritam', 'honey' => 'volume',
            'capsules' => 'piece',
            default => 'weight',
        };
    }
    
    private function convertToNepaliPrice($inrPrice)
    {
        // INR to NPR conversion rate (approximate 1 INR = 1.6 NPR)
        return round($inrPrice * 1.6, 2);
    }
}
```

---

## ✅ Usage with GitHub Copilot

Save this file and use it as context:

```php
/**
 * COPILOT CONTEXT: Rishipath Product Catalog
 * 
 * This file contains the complete product catalog for Rishipath Bhaisajyashala.
 * 
 * PRODUCT STRUCTURE:
 * - Base Product (common attributes)
 * - Variants (different pack sizes with individual pricing)
 * 
 * CATEGORIES:
 * - Choornas (powders) - weight-based (GMS)
 * - Tailams (oils) - volume-based (ML)
 * - Ghritams (medicated ghee) - volume-based (ML)
 * - Rasayanas (rejuvenatives) - weight/volume
 * - Capsules - piece-based (CAPSULES)
 * - Honey & Teas - various
 * 
 * TAX RATES (India):
 * - Essential items: 5% GST
 * - Ayurvedic medicines: 12% GST
 * - Cosmetics: 18% GST
 */
```

**This catalog is ready to import! 🚀**

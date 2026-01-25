<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\LoyaltyTier;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Terminal;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FullScaleTestSeeder extends Seeder
{
    private $faker;
    private $loyaltyService;

    public function run(): void
    {
        $this->faker = \Faker\Factory::create('en_IN');
        $this->loyaltyService = app(LoyaltyService::class);

        $this->command->info('🚀 Starting full-scale test data seeding...');

        // Get existing Rishipath organization
        $rishipath = Organization::where('slug', 'rishipath')->first();
        if (!$rishipath) {
            $this->command->error('Rishipath organization not found! Please run InitialSetupSeeder first.');
            return;
        }

        // Create or get Shuddhidham organization
        $shuddhidham = $this->createShuddhidhamOrganization();

        // Seed both organizations with full data
        $this->seedOrganizationData($rishipath);
        $this->seedOrganizationData($shuddhidham);

        $this->command->info('✅ Full-scale test data seeding completed!');
    }

    private function createShuddhidhamOrganization(): Organization
    {
        $this->command->info('Creating Shuddhidham organization...');

        $org = Organization::firstOrCreate(
            ['slug' => 'shuddhidham'],
            [
                'name' => 'Shuddhidham Ayurved',
                'legal_name' => 'Shuddhidham Ayurved Pvt. Ltd.',
                'country_code' => 'NP',
                'currency' => 'NPR',
                'timezone' => 'Asia/Kathmandu',
                'locale' => 'en',
                'config' => [
                    'branding' => [
                        'logo_url' => null,
                        'primary_color' => '#059669',
                    ],
                    'features' => [
                        'offline_mode' => true,
                        'multi_currency' => false,
                        'loyalty_program' => true,
                    ],
                    'tax' => [
                        'type' => 'VAT',
                        'rates' => [
                            'standard' => 13,
                        ],
                    ],
                    'receipt' => [
                        'format' => 'SHD-{date}-{number}',
                        'footer_text' => 'धन्यवाद! Thank you!',
                    ],
                ],
                'active' => true,
            ]
        );

        // Create roles for Shuddhidham
        $adminRole = Role::firstOrCreate(
            [
                'organization_id' => $org->id,
                'slug' => 'super-admin',
            ],
            [
                'name' => 'Super Admin',
                'permissions' => ['*'],
                'is_system_role' => true,
            ]
        );

        Role::firstOrCreate(
            [
                'organization_id' => $org->id,
                'slug' => 'cashier',
            ],
            [
                'name' => 'Cashier',
                'permissions' => [
                    'view_dashboard',
                    'create_sales',
                    'view_sales',
                    'view_products',
                    'view_customers',
                ],
                'is_system_role' => true,
            ]
        );

        $this->command->info('✓ Shuddhidham organization created');
        return $org;
    }

    private function seedOrganizationData(Organization $org): void
    {
        $this->command->info("📦 Seeding data for {$org->name}...");

        // Create stores
        $stores = $this->createStores($org);
        
        // Create suppliers
        $suppliers = $this->createSuppliers($org);
        
        // Create categories and products
        $categories = $this->createCategories($org);
        $products = $this->createProducts($org, $categories);
        
        // Create product variants for all products
        $variants = $this->createProductVariants($org, $products);
        
        // Create loyalty tiers
        $this->createLoyaltyTiers($org);
        
        // Create customers
        $customers = $this->createCustomers($org);
        
        // Create cashier users (needed before purchases)
        $cashiers = $this->createCashiers($org, $stores);
        
        // Create purchases (which will create batches when received)
        $this->createPurchases($org, $stores, $suppliers, $variants, $cashiers);
        
        // Create sales transactions
        $this->createSales($org, $stores, $customers, $cashiers, $variants);

        $this->command->info("✅ Completed seeding for {$org->name}");
    }

    private function createStores(Organization $org): array
    {
        $this->command->info("  Creating stores for {$org->name}...");
        
        $stores = [];
        $storeData = $org->country_code === 'IN' ? [
            ['code' => 'MAIN', 'name' => 'Main Store', 'city' => 'Mumbai', 'state' => 'Maharashtra'],
            ['code' => 'MUM', 'name' => 'Mumbai Branch', 'city' => 'Mumbai', 'state' => 'Maharashtra'],
            ['code' => 'DEL', 'name' => 'Delhi Branch', 'city' => 'New Delhi', 'state' => 'Delhi'],
            ['code' => 'BLR', 'name' => 'Bangalore Branch', 'city' => 'Bangalore', 'state' => 'Karnataka'],
        ] : [
            ['code' => 'KTM', 'name' => 'Kathmandu Store', 'city' => 'Kathmandu', 'state' => 'Bagmati'],
            ['code' => 'PKR', 'name' => 'Pokhara Store', 'city' => 'Pokhara', 'state' => 'Gandaki'],
        ];

        foreach ($storeData as $data) {
            $store = Store::firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'code' => $data['code'],
                ],
                [
                    'name' => $data['name'],
                    'address' => $this->faker->streetAddress,
                    'city' => $data['city'],
                    'state' => $data['state'],
                    'country_code' => $org->country_code,
                    'postal_code' => $this->faker->postcode,
                    'phone' => $this->faker->phoneNumber,
                    'email' => strtolower($data['code']) . '@' . Str::slug($org->name) . '.com',
                    'active' => true,
                ]
            );

            // Create terminals for each store
            for ($i = 1; $i <= 2; $i++) {
                Terminal::firstOrCreate(
                    [
                        'store_id' => $store->id,
                        'code' => $data['code'] . '-T' . $i,
                    ],
                    [
                        'name' => "Terminal {$i}",
                        'active' => true,
                    ]
                );
            }

            $stores[] = $store;
        }

        $this->command->info("    ✓ Created " . count($stores) . " stores with terminals");
        return $stores;
    }

    private function createSuppliers(Organization $org): array
    {
        $this->command->info("  Creating suppliers for {$org->name}...");
        
        $suppliers = [];
        $supplierNames = [
            'Himalaya Wellness',
            'Dabur India Ltd',
            'Patanjali Ayurved',
            'Baidyanath Ayurved',
            'Zandu Pharmaceutical',
            'Organic India',
            'Kerala Ayurveda',
            'AVP Coimbatore',
        ];

        foreach ($supplierNames as $index => $name) {
            $supplierCode = $org->slug . '-SUP-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);
            
            $supplier = Supplier::firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'supplier_code' => $supplierCode,
                ],
                [
                    'name' => $name,
                    'email' => Str::slug($name) . '@supplier.com',
                    'phone' => $this->faker->phoneNumber,
                    'address' => $this->faker->address,
                    'active' => true,
                ]
            );
            $suppliers[] = $supplier;
        }

        $this->command->info("    ✓ Created " . count($suppliers) . " suppliers");
        return $suppliers;
    }

    private function createCategories(Organization $org): array
    {
        $this->command->info("  Creating categories for {$org->name}...");
        
        $categories = [];
        $categoryNames = [
            'Churna (Powders)',
            'Ras & Bhasma',
            'Asava & Arishta',
            'Tailam (Oils)',
            'Ghritam (Ghee)',
            'Tablets',
            'Capsules',
            'Personal Care',
        ];

        foreach ($categoryNames as $index => $name) {
            $category = Category::firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'name' => $name,
                ],
                [
                    'slug' => Str::slug($name),
                    'sort_order' => $index,
                    'active' => true,
                ]
            );
            $categories[] = $category;
        }

        $this->command->info("    ✓ Created " . count($categories) . " categories");
        return $categories;
    }

    private function createProducts(Organization $org, array $categories): array
    {
        $this->command->info("  Creating products for {$org->name}...");
        
        $products = [];
        $productData = [
            // Churna
            ['name' => 'Triphala Churna', 'category' => 'Churna (Powders)', 'desc' => 'Digestive health supplement', 'type' => 'churna', 'unit_type' => 'weight'],
            ['name' => 'Ashwagandha Churna', 'category' => 'Churna (Powders)', 'desc' => 'Stress relief and vitality', 'type' => 'churna', 'unit_type' => 'weight'],
            ['name' => 'Brahmi Churna', 'category' => 'Churna (Powders)', 'desc' => 'Memory and cognitive support', 'type' => 'churna', 'unit_type' => 'weight'],
            ['name' => 'Tulsi Churna', 'category' => 'Churna (Powders)', 'desc' => 'Immunity booster', 'type' => 'churna', 'unit_type' => 'weight'],
            // Tablets
            ['name' => 'Amla Tablets', 'category' => 'Tablets', 'desc' => 'Vitamin C rich immunity tablets', 'type' => 'tablet', 'unit_type' => 'piece'],
            ['name' => 'Giloy Tablets', 'category' => 'Tablets', 'desc' => 'Fever and immunity', 'type' => 'tablet', 'unit_type' => 'piece'],
            ['name' => 'Neem Tablets', 'category' => 'Tablets', 'desc' => 'Blood purifier', 'type' => 'tablet', 'unit_type' => 'piece'],
            // Oils
            ['name' => 'Mahanarayana Tailam', 'category' => 'Tailam (Oils)', 'desc' => 'Joint and muscle pain relief', 'type' => 'tailam', 'unit_type' => 'volume'],
            ['name' => 'Kumkumadi Tailam', 'category' => 'Tailam (Oils)', 'desc' => 'Skin brightening oil', 'type' => 'tailam', 'unit_type' => 'volume'],
            ['name' => 'Dhanwantaram Tailam', 'category' => 'Tailam (Oils)', 'desc' => 'Post-natal care oil', 'type' => 'tailam', 'unit_type' => 'volume'],
            // Asava
            ['name' => 'Ashokarishta', 'category' => 'Asava & Arishta', 'desc' => 'Women\'s health tonic', 'type' => 'arishta', 'unit_type' => 'volume'],
            ['name' => 'Arjunarishta', 'category' => 'Asava & Arishta', 'desc' => 'Heart health tonic', 'type' => 'arishta', 'unit_type' => 'volume'],
            ['name' => 'Draksharishta', 'category' => 'Asava & Arishta', 'desc' => 'Digestive and blood tonic', 'type' => 'arishta', 'unit_type' => 'volume'],
            // Capsules
            ['name' => 'Shilajit Capsules', 'category' => 'Capsules', 'desc' => 'Energy and stamina', 'type' => 'capsule', 'unit_type' => 'piece'],
            ['name' => 'Moringa Capsules', 'category' => 'Capsules', 'desc' => 'Nutritional supplement', 'type' => 'capsule', 'unit_type' => 'piece'],
            // Personal Care
            ['name' => 'Ayurvedic Face Wash', 'category' => 'Personal Care', 'desc' => 'Natural face cleanser', 'type' => 'cosmetic', 'unit_type' => 'volume'],
            ['name' => 'Herbal Hair Oil', 'category' => 'Personal Care', 'desc' => 'Hair growth oil', 'type' => 'cosmetic', 'unit_type' => 'volume'],
            ['name' => 'Turmeric Soap', 'category' => 'Personal Care', 'desc' => 'Natural antibacterial soap', 'type' => 'cosmetic', 'unit_type' => 'piece'],
        ];

        foreach ($productData as $index => $data) {
            $category = collect($categories)->first(fn($c) => $c->name === $data['category']);
            
            $sku = strtoupper(substr($org->slug, 0, 3)) . '-PRD-' . str_pad($index + 1, 5, '0', STR_PAD_LEFT);
            
            $product = Product::firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'sku' => $sku,
                ],
                [
                    'name' => $data['name'],
                    'description' => $data['desc'],
                    'category_id' => $category->id,
                    'product_type' => $data['type'],
                    'unit_type' => $data['unit_type'],
                    'has_variants' => true,
                    'active' => true,
                ]
            );
            $products[] = $product;
        }

        $this->command->info("    ✓ Created " . count($products) . " products");
        return $products;
    }

    private function createProductVariants(Organization $org, array $products): array
    {
        $this->command->info("  Creating product variants for {$org->name}...");
        
        $variants = [];
        $variantCount = 0;

        foreach ($products as $product) {
            // Determine pack sizes based on product type
            $packSizes = $this->getPackSizesForProduct($product->name);

            foreach ($packSizes as $sizeData) {
                $sku = $product->sku . '-' . $sizeData['size'] . $sizeData['unit'];
                
                $variant = ProductVariant::firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'sku' => $sku,
                    ],
                    [
                        'pack_size' => $sizeData['size'],
                        'unit' => $sizeData['unit'],
                        'base_price' => $sizeData['price'],
                        'mrp_india' => $sizeData['mrp_india'],
                        'selling_price_nepal' => $sizeData['price_nepal'],
                        'cost_price' => $sizeData['cost'],
                        'active' => true,
                    ]
                );
                $variants[] = $variant;
                $variantCount++;
            }
        }

        $this->command->info("    ✓ Created {$variantCount} product variants");
        return $variants;
    }

    private function getPackSizesForProduct(string $productName): array
    {
        // Return different pack sizes based on product type
        if (str_contains($productName, 'Churna') || str_contains($productName, 'Powder')) {
            return [
                ['size' => 50, 'unit' => 'GMS', 'price' => 120, 'mrp_india' => 150, 'price_nepal' => 200, 'cost' => 80],
                ['size' => 100, 'unit' => 'GMS', 'price' => 220, 'mrp_india' => 275, 'price_nepal' => 380, 'cost' => 150],
                ['size' => 500, 'unit' => 'GMS', 'price' => 980, 'mrp_india' => 1225, 'price_nepal' => 1680, 'cost' => 650],
            ];
        } elseif (str_contains($productName, 'Tablets') || str_contains($productName, 'Capsules')) {
            return [
                ['size' => 30, 'unit' => 'PCS', 'price' => 150, 'mrp_india' => 190, 'price_nepal' => 260, 'cost' => 100],
                ['size' => 60, 'unit' => 'PCS', 'price' => 280, 'mrp_india' => 350, 'price_nepal' => 480, 'cost' => 185],
                ['size' => 120, 'unit' => 'PCS', 'price' => 520, 'mrp_india' => 650, 'price_nepal' => 890, 'cost' => 350],
            ];
        } elseif (str_contains($productName, 'Tailam') || str_contains($productName, 'Oil')) {
            return [
                ['size' => 50, 'unit' => 'ML', 'price' => 180, 'mrp_india' => 225, 'price_nepal' => 310, 'cost' => 120],
                ['size' => 100, 'unit' => 'ML', 'price' => 320, 'mrp_india' => 400, 'price_nepal' => 550, 'cost' => 215],
                ['size' => 200, 'unit' => 'ML', 'price' => 580, 'mrp_india' => 725, 'price_nepal' => 995, 'cost' => 390],
            ];
        } elseif (str_contains($productName, 'Arishta') || str_contains($productName, 'Asava')) {
            return [
                ['size' => 200, 'unit' => 'ML', 'price' => 180, 'mrp_india' => 225, 'price_nepal' => 310, 'cost' => 120],
                ['size' => 450, 'unit' => 'ML', 'price' => 380, 'mrp_india' => 475, 'price_nepal' => 650, 'cost' => 255],
            ];
        } else {
            // Personal care or others
            return [
                ['size' => 100, 'unit' => 'GMS', 'price' => 199, 'mrp_india' => 249, 'price_nepal' => 340, 'cost' => 135],
            ];
        }
    }

    private function createLoyaltyTiers(Organization $org): void
    {
        $this->command->info("  Creating loyalty tiers for {$org->name}...");
        
        $tiers = [
            [
                'name' => 'Bronze',
                'slug' => $org->slug . '-bronze',
                'min_points' => 0,
                'max_points' => 999,
                'points_multiplier' => 1.0,
                'discount_percentage' => 0,
                'order' => 1,
            ],
            [
                'name' => 'Silver',
                'slug' => $org->slug . '-silver',
                'min_points' => 1000,
                'max_points' => 4999,
                'points_multiplier' => 1.25,
                'discount_percentage' => 5,
                'order' => 2,
            ],
            [
                'name' => 'Gold',
                'slug' => $org->slug . '-gold',
                'min_points' => 5000,
                'max_points' => 14999,
                'points_multiplier' => 1.5,
                'discount_percentage' => 10,
                'order' => 3,
            ],
            [
                'name' => 'Platinum',
                'slug' => $org->slug . '-platinum',
                'min_points' => 15000,
                'max_points' => null,
                'points_multiplier' => 2.0,
                'discount_percentage' => 15,
                'order' => 4,
            ],
        ];

        foreach ($tiers as $tierData) {
            LoyaltyTier::firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'slug' => $tierData['slug'],
                ],
                array_merge($tierData, [
                    'badge_color' => $this->getTierColor(str_replace($org->slug . '-', '', $tierData['slug'])),
                    'active' => true,
                ])
            );
        }

        $this->command->info("    ✓ Created loyalty tiers");
    }

    private function getTierColor(string $slug): string
    {
        return match($slug) {
            'bronze' => '#CD7F32',
            'silver' => '#C0C0C0',
            'gold' => '#FFD700',
            'platinum' => '#E5E4E2',
            default => '#94A3B8',
        };
    }

    private function createCustomers(Organization $org): array
    {
        $this->command->info("  Creating customers for {$org->name}...");
        
        // Create new faker instance with unique generator for this org to avoid duplicates
        $faker = \Faker\Factory::create('en_IN');
        
        $customers = [];
        $numCustomers = 150; // Create 150 customers per organization

        for ($i = 0; $i < $numCustomers; $i++) {
            $dob = $faker->dateTimeBetween('-70 years', '-18 years');
            $enrolledInLoyalty = rand(0, 100) < 70; // 70% enrolled in loyalty

            $customerCode = strtoupper(substr($org->slug, 0, 3)) . '-CUST-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
            
            $customer = Customer::firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'customer_code' => $customerCode,
                ],
                [
                    'name' => $faker->name(),
                    'phone' => $org->slug . '-' . $faker->numerify('##########'), // Prefix with org to avoid duplicates
                    'email' => $org->slug . '-cust-' . ($i + 1) . '@' . $faker->freeEmailDomain(), // Org-specific email
                    'address' => $faker->address(),
                    'city' => $faker->city(),
                    'date_of_birth' => $dob->format('Y-m-d'),
                    'birthday' => $dob->format('Y-m-d'),
                    'total_purchases' => 0,
                    'total_spent' => 0,
                    'loyalty_points' => 0,
                    'loyalty_tier_id' => null,
                    'loyalty_enrolled_at' => $enrolledInLoyalty ? now()->subDays(rand(1, 730)) : null,
                    'active' => true,
                ]
            );
            $customers[] = $customer;
        }

        $this->command->info("    ✓ Created {$numCustomers} customers");
        return $customers;
    }

    private function createCashiers(Organization $org, array $stores): array
    {
        $this->command->info("  Creating cashier users for {$org->name}...");
        
        $cashierRole = Role::where('organization_id', $org->id)
            ->where('slug', 'cashier')
            ->first();

        $cashiers = [];
        foreach ($stores as $index => $store) {
            // Create 2-3 cashiers per store
            $numCashiers = rand(2, 3);
            
            for ($i = 1; $i <= $numCashiers; $i++) {
                $email = strtolower($store->code) . "-cashier{$i}@" . Str::slug($org->name) . ".test";
                
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'organization_id' => $org->id,
                        'name' => $this->faker->name(),
                        'password' => bcrypt('password'),
                        'role_id' => $cashierRole->id,
                        'stores' => [$store->id],
                        'active' => true,
                    ]
                );
                $cashiers[] = $user;
            }
        }

        $this->command->info("    ✓ Created " . count($cashiers) . " cashier users");
        return $cashiers;
    }

    private function createPurchases(Organization $org, array $stores, array $suppliers, array $variants, array $cashiers): void
    {
        $this->command->info("  Creating purchase orders for {$org->name}...");
        
        $purchaseCount = 0;
        $purchaseItemsCount = 0;

        // Create 50-80 purchases spread across last 90 days
        $numPurchases = rand(50, 80);

        for ($i = 0; $i < $numPurchases; $i++) {
            $store = $this->faker->randomElement($stores);
            $supplier = $this->faker->randomElement($suppliers);
            $cashier = $this->faker->randomElement($cashiers);
            
            $purchaseDate = now()->subDays(rand(0, 90));
            $expectedDeliveryDate = $purchaseDate->copy()->addDays(rand(3, 15));
            
            // Randomly decide if this purchase has been received
            $isReceived = rand(0, 100) < 70; // 70% received
            $receivedDate = $isReceived ? $purchaseDate->copy()->addDays(rand(3, 10)) : null;
            
            // Randomly decide payment status
            $paymentStatus = $this->faker->randomElement(['unpaid', 'paid', 'partial']);
            
            $status = $isReceived ? 'received' : ($receivedDate ? 'received' : 'ordered');
            
            // Calculate amounts FIRST before creating purchase
            $numItems = rand(3, 10);
            $subtotal = 0;
            $selectedVariants = $this->faker->randomElements($variants, min($numItems, count($variants)));
            
            $purchaseItems = [];
            foreach ($selectedVariants as $variant) {
                $quantity = rand(20, 200);
                $costPrice = $variant->cost_price ?? ($variant->base_price * 0.7);
                $itemTotal = $costPrice * $quantity;
                $subtotal += $itemTotal;

                $taxRate = $org->country_code === 'IN' ? 12.00 : 13.00;
                $taxAmount = round($itemTotal * $taxRate / 100, 2);

                $purchaseItems[] = [
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'product_sku' => $variant->sku,
                    'quantity_ordered' => $quantity,
                    'quantity_received' => 0,
                    'unit' => $variant->unit,
                    'unit_cost' => $costPrice,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => 0,
                    'line_total' => $itemTotal + $taxAmount,
                    'created_at' => $purchaseDate,
                    'updated_at' => $purchaseDate,
                ];
            }

            $taxRate = $org->country_code === 'IN' ? 0.12 : 0.13;
            $taxAmount = round($subtotal * $taxRate, 2);
            $shippingCost = rand(0, 1) ? rand(100, 500) : 0;
            $totalAmount = $subtotal + $taxAmount + $shippingCost;
            
            $amountPaid = match($paymentStatus) {
                'paid' => $totalAmount,
                'partial' => round($totalAmount * rand(30, 70) / 100, 2),
                default => 0,
            };
            
            // NOW create purchase with all calculated amounts
            $purchase = Purchase::create([
                'organization_id' => $org->id,
                'store_id' => $store->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => $purchaseDate,
                'expected_delivery_date' => $expectedDeliveryDate,
                'received_date' => $receivedDate,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'supplier_invoice_number' => 'INV-' . strtoupper(Str::random(8)),
                'shipping_cost' => $shippingCost,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $totalAmount,
                'amount_paid' => $amountPaid,
                'notes' => rand(0, 100) < 30 ? $this->faker->sentence() : null,
                'created_by' => $cashier->id,
                'received_by' => $isReceived ? $cashier->id : null,
            ]);

            // Insert purchase items
            foreach ($purchaseItems as $item) {
                $item['purchase_id'] = $purchase->id;
                \DB::table('purchase_items')->insert($item);
                $purchaseItemsCount++;
            }

            // If the purchase is received, call the receive method to create batches
            if ($isReceived && $receivedDate) {
                try {
                    $purchase->status = 'received';
                    $purchase->received_date = $receivedDate;
                    $purchase->received_by = $cashier->id;
                    $purchase->save();
                    
                    // This will trigger batch creation
                    $purchase->receive(userId: $cashier->id);
                } catch (\Throwable $e) {
                    $this->command->warn("    ! Failed to receive purchase {$purchase->purchase_number}: " . $e->getMessage());
                }
            }

            $purchaseCount++;
        }

        $this->command->info("    ✓ Created {$purchaseCount} purchases with {$purchaseItemsCount} items");
    }

    private function createSales(Organization $org, array $stores, array $customers, array $cashiers, array $variants): void
    {
        $this->command->info("  Creating sales transactions for {$org->name}...");
        
        $salesCount = 0;
        $saleItemsCount = 0;

        // Create 300-500 sales spread across last 90 days
        $numSales = rand(300, 500);

        for ($i = 0; $i < $numSales; $i++) {
            $store = $this->faker->randomElement($stores);
            $terminal = $store->terminals->random();
            $cashier = collect($cashiers)->filter(fn($c) => in_array($store->id, $c->stores))->random();
            $customer = rand(0, 100) < 80 ? $this->faker->randomElement($customers) : null; // 80% with customer
            
            $saleDate = now()->subDays(rand(0, 90));
            
            // Get variants available in this store
            $storeVariants = ProductBatch::where('store_id', $store->id)
                ->where('quantity_remaining', '>', 0)
                ->with('productVariant')
                ->get()
                ->pluck('productVariant')
                ->unique('id');

            if ($storeVariants->count() === 0) continue;

            // Select items and calculate totals BEFORE creating sale
            $numItems = rand(1, 5);
            $selectedVariants = $storeVariants->random(min($numItems, $storeVariants->count()));
            $subtotal = 0;
            $saleItems = [];

            foreach ($selectedVariants as $variant) {
                $quantity = rand(1, 3);
                $price = $org->country_code === 'IN' ? $variant->mrp_india : $variant->selling_price_nepal;
                $itemTotal = $price * $quantity;
                $subtotal += $itemTotal;

                $taxRate = $org->country_code === 'IN' ? 12.00 : 13.00;
                $taxAmount = round($itemTotal * $taxRate / 100, 2);

                $saleItems[] = [
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'product_sku' => $variant->sku,
                    'quantity' => $quantity,
                    'unit' => $variant->unit,
                    'price_per_unit' => $price,
                    'cost_price' => $variant->cost_price ?? 0,
                    'subtotal' => $itemTotal,
                    'discount_amount' => 0,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $itemTotal + $taxAmount,
                    'created_at' => $saleDate,
                    'updated_at' => $saleDate,
                ];
            }

            $taxRate = $org->country_code === 'IN' ? 0.12 : 0.13;
            $taxAmount = round($subtotal * $taxRate, 2);
            $totalAmount = $subtotal + $taxAmount;

            // Create sale with calculated amounts
            $sale = Sale::create([
                'organization_id' => $org->id,
                'store_id' => $store->id,
                'terminal_id' => $terminal->id,
                'receipt_number' => $this->generateReceiptNumber($org, $saleDate),
                'date' => $saleDate->toDateString(),
                'time' => $saleDate->format('H:i:s'),
                'cashier_id' => $cashier->id,
                'customer_id' => $customer?->id,
                'customer_name' => $customer?->name,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => 0,
                'total_amount' => $totalAmount,
                'payment_method' => $this->faker->randomElement(['cash', 'card', 'upi', 'esewa', 'khalti']),
                'payment_status' => 'paid',
                'amount_paid' => $totalAmount,
            ]);

            // Insert sale items
            foreach ($saleItems as $item) {
                $item['sale_id'] = $sale->id;
                \DB::table('sale_items')->insert($item);
                $saleItemsCount++;
            }

            // Award loyalty points if customer exists and is enrolled
            if ($customer && $customer->loyalty_enrolled_at) {
                try {
                    $this->loyaltyService->awardPointsForSale($sale);
                } catch (\Throwable $e) {
                    // Ignore errors
                }
            }

            $salesCount++;
        }

        $this->command->info("    ✓ Created {$salesCount} sales with {$saleItemsCount} items");
    }

    private function generateReceiptNumber(Organization $org, $date): string
    {
        $prefix = $org->country_code === 'IN' ? 'RSH' : 'SHD';
        return $prefix . '-' . $date->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}

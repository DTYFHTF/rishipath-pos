<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\Store;
use App\Models\Terminal;
use App\Models\User;
use App\Models\LoyaltyTier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialSetupSeeder extends Seeder
{
    public function run(): void
    {
        // Create or update Rishipath Organization (idempotent)
        $org = Organization::updateOrCreate([
            'slug' => 'rishipath',
        ], [
            'name' => 'Shuddhidham Ayurveda & Yoga-Wellness Suppliers',
            'legal_name' => 'Shuddhidham Ayurveda & Yoga-Wellness Suppliers',
            'country_code' => 'NP',
            'currency' => 'NPR',
            'timezone' => 'Asia/Kathmandu',
            'locale' => 'en',
            'config' => [
                'branding' => [
                    'logo_url' => null,
                    'primary_color' => '#10b981',
                ],
                'features' => [
                    'offline_mode' => true,
                    'multi_currency' => false,
                    'loyalty_program' => false,
                ],
                'tax' => [
                    'type' => 'PAN',
                    'business_tax_number' => '622513573',
                    'sales_bill_type' => 'PAN',
                    'purchase_bill_type' => 'VAT',
                    'rates' => [
                        // PAN billing: no VAT added on normal sales bills.
                        'essential' => 0,
                        'standard' => 0,
                        'luxury' => 0,
                    ],
                ],
                'receipt' => [
                    'format' => 'RSH-{date}-{number}',
                    'footer_text' => 'Thank you for your purchase!',
                ],
            ],
            'active' => true,
        ]);

        // Create Admin Role (idempotent)
        $adminRole = Role::updateOrCreate(
            ['organization_id' => $org->id, 'slug' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'permissions' => [
                    // Dashboard & Analytics
                    'view_dashboard',
                    'view_pos_stats',
                    'view_inventory_overview',
                    'view_low_stock_alerts',
                    
                    // POS Operations
                    'access_pos_billing',
                    'create_sales',
                    'void_sales',
                    'apply_discounts',
                    'process_refunds',
                    'view_sales',
                    
                    // Product Management
                    'view_products',
                    'create_products',
                    'edit_products',
                    'delete_products',
                    'view_product_variants',
                    'create_product_variants',
                    'edit_product_variants',
                    'delete_product_variants',
                    'view_categories',
                    'create_categories',
                    'edit_categories',
                    'delete_categories',
                    
                    // Inventory Management
                    'view_inventory',
                    'view_stock_levels',
                    'view_product_batches',
                    'create_product_batches',
                    'edit_product_batches',
                    'delete_product_batches',
                    'adjust_stock',
                    'view_stock_adjustments',
                    'view_inventory_movements',
                    
                    // Purchase Management
                    'view_purchases',
                    'create_purchases',
                    'edit_purchases',
                    'delete_purchases',
                    'approve_purchases',
                    'receive_purchases',
                    
                    // Supplier Management
                    'view_suppliers',
                    'create_suppliers',
                    'edit_suppliers',
                    'delete_suppliers',
                    
                    // Customer Management
                    'view_customers',
                    'create_customers',
                    'edit_customers',
                    'delete_customers',
                    'view_customer_purchase_history',
                    
                    // Reporting
                    'view_sales_reports',
                    'view_inventory_reports',
                    'view_profit_reports',
                    'view_customer_ledger',
                    'view_supplier_ledger',
                    'export_reports',
                    'email_reports',
                    
                    // Loyalty Program
                    'view_loyalty_program',
                    'manage_loyalty_tiers',
                    'manage_loyalty_points',
                    'manage_rewards',
                    
                    // User Management
                    'view_users',
                    'create_users',
                    'edit_users',
                    'delete_users',
                    'manage_user_permissions',
                    
                    // Role Management
                    'view_roles',
                    'create_roles',
                    'edit_roles',
                    'delete_roles',
                    
                    // Settings & Configuration
                    'view_settings',
                    'edit_settings',
                    'view_organizations',
                    'edit_organizations',
                    'view_stores',
                    'create_stores',
                    'edit_stores',
                    'delete_stores',
                    'view_terminals',
                    'create_terminals',
                    'edit_terminals',
                    'delete_terminals',
                    
                    // System Administration
                    'access_system_logs',
                    'manage_backups',
                    'manage_integrations',
                ],
                'is_system_role' => true,
            ]
        );

        // Create Cashier Role (idempotent)
        Role::updateOrCreate(
            ['organization_id' => $org->id, 'slug' => 'cashier'],
            [
                'name' => 'Cashier',
                'permissions' => [
                    // POS Operations
                    'access_pos_billing',
                    'create_sales',
                    'view_own_sales_only',
                    
                    // Product Management (read-only)
                    'view_products',
                    'view_product_variants',
                    
                    // Customer Management
                    'view_customers',
                    'create_customers',
                    
                    // Basic Inventory
                    'view_inventory',
                    'view_stock_levels',
                ],
                'is_system_role' => true,
            ]
        );

        // Create Main Store (idempotent)
        $store = Store::updateOrCreate(
            ['organization_id' => $org->id, 'code' => 'MAIN'],
            [
                'name' => 'Main Store',
                'address' => 'Kalimati, Kathmandu',
                'city' => 'Kathmandu',
                'state' => 'Bagmati',
                'country_code' => 'NP',
                'postal_code' => '44614',
                'phone' => '+977-9808450422',
                'email' => 'store@shuddhidham.com',
                'tax_number' => '622513573',
                'config' => [
                    'hours' => [
                        'monday' => ['open' => '09:00', 'close' => '18:00'],
                        'tuesday' => ['open' => '09:00', 'close' => '18:00'],
                        'wednesday' => ['open' => '09:00', 'close' => '18:00'],
                        'thursday' => ['open' => '09:00', 'close' => '18:00'],
                        'friday' => ['open' => '09:00', 'close' => '18:00'],
                        'saturday' => ['open' => '09:00', 'close' => '14:00'],
                        'sunday' => ['closed' => true],
                    ],
                ],
                'active' => true,
            ]
        );

        // Create Terminal (idempotent)
        Terminal::updateOrCreate(
            ['store_id' => $store->id, 'code' => 'POS-01'],
            [
                'name' => 'Main Counter',
                'device_id' => 'MAIN-POS-001',
                'printer_config' => [
                    'type' => 'thermal',
                    'width' => 80,
                    'connection' => 'usb',
                ],
                'active' => true,
            ]
        );

        // Create Admin User (idempotent)
        User::updateOrCreate(
            ['organization_id' => $org->id, 'email' => env('ADMIN_EMAIL', 'admin@rishipath.org')],
            [
                'name' => env('ADMIN_NAME', 'Admin User'),
                'phone' => env('ADMIN_PHONE', '+977-9808450422'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'pin' => env('ADMIN_PIN', '1234'),
                'role_id' => $adminRole->id,
                'stores' => [$store->id],
                'permissions' => null,
                'active' => true,
            ]
        );

        // Seed default loyalty tiers for this organization (idempotent)
        $tiers = [
            [
                'slug' => 'bronze',
                'name' => 'Bronze',
                'min_points' => 0,
                'max_points' => 999,
                'points_multiplier' => 1.0,
                'discount_percentage' => 0,
                'benefits' => ['Basic member benefits'],
                'badge_color' => '#CD7F32',
                'order' => 1,
                'active' => true,
            ],
            [
                'slug' => 'silver',
                'name' => 'Silver',
                'min_points' => 1000,
                'max_points' => 4999,
                'points_multiplier' => 1.25,
                'discount_percentage' => 5,
                'benefits' => ['5% store discount', 'Birthday bonus points'],
                'badge_color' => '#C0C0C0',
                'order' => 2,
                'active' => true,
            ],
            [
                'slug' => 'gold',
                'name' => 'Gold',
                'min_points' => 5000,
                'max_points' => 14999,
                'points_multiplier' => 1.5,
                'discount_percentage' => 10,
                'benefits' => ['10% store discount', 'Free shipping', 'Priority support'],
                'badge_color' => '#FFD700',
                'order' => 3,
                'active' => true,
            ],
            [
                'slug' => 'platinum',
                'name' => 'Platinum',
                'min_points' => 15000,
                'max_points' => null,
                'points_multiplier' => 2.0,
                'discount_percentage' => 15,
                'benefits' => ['15% store discount', 'Express shipping', 'VIP events'],
                'badge_color' => '#E5E4E2',
                'order' => 4,
                'active' => true,
            ],
        ];

        foreach ($tiers as $attrs) {
            $attrs['organization_id'] = $org->id;
            LoyaltyTier::updateOrCreate(
                ['organization_id' => $org->id, 'slug' => $attrs['slug']],
                $attrs
            );
        }

        $this->command->info('✅ Initial setup complete!');
        $this->command->info('📧 Email: admin@rishipath.org');
        $this->command->info('🔑 Password: password');
        $this->command->info('📍 Organization: '.$org->name);
        $this->command->info('🧾 PAN: 622513573');
        $this->command->info('🏪 Store: '.$store->name);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * UserRoleSeeder - Creates test users with different roles
 *
 * This seeder creates:
 * - 1 Super Admin (full access)
 * - 1 Store Manager (most features except system admin)
 * - 2 Cashiers (POS only)
 * - 1 Inventory Clerk (inventory management)
 * - 1 Accountant (reports only)
 *
 * Run with: php artisan db:seed --class=UserRoleSeeder
 */
class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure we have an organization and store
        $organization = Organization::firstOrCreate(
            ['slug' => 'rishipath'],
            [
                'name' => 'Rishipath Ayurveda',
                'legal_name' => 'Rishipath Ayurveda Pvt Ltd',
                'country_code' => 'NP',
                'currency' => 'NPR',
                'timezone' => 'Asia/Kathmandu',
                'locale' => 'ne',
                'active' => true,
            ]
        );

        $store = Store::firstOrCreate(
            ['code' => 'MAIN01', 'organization_id' => $organization->id],
            [
                'name' => 'Main Store',
                'address' => 'Downtown Kathmandu',
                'city' => 'Kathmandu',
                'country_code' => 'NP',
                'phone' => '+977-1-4567891',
                'active' => true,
            ]
        );

        // Define all permissions for super admin
        $allPermissions = [
            // Dashboard
            'view_dashboard', 'view_pos_stats', 'view_inventory_overview', 'view_low_stock_alerts',
            // POS
            'access_pos_billing', 'create_sales', 'void_sales', 'apply_discounts', 'process_refunds', 'view_sales', 'view_own_sales_only',
            // Products
            'view_products', 'create_products', 'edit_products', 'delete_products',
            'view_product_variants', 'create_product_variants', 'edit_product_variants', 'delete_product_variants',
            'view_categories', 'create_categories', 'edit_categories', 'delete_categories',
            // Inventory
            'view_inventory', 'view_stock_levels', 'view_product_batches', 'create_product_batches',
            'edit_product_batches', 'delete_product_batches', 'adjust_stock', 'view_stock_adjustments',
            'view_inventory_movements', 'view_suppliers', 'create_suppliers', 'edit_suppliers', 'delete_suppliers',
            // Customers
            'view_customers', 'create_customers', 'edit_customers', 'delete_customers', 'view_customer_purchase_history',
            // Reports
            'view_sales_reports', 'view_inventory_reports', 'view_profit_reports', 'export_reports', 'email_reports',
            // Users
            'view_users', 'create_users', 'edit_users', 'delete_users',
            // Roles
            'view_roles', 'create_roles', 'edit_roles', 'delete_roles',
            // Settings
            'view_settings', 'edit_settings', 'view_stores', 'create_stores', 'edit_stores', 'delete_stores',
            // Loyalty
            'view_loyalty_program', 'manage_loyalty_program',
        ];

        // Create roles
        $roles = $this->createRoles($organization, $allPermissions);

        // Create test users
        $this->createTestUsers($organization, $store, $roles);

        $this->command->info('✅ UserRoleSeeder completed successfully!');
        $this->command->info('');
        $this->command->table(
            ['Email', 'Password', 'Role', 'Purpose'],
            [
                ['admin@rishipath.com', 'admin123', 'Super Admin', 'Full system access'],
                ['manager@rishipath.com', 'manager123', 'Manager', 'Store operations'],
                ['cashier1@rishipath.com', 'cashier123', 'Cashier', 'POS only'],
                ['cashier2@rishipath.com', 'cashier123', 'Cashier', 'POS only (alt)'],
                ['inventory@rishipath.com', 'inventory123', 'Inventory Clerk', 'Stock management'],
                ['accountant@rishipath.com', 'accountant123', 'Accountant', 'Reports only'],
            ]
        );
    }

    /**
     * Create or update roles with permissions
     */
    private function createRoles(Organization $organization, array $allPermissions): array
    {
        $roles = [];

        // Super Admin - All permissions
        $roles['super-admin'] = Role::updateOrCreate(
            ['slug' => 'super-admin', 'organization_id' => $organization->id],
            [
                'name' => 'Super Administrator',
                'permissions' => $allPermissions,
                'is_system_role' => true,
            ]
        );

        // Manager - Most permissions except role/user management
        $roles['manager'] = Role::updateOrCreate(
            ['slug' => 'manager', 'organization_id' => $organization->id],
            [
                'name' => 'Store Manager',
                'permissions' => array_diff($allPermissions, [
                    'view_roles', 'create_roles', 'edit_roles', 'delete_roles',
                    'delete_users', 'view_settings', 'edit_settings',
                    'create_stores', 'edit_stores', 'delete_stores',
                ]),
                'is_system_role' => true,
            ]
        );

        // Cashier - POS only
        $roles['cashier'] = Role::updateOrCreate(
            ['slug' => 'cashier', 'organization_id' => $organization->id],
            [
                'name' => 'Cashier',
                'permissions' => [
                    'access_pos_billing', 'create_sales', 'apply_discounts',
                    'view_own_sales_only', 'view_products', 'view_product_variants',
                    'view_categories', 'view_inventory', 'view_stock_levels',
                    'view_customers', 'create_customers', 'view_loyalty_program',
                ],
                'is_system_role' => true,
            ]
        );

        // Inventory Clerk - Inventory management
        $roles['inventory-clerk'] = Role::updateOrCreate(
            ['slug' => 'inventory-clerk', 'organization_id' => $organization->id],
            [
                'name' => 'Inventory Clerk',
                'permissions' => [
                    'view_dashboard', 'view_inventory_overview', 'view_low_stock_alerts',
                    'view_products', 'view_product_variants', 'view_categories',
                    'view_inventory', 'view_stock_levels', 'view_product_batches',
                    'create_product_batches', 'edit_product_batches',
                    'adjust_stock', 'view_stock_adjustments', 'view_inventory_movements',
                    'view_suppliers', 'create_suppliers', 'edit_suppliers',
                    'view_inventory_reports',
                ],
                'is_system_role' => true,
            ]
        );

        // Accountant - Reports only
        $roles['accountant'] = Role::updateOrCreate(
            ['slug' => 'accountant', 'organization_id' => $organization->id],
            [
                'name' => 'Accountant',
                'permissions' => [
                    'view_dashboard', 'view_pos_stats', 'view_inventory_overview',
                    'view_sales', 'view_products', 'view_product_variants',
                    'view_categories', 'view_inventory', 'view_stock_levels',
                    'view_customers', 'view_customer_purchase_history',
                    'view_sales_reports', 'view_inventory_reports', 'view_profit_reports',
                    'export_reports', 'email_reports',
                ],
                'is_system_role' => true,
            ]
        );

        return $roles;
    }

    /**
     * Create test users with different roles
     */
    private function createTestUsers(Organization $organization, Store $store, array $roles): void
    {
        // Super Admin
        User::updateOrCreate(
            ['email' => 'admin@rishipath.com'],
            [
                'organization_id' => $organization->id,
                'name' => 'System Admin',
                'phone' => '+977-9800000001',
                'password' => Hash::make('admin123'),
                'pin' => Hash::make('1234'),
                'role_id' => $roles['super-admin']->id,
                'stores' => [$store->id],
                'permissions' => [],
                'active' => true,
            ]
        );

        // Store Manager
        User::updateOrCreate(
            ['email' => 'manager@rishipath.com'],
            [
                'organization_id' => $organization->id,
                'name' => 'Store Manager',
                'phone' => '+977-9800000002',
                'password' => Hash::make('manager123'),
                'pin' => Hash::make('2345'),
                'role_id' => $roles['manager']->id,
                'stores' => [$store->id],
                'permissions' => [],
                'active' => true,
            ]
        );

        // Cashier 1
        User::updateOrCreate(
            ['email' => 'cashier1@rishipath.com'],
            [
                'organization_id' => $organization->id,
                'name' => 'Cashier One',
                'phone' => '+977-9800000003',
                'password' => Hash::make('cashier123'),
                'pin' => Hash::make('3456'),
                'role_id' => $roles['cashier']->id,
                'stores' => [$store->id],
                'permissions' => [],
                'active' => true,
            ]
        );

        // Cashier 2
        User::updateOrCreate(
            ['email' => 'cashier2@rishipath.com'],
            [
                'organization_id' => $organization->id,
                'name' => 'Cashier Two',
                'phone' => '+977-9800000004',
                'password' => Hash::make('cashier123'),
                'pin' => Hash::make('4567'),
                'role_id' => $roles['cashier']->id,
                'stores' => [$store->id],
                'permissions' => [],
                'active' => true,
            ]
        );

        // Inventory Clerk
        User::updateOrCreate(
            ['email' => 'inventory@rishipath.com'],
            [
                'organization_id' => $organization->id,
                'name' => 'Inventory Clerk',
                'phone' => '+977-9800000005',
                'password' => Hash::make('inventory123'),
                'pin' => Hash::make('5678'),
                'role_id' => $roles['inventory-clerk']->id,
                'stores' => [$store->id],
                'permissions' => [],
                'active' => true,
            ]
        );

        // Accountant
        User::updateOrCreate(
            ['email' => 'accountant@rishipath.com'],
            [
                'organization_id' => $organization->id,
                'name' => 'Staff Accountant',
                'phone' => '+977-9800000006',
                'password' => Hash::make('accountant123'),
                'pin' => Hash::make('6789'),
                'role_id' => $roles['accountant']->id,
                'stores' => [$store->id],
                'permissions' => [],
                'active' => true,
            ]
        );
    }
}

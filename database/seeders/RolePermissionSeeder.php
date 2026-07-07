<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Complete permissions structure for Rishipath POS
     */
    private function getAllPermissions(): array
    {
        return [
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
            'view_own_sales_only',

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
            'transfer_stock',
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
            'record_customer_payments',

            // Sales Agents
            'view_sales_agents',
            'manage_sales_agents',

            // Field Sales (retail stores, bulk orders, feedback)
            'view_retail_stores',
            'create_retail_stores',
            'edit_retail_stores',
            'delete_retail_stores',
            'view_bulk_order_inquiries',
            'create_bulk_order_inquiries',
            'edit_bulk_order_inquiries',
            'delete_bulk_order_inquiries',
            'view_feedbacks',
            'create_feedbacks',
            'edit_feedbacks',
            'delete_feedbacks',

            // Ingredient Knowledge Base
            'view_ingredients',
            'manage_ingredients',

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
        ];
    }

    /**
     * Canonical (reduced) permission allow-list for the Sales Agent role.
     * Also used by ShuddhidhamUsersSeeder so the two seeders never drift.
     */
    public static function getSalesAgentPermissions(): array
    {
        return [
            // Dashboard (limited view — most widgets require extra permissions)
            'view_dashboard',

            // POS — core function
            'access_pos_billing',
            'create_sales',
            'apply_discounts',
            'view_own_sales_only',

            // Products (read-only for POS search)
            'view_products',
            'view_product_variants',
            'view_categories',

            // Customers (create & manage their own) + credit collection
            'view_customers',
            'create_customers',
            'edit_customers',
            'view_customer_purchase_history',
            'view_customer_ledger',
            'record_customer_payments',

            // Field sales — retail visits, bulk order leads, customer feedback
            'view_retail_stores',
            'create_retail_stores',
            'view_bulk_order_inquiries',
            'create_bulk_order_inquiries',
            'view_feedbacks',
            'create_feedbacks',

            // Loyalty (apply rewards at checkout)
            'view_loyalty_program',

            // Ingredient Knowledge Base (read-only selling aid)
            'view_ingredients',
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create the main organization
        $organization = Organization::first();

        if (! $organization) {
            $this->command->error('No organization found. Please run DatabaseSeeder first.');

            return;
        }

        $this->command->info('Creating roles and permissions for: '.$organization->name);

        // Define predefined roles with their permissions
        $roles = [
            [
                'name' => 'Super Administrator',
                'slug' => 'super-admin',
                'permissions' => $this->getAllPermissions(), // All permissions
                'is_system_role' => true,
            ],
            [
                'name' => 'Store Manager',
                'slug' => 'manager',
                'permissions' => [
                    // Dashboard
                    'view_dashboard',
                    'view_pos_stats',
                    'view_inventory_overview',
                    'view_low_stock_alerts',

                    // POS
                    'access_pos_billing',
                    'create_sales',
                    'void_sales',
                    'apply_discounts',
                    'process_refunds',
                    'view_sales',

                    // Products
                    'view_products',
                    'create_products',
                    'edit_products',
                    'view_product_variants',
                    'create_product_variants',
                    'edit_product_variants',
                    'view_categories',
                    'create_categories',
                    'edit_categories',

                    // Inventory
                    'view_inventory',
                    'view_stock_levels',
                    'view_product_batches',
                    'create_product_batches',
                    'edit_product_batches',
                    'adjust_stock',
                    'transfer_stock',
                    'view_stock_adjustments',
                    'view_inventory_movements',

                    // Purchasing (managers must be able to run purchases)
                    'view_purchases',
                    'create_purchases',
                    'edit_purchases',
                    'approve_purchases',
                    'receive_purchases',

                    // Suppliers
                    'view_suppliers',
                    'create_suppliers',
                    'edit_suppliers',

                    // Customers
                    'view_customers',
                    'create_customers',
                    'edit_customers',
                    'view_customer_purchase_history',
                    'view_customer_ledger',
                    'view_supplier_ledger',
                    'record_customer_payments',

                    // Sales agents & field sales
                    'view_sales_agents',
                    'manage_sales_agents',
                    'view_retail_stores',
                    'create_retail_stores',
                    'edit_retail_stores',
                    'view_bulk_order_inquiries',
                    'create_bulk_order_inquiries',
                    'edit_bulk_order_inquiries',
                    'view_feedbacks',
                    'edit_feedbacks',

                    // Ingredient Knowledge Base
                    'view_ingredients',
                    'manage_ingredients',

                    // Reporting
                    'view_sales_reports',
                    'view_inventory_reports',
                    'view_profit_reports',
                    'export_reports',

                    // Users (limited)
                    'view_users',
                    'create_users',
                    'edit_users',

                    // Settings (limited)
                    'view_settings',
                    'view_stores',
                    'view_terminals',
                ],
                'is_system_role' => true,
            ],
            [
                'name' => 'Cashier',
                'slug' => 'cashier',
                'permissions' => [
                    // POS (main function)
                    'access_pos_billing',
                    'create_sales',
                    'apply_discounts',
                    'view_own_sales_only',

                    // Products (view only)
                    'view_products',
                    'view_product_variants',

                    // Customers
                    'view_customers',
                    'create_customers',
                    'view_customer_purchase_history',
                    'record_customer_payments',

                    // Loyalty (apply rewards at checkout)
                    'view_loyalty_program',
                ],
                'is_system_role' => true,
            ],
            [
                'name' => 'Marketing',
                'slug' => 'marketing',
                'permissions' => [
                    'view_dashboard',
                    'view_products',
                    'view_product_variants',
                    'view_categories',
                    'view_customers',
                    'create_customers',
                    'edit_customers',
                    'view_customer_purchase_history',
                    'view_sales_reports',
                    // Loyalty program management
                    'view_loyalty_program',
                    'manage_loyalty_tiers',
                    'manage_loyalty_points',
                    'manage_rewards',
                    // Field sales & knowledge base
                    'view_retail_stores',
                    'view_bulk_order_inquiries',
                    'view_feedbacks',
                    'view_ingredients',
                ],
                'is_system_role' => true,
            ],
            [
                'name' => 'Inventory Clerk',
                'slug' => 'inventory-clerk',
                'permissions' => [
                    'view_dashboard',
                    'view_inventory_overview',
                    'view_low_stock_alerts',
                    'view_products',
                    'view_product_variants',
                    'view_categories',
                    'view_inventory',
                    'view_stock_levels',
                    'view_product_batches',
                    'create_product_batches',
                    'edit_product_batches',
                    'adjust_stock',
                    'transfer_stock',
                    'view_stock_adjustments',
                    'view_inventory_movements',
                    'view_suppliers',
                    'create_suppliers',
                    'edit_suppliers',
                    'view_purchases',
                    'create_purchases',
                    'edit_purchases',
                    'receive_purchases',
                ],
                'is_system_role' => true,
            ],
            [
                'name' => 'Accountant',
                'slug' => 'accountant',
                'permissions' => [
                    // Dashboard
                    'view_dashboard',
                    'view_pos_stats',
                    'view_inventory_overview',

                    // Sales (view only)
                    'view_sales',

                    // Products (view only)
                    'view_products',
                    'view_product_variants',
                    'view_categories',

                    // Inventory (view only)
                    'view_inventory',
                    'view_stock_levels',
                    'view_product_batches',
                    'view_inventory_movements',

                    // Purchasing (view only — to reconcile supplier invoices)
                    'view_purchases',
                    'view_suppliers',

                    // Customers & Ledgers
                    'view_customers',
                    'view_customer_purchase_history',
                    'view_customer_ledger',
                    'view_supplier_ledger',
                    'record_customer_payments',

                    // Reporting (full access)
                    'view_sales_reports',
                    'view_inventory_reports',
                    'view_profit_reports',
                    'export_reports',
                    'email_reports',

                    // Settings (view only)
                    'view_settings',
                    'view_stores',
                ],
                'is_system_role' => true,
            ],
            [
                'name' => 'Sales Agent',
                'slug' => 'sales-agent',
                'permissions' => $this->getSalesAgentPermissions(),
                'is_system_role' => true,
            ],
        ];

        // Create roles
        foreach ($roles as $roleData) {
            $role = Role::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'slug' => $roleData['slug'],
                ],
                [
                    'name' => $roleData['name'],
                    'permissions' => $roleData['permissions'],
                    'is_system_role' => $roleData['is_system_role'],
                ]
            );

            $this->command->info("✓ Created/Updated role: {$role->name} with ".count($role->permissions).' permissions');
        }

        $this->command->info('');
        $this->command->info('Role and permission seeding complete!');
        $this->command->info('Total permissions defined: '.count($this->getAllPermissions()));
    }
}

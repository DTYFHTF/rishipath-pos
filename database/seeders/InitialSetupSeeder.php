<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\Store;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialSetupSeeder extends Seeder
{
    public function run(): void
    {
        // Create Rishipath Organization
        $org = Organization::create([
            'slug' => 'rishipath',
            'name' => 'Rishipath International Foundation',
            'legal_name' => 'Rishipath International Foundation',
            'country_code' => 'IN',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
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
                    'type' => 'GST',
                    'rates' => [
                        'essential' => 5,
                        'standard' => 12,
                        'luxury' => 18,
                    ],
                ],
                'receipt' => [
                    'format' => 'RSH-{date}-{number}',
                    'footer_text' => 'Thank you for your purchase!',
                ],
            ],
            'active' => true,
        ]);

        // Create Admin Role
        $adminRole = Role::create([
            'organization_id' => $org->id,
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'permissions' => [
                'sales' => ['create', 'view', 'edit', 'delete', 'discount', 'refund'],
                'products' => ['create', 'view', 'edit', 'delete'],
                'inventory' => ['create', 'view', 'edit', 'delete', 'adjust'],
                'reports' => ['all'],
                'settings' => ['all'],
                'users' => ['create', 'view', 'edit', 'delete'],
            ],
            'is_system_role' => true,
        ]);

        // Create Cashier Role
        Role::create([
            'organization_id' => $org->id,
            'name' => 'Cashier',
            'slug' => 'cashier',
            'permissions' => [
                'sales' => ['create', 'view'],
                'products' => ['view'],
                'inventory' => ['view'],
                'reports' => ['sales_daily'],
            ],
            'is_system_role' => true,
        ]);

        // Create Main Store
        $store = Store::create([
            'organization_id' => $org->id,
            'code' => 'MAIN',
            'name' => 'Main Store',
            'address' => '123 Ayurvedic Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'country_code' => 'IN',
            'postal_code' => '400001',
            'phone' => '+91-9876543210',
            'email' => 'store@rishipath.org',
            'tax_number' => 'GSTIN123456789',
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
        ]);

        // Create Terminal
        Terminal::create([
            'store_id' => $store->id,
            'code' => 'POS-01',
            'name' => 'Main Counter',
            'device_id' => 'MAIN-POS-001',
            'printer_config' => [
                'type' => 'thermal',
                'width' => 80,
                'connection' => 'usb',
            ],
            'active' => true,
        ]);

        // Create Admin User
        User::create([
            'organization_id' => $org->id,
            'name' => 'Admin User',
            'email' => 'admin@rishipath.org',
            'phone' => '+91-9876543210',
            'password' => Hash::make('password'),
            'pin' => '1234',
            'role_id' => $adminRole->id,
            'stores' => [$store->id],
            'permissions' => null,
            'active' => true,
        ]);

        $this->command->info('✅ Initial setup complete!');
        $this->command->info('📧 Email: admin@rishipath.org');
        $this->command->info('🔑 Password: password');
        $this->command->info('📍 Organization: '.$org->name);
        $this->command->info('🏪 Store: '.$store->name);
    }
}

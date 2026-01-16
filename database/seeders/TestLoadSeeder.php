<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\LoyaltyTier;
use App\Models\Organization;
use App\Models\Reward;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TestLoadSeeder extends Seeder
{
    public function run(): void
    {
        // Use existing organization (don't create a new one)
        $org = Organization::first();

        if (! $org) {
            $this->command->error('No organization found! Please run the main database seeder first.');

            return;
        }

        // Use existing store
        $store = Store::where('organization_id', $org->id)->first();

        if (! $store) {
            $this->command->error('No store found! Please run the main database seeder first.');

            return;
        }

        // Create a terminal for sales
        $terminal = \App\Models\Terminal::firstOrCreate([
            'store_id' => $store->id,
            'code' => 'TERM-1',
        ], [
            'name' => 'Main Terminal',
            'active' => true,
        ]);

        // Ensure a cashier role exists
        $cashierRole = Role::firstOrCreate([
            'slug' => 'cashier',
        ], [
            'name' => 'Cashier',
        ]);

        // Create 2 cashier users
        for ($i = 1; $i <= 2; $i++) {
            User::firstOrCreate([
                'email' => "cashier{$i}@example.test",
            ], [
                'organization_id' => $org->id,
                'name' => "Cashier {$i}",
                'password' => bcrypt('password'),
                'role_id' => $cashierRole->id,
                'stores' => [$store->id],
                'active' => true,
            ]);
        }

        // Create loyalty tiers
        $tiers = [
            ['name' => 'Bronze', 'min_points' => 0, 'max_points' => 999, 'points_multiplier' => 1.0, 'order' => 1, 'active' => true],
            ['name' => 'Silver', 'min_points' => 1000, 'max_points' => 4999, 'points_multiplier' => 1.25, 'order' => 2, 'active' => true],
            ['name' => 'Gold', 'min_points' => 5000, 'max_points' => null, 'points_multiplier' => 1.5, 'order' => 3, 'active' => true],
        ];

        foreach ($tiers as $t) {
            $attrs = array_merge(['organization_id' => $org->id, 'name' => $t['name']], $t);
            $attrs['slug'] = Str::slug($t['name']);
            LoyaltyTier::updateOrCreate(['slug' => $attrs['slug']], $attrs);
        }

        // Create a couple of rewards
        Reward::firstOrCreate([
            'organization_id' => $org->id,
            'name' => '10% off',
        ], [
            'type' => 'discount_percentage',
            'points_required' => 500,
            'discount_value' => 10,
            'active' => true,
        ]);

        Reward::firstOrCreate([
            'organization_id' => $org->id,
            'name' => '₹100 off',
        ], [
            'type' => 'discount_fixed',
            'points_required' => 1000,
            'discount_value' => 100,
            'active' => true,
        ]);

        // Get cashier ids
        $cashierIds = User::where('role_id', $cashierRole->id)->pluck('id')->toArray();

        // Create 100 customers (manual, factories not enabled on Customer model)
        $faker = \Faker\Factory::create();
        for ($i = 0; $i < 100; $i++) {
            $dob = $faker->dateTimeBetween('-70 years', '-18 years');

            $customer = Customer::create([
                'organization_id' => $org->id,
                'name' => $faker->name(),
                'phone' => $faker->unique()->phoneNumber(),
                'email' => $faker->unique()->safeEmail(),
                'address' => $faker->address(),
                'city' => $faker->city(),
                'date_of_birth' => $dob->format('Y-m-d'),
                'birthday' => $dob->format('Y-m-d'),
                'total_purchases' => 0,
                'total_spent' => 0,
                'loyalty_points' => 0,
                'loyalty_tier_id' => null,
                'last_birthday_bonus_at' => null,
                'loyalty_enrolled_at' => (rand(0, 100) < 50) ? now() : null,
                'notes' => null,
                'active' => true,
            ]);

            // Create 1-3 sample sales for each customer
            $salesCount = rand(1, 3);
            for ($s = 0; $s < $salesCount; $s++) {
                $receipt = 'RCPT-'.Str::upper(Str::random(8));
                $total = rand(100, 5000) / 1; // integer amount

                $sale = Sale::create([
                    'organization_id' => $org->id,
                    'store_id' => $store->id,
                    'terminal_id' => $terminal->id,
                    'receipt_number' => $receipt,
                    'date' => now()->toDateString(),
                    'time' => now()->format('H:i:s'),
                    'cashier_id' => count($cashierIds) ? $cashierIds[array_rand($cashierIds)] : null,
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'subtotal' => $total,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_amount' => $total,
                    'payment_method' => 'cash',
                    'payment_status' => 'paid',
                    'amount_paid' => $total,
                ]);

                // Award loyalty points for the sale (if customer exists)
                try {
                    app(LoyaltyService::class)->awardPointsForSale($sale);
                } catch (\Throwable $e) {
                    // ignore errors when seeding loyalty
                }
            }
        }

        $this->command->info('Test load: Created organization, store, 2 cashiers, tiers, rewards and 100 customers.');
    }
}

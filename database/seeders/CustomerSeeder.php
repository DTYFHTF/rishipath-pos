<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'rishipath')->first();
        if (! $org) {
            $this->command->error('Please run InitialSetupSeeder first.');
            return;
        }

        $customers = [
            [
                'organization_id' => $org->id,
                'name' => 'Abin Maharjan',
                'country_code' => '+977',
                'phone' => '9841000001',
                'email' => 'abin@example.com',
                'address' => 'Kathmandu, Nepal',
                'active' => true,
            ],
            [
                'organization_id' => $org->id,
                'name' => 'Sita Sharma',
                'country_code' => '+91',
                'phone' => '9876500002',
                'email' => 'sita@example.com',
                'address' => 'Mumbai, India',
                'active' => true,
            ],
            [
                'organization_id' => $org->id,
                'name' => 'Rakesh Gupta',
                'country_code' => '+91',
                'phone' => '9876500003',
                'email' => 'rakesh@example.com',
                'address' => 'Delhi, India',
                'active' => true,
            ],
            [
                'organization_id' => $org->id,
                'name' => 'Maya Koirala',
                'country_code' => '+977',
                'phone' => '9841000004',
                'email' => 'maya@example.com',
                'address' => 'Pokhara, Nepal',
                'active' => true,
            ],
            [
                'organization_id' => $org->id,
                'name' => 'Asha Patel',
                'country_code' => '+91',
                'phone' => '9876500005',
                'email' => 'asha@example.com',
                'address' => 'Ahmedabad, India',
                'active' => true,
            ],
        ];

        foreach ($customers as $data) {
            Customer::create($data);
        }

        $this->command->info('✅ 5 customers seeded successfully!');
    }
}

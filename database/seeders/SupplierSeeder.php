<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'rishipath')->first();

        if (! $org) {
            $this->command->error('Please run InitialSetupSeeder first!');

            return;
        }

        $suppliers = [
            [
                'organization_id' => $org->id,
                'supplier_code' => 'SUP-001',
                'name' => 'Laxmi Khadyana Stores',
                'contact_person' => 'Rajkumar Jaiswal',
                'phone' => '+977 9808450422',
                'email' => null,
                'address' => 'Kalimati, Kathmandu',
                'city' => 'Kathmandu',
                'state' => 'Bagmati',
                'country_code' => 'NP',
                'tax_number' => null,
                'notes' => 'Primary supplier. Purchase bills are VAT bills.',
                'active' => true,
            ],
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::updateOrCreate(
                [
                    'organization_id' => $supplierData['organization_id'],
                    'supplier_code' => $supplierData['supplier_code'],
                ],
                $supplierData,
            );
        }

        $this->command->info('✅ '.count($suppliers).' suppliers seeded successfully!');
    }
}

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
                'name' => 'Himalayan Herbs Pvt Ltd',
                'contact_person' => 'Rajesh Kumar',
                'phone' => '+91-9876543201',
                'email' => 'contact@himalayanherbs.com',
                'address' => '45 Herbal Lane, Haridwar',
                'city' => 'Haridwar',
                'state' => 'Uttarakhand',
                'country_code' => 'IN',
                'tax_number' => 'GSTIN-HH-001',
                'notes' => 'Premium quality herbs supplier, 30-day payment terms',
                'active' => true,
            ],
            [
                'organization_id' => $org->id,
                'supplier_code' => 'SUP-002',
                'name' => 'Kerala Ayurveda Suppliers',
                'contact_person' => 'Suresh Nair',
                'phone' => '+91-9876543202',
                'email' => 'orders@keralaayurveda.com',
                'address' => '12 Spice Road, Kochi',
                'city' => 'Kochi',
                'state' => 'Kerala',
                'country_code' => 'IN',
                'tax_number' => 'GSTIN-KA-002',
                'notes' => 'Traditional oils and powders, weekly deliveries',
                'active' => true,
            ],
            [
                'organization_id' => $org->id,
                'supplier_code' => 'SUP-003',
                'name' => 'North India Medicinals',
                'contact_person' => 'Amit Singh',
                'phone' => '+91-9876543203',
                'email' => 'sales@nimedicinals.in',
                'address' => '89 Medical Plaza, New Delhi',
                'city' => 'New Delhi',
                'state' => 'Delhi',
                'country_code' => 'IN',
                'tax_number' => 'GSTIN-NI-003',
                'notes' => 'Bulk supplier, competitive pricing',
                'active' => true,
            ],
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::create($supplierData);
        }

        $this->command->info('✅ '.count($suppliers).' suppliers seeded successfully!');
    }
}

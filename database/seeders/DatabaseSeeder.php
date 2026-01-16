<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call the main seeders
        $this->call([
            InitialSetupSeeder::class,
            RolePermissionSeeder::class,
            SupplierSeeder::class,
            ProductCatalogSeeder::class,
        ]);
    }
}

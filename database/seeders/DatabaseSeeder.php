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
        // ProductCatalogSeeder is the single source of truth for the catalog:
        // it deactivates any product absent from its own rate list. The older
        // SpiceProductSeeder / DryFruitsSeeder / MandatoryPackVariantSeeder /
        // SyncWeightVariantPricingSeeder were superseded by it and have been
        // removed.
        $this->call([
            InitialSetupSeeder::class,
            RolePermissionSeeder::class,
            StaffUserSeeder::class,
            SupplierSeeder::class,
            ProductCatalogSeeder::class,
            ProductImageSeeder::class,
            JeeraPowderSeeder::class,
            // Must run after ProductCatalogSeeder: re-activates the blend
            // products its deactivation sweep would otherwise hide.
            IngredientKnowledgeBaseSeeder::class,
            BlendProductsSeeder::class,
            MultaniMittiSeeder::class,
            // Runs last: sets up the live Shuddhidham accounts (Super Admin +
            // sales agents) and deactivates any other users so the panel ships
            // with exactly the intended logins.
            ShuddhidhamUsersSeeder::class,
        ]);
    }
}

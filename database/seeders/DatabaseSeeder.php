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
        // NOTE: SpiceProductSeeder and DryFruitsSeeder are intentionally NOT
        // included here - they add products outside the verified "May 2026
        // Rate List" that ProductCatalogSeeder treats as the single source of
        // truth (it deactivates anything not in its own list). Run them
        // manually with --class if that older catalog is ever needed again.
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

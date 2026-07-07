<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\SalesAgent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Shuddhidham Production Users Seeder
 *
 * Sets up exactly 3 users:
 *  1. admin@shuddhidham.com  — Super Admin
 *  2. bina@shuddhidham.com   — Sales Agent (+ SalesAgent profile)
 *  3. bishal@shuddhidham.com — Sales Agent (+ SalesAgent profile)
 *
 * Deletes all other user accounts for this organisation.
 * Safe to re-run (idempotent for the 3 kept accounts).
 */
class ShuddhidhamUsersSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'rishipath')->firstOrFail();
        $store = Store::where('organization_id', $org->id)->where('code', 'MAIN')->firstOrFail();

        // ── Roles ─────────────────────────────────────────────────────────────
        $superAdminRole = Role::where('organization_id', $org->id)
            ->where('slug', 'super-admin')
            ->firstOrFail();

        // updateOrCreate so re-running this seeder re-applies the reduced
        // allow-list even if the role already exists with broader permissions.
        $agentRole = Role::updateOrCreate(
            ['organization_id' => $org->id, 'slug' => 'sales-agent'],
            [
                'name' => 'Sales Agent',
                'permissions' => RolePermissionSeeder::getSalesAgentPermissions(),
                'is_system_role' => true,
            ]
        );

        // ── 1. Super Admin ────────────────────────────────────────────────────
        User::updateOrCreate(
            ['organization_id' => $org->id, 'email' => 'admin@shuddhidham.com'],
            [
                'name'        => 'Super Admin',
                'phone'       => '+977-9808450422',
                'password'    => Hash::make('shuddhidham'),
                'pin'         => '1234',
                'role_id'     => $superAdminRole->id,
                'stores'      => [$store->id],
                'permissions' => null,
                'active'      => true,
            ]
        );

        // ── 2. Bina ───────────────────────────────────────────────────────────
        $bina = User::updateOrCreate(
            ['organization_id' => $org->id, 'email' => 'bina@shuddhidham.com'],
            [
                'name'        => 'Bina Shrestha',
                'phone'       => '+977-9800000001',
                'password'    => Hash::make('shuddhidham'),
                'pin'         => '1111',
                'role_id'     => $agentRole->id,
                'stores'      => [$store->id],
                'permissions' => null,
                'active'      => true,
            ]
        );

        SalesAgent::updateOrCreate(
            ['organization_id' => $org->id, 'email' => 'bina@shuddhidham.com'],
            [
                'agent_code'                    => 'AGT-BINA',
                'name'                          => 'Bina Shrestha',
                'phone'                         => '+977-9800000001',
                'address'                       => 'Kathmandu',
                'territory'                     => 'Thamel / Asan',
                'commission_retail_pct'         => 5.00,
                'commission_wholesale_profit_pct' => 30.00,
                'min_wholesale_amount'          => 5000.00,
                'active'                        => true,
                'notes'                         => 'Route: Thamel restaurant belt, Asan mithai lane',
            ]
        );

        // ── 3. Bishal ─────────────────────────────────────────────────────────
        $bishal = User::updateOrCreate(
            ['organization_id' => $org->id, 'email' => 'bishal@shuddhidham.com'],
            [
                'name'        => 'Bishal Karki',
                'phone'       => '+977-9800000002',
                'password'    => Hash::make('shuddhidham'),
                'pin'         => '2222',
                'role_id'     => $agentRole->id,
                'stores'      => [$store->id],
                'permissions' => null,
                'active'      => true,
            ]
        );

        SalesAgent::updateOrCreate(
            ['organization_id' => $org->id, 'email' => 'bishal@shuddhidham.com'],
            [
                'agent_code'                    => 'AGT-BISHAL',
                'name'                          => 'Bishal Karki',
                'phone'                         => '+977-9800000002',
                'address'                       => 'Lalitpur',
                'territory'                     => 'Patan / Lalitpur',
                'commission_retail_pct'         => 5.00,
                'commission_wholesale_profit_pct' => 30.00,
                'min_wholesale_amount'          => 5000.00,
                'active'                        => true,
                'notes'                         => 'Route: Patan restaurant corridor, Lalitpur market',
            ]
        );

        // ── Remove all other users for this organisation ───────────────────
        // Deactivate rather than hard-delete to avoid FK constraint violations
        // from existing sales/movements that reference cashier_id / user_id.
        $keepEmails = [
            'admin@shuddhidham.com',
            'bina@shuddhidham.com',
            'bishal@shuddhidham.com',
        ];

        $deactivated = User::where('organization_id', $org->id)
            ->whereNotIn('email', $keepEmails)
            ->update(['active' => false]);

        $this->command->info("✅ 3 users created/updated (admin, bina, bishal).");
        $this->command->info("🚫 {$deactivated} other user(s) deactivated (not deleted, to preserve historical data).");
        $this->command->info("👤 SalesAgent profiles created for Bina (AGT-BINA) and Bishal (AGT-BISHAL).");
    }
}

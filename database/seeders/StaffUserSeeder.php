<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffUserSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->where('slug', 'rishipath')->first();
        $store = Store::query()->where('organization_id', $organization?->id)->where('code', 'MAIN')->first();

        if (! $organization || ! $store) {
            $this->command?->error('Organization or main store missing. Run InitialSetupSeeder first.');
            return;
        }

        $roles = [
            'cashier' => Role::query()->where('organization_id', $organization->id)->where('slug', 'cashier')->first(),
            'marketing' => Role::query()->where('organization_id', $organization->id)->where('slug', 'marketing')->first(),
            'inventory-clerk' => Role::query()->where('organization_id', $organization->id)->where('slug', 'inventory-clerk')->first(),
        ];

        $users = [
            [
                'email' => 'cashier@rishipath.org',
                'name' => 'Cashier User',
                'phone' => '+91-9000000001',
                'role' => 'cashier',
            ],
            [
                'email' => 'marketing@rishipath.org',
                'name' => 'Marketing User',
                'phone' => '+91-9000000002',
                'role' => 'marketing',
            ],
            [
                'email' => 'inventory@rishipath.org',
                'name' => 'Inventory User',
                'phone' => '+91-9000000003',
                'role' => 'inventory-clerk',
            ],
        ];

        foreach ($users as $userData) {
            $role = $roles[$userData['role']] ?? null;
            if (! $role) {
                $this->command?->warn("Role {$userData['role']} not found, skipping {$userData['email']}");
                continue;
            }

            $user = User::firstOrNew(['email' => $userData['email']]);
            $isNew = ! $user->exists;

            $user->organization_id = $organization->id;
            $user->name = $userData['name'];
            $user->phone = $userData['phone'];
            $user->role_id = $role->id;
            $user->stores = [$store->id];
            $user->permissions = [];
            $user->active = true;

            if ($isNew) {
                $user->password = Hash::make('password');
                $user->pin = Hash::make('1234');
            }

            $user->save();
        }

        $this->command?->info('StaffUserSeeder complete. Cashier, marketing, and inventory users are ready.');
    }
}

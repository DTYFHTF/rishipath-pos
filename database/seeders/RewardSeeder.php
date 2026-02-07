<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Reward;
use Illuminate\Database\Seeder;

class RewardSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::first();
        if (! $org) {
            return;
        }

        // Create a standard high-value reward: ₹1,000 off for 100,000 points
        $reward = Reward::updateOrCreate(
            [
                'organization_id' => $org->id,
                'name' => '₹1,000 Off (100k points)'
            ],
            [
                'description' => 'Redeem 100,000 loyalty points for ₹1,000 off your purchase.',
                'type' => 'discount_fixed',
                'points_required' => 100000,
                'discount_value' => 1000.00,
                'quantity' => 1,
                'valid_from' => now(),
                'valid_until' => now()->addYear(),
                'max_redemptions_per_customer' => 5,
                'total_redemptions' => 0,
                'tier_restrictions' => [],
                'image_url' => null,
                'active' => true,
            ]
        );

        $this->command->info('✅ Reward seeded: ' . $reward->name . ' (ID: ' . $reward->id . ')');
    }
}

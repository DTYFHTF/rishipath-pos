<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Reward;
use Illuminate\Database\Seeder;

/**
 * The one loyalty reward the business actually runs.
 *
 * Points are awarded at Rs1 spent = 1 point (LoyaltyService::awardPointsForSale),
 * so 100,000 points is 100,000 rupees of accumulated purchases and the Rs1,000
 * discount works out to 1% back. Points accumulate across purchases rather than
 * needing a single Rs100,000 sale.
 */
class LoyaltyRewardSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Organization::all() as $organization) {
            Reward::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => 'Rs1,000 off',
                ],
                [
                    'description' => 'Earned after Rs100,000 of purchases. Redeem against any bill.',
                    'type' => 'discount_fixed',
                    'points_required' => 100000,
                    'discount_value' => 1000,
                    'valid_from' => now()->startOfDay(),
                    'valid_until' => null,
                    // Deliberately repeatable: spend another Rs100,000, earn it again.
                    'max_redemptions_per_customer' => null,
                    'active' => true,
                ]
            );
        }
    }
}

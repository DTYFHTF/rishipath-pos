<?php

namespace Database\Seeders;

use App\Models\LoyaltyTier;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class LoyaltyTierSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::first();

        if (!$organization) {
            return;
        }

        $tiers = [
            [
                'name' => 'Bronze',
                'slug' => 'bronze',
                'min_points' => 0,
                'max_points' => 999,
                'points_multiplier' => 1.00,
                'discount_percentage' => 0,
                'benefits' => [
                    'Earn 1 point per ₹1 spent',
                    'Birthday bonus points',
                    'Access to seasonal offers',
                ],
                'badge_color' => 'orange',
                'badge_icon' => 'heroicon-o-star',
                'order' => 1,
            ],
            [
                'name' => 'Silver',
                'slug' => 'silver',
                'min_points' => 1000,
                'max_points' => 4999,
                'points_multiplier' => 1.25,
                'discount_percentage' => 2.5,
                'benefits' => [
                    'Earn 1.25x points on purchases',
                    '2.5% discount on all purchases',
                    'Priority customer support',
                    'Double birthday bonus',
                    'Early access to new products',
                ],
                'badge_color' => 'gray',
                'badge_icon' => 'heroicon-o-sparkles',
                'order' => 2,
            ],
            [
                'name' => 'Gold',
                'slug' => 'gold',
                'min_points' => 5000,
                'max_points' => 14999,
                'points_multiplier' => 1.50,
                'discount_percentage' => 5.0,
                'benefits' => [
                    'Earn 1.5x points on purchases',
                    '5% discount on all purchases',
                    'Free home delivery',
                    'Triple birthday bonus',
                    'Exclusive gold member events',
                    'Extended return policy (30 days)',
                ],
                'badge_color' => 'yellow',
                'badge_icon' => 'heroicon-o-fire',
                'order' => 3,
            ],
            [
                'name' => 'Platinum',
                'slug' => 'platinum',
                'min_points' => 15000,
                'max_points' => null,
                'points_multiplier' => 2.00,
                'discount_percentage' => 10.0,
                'benefits' => [
                    'Earn 2x points on purchases',
                    '10% discount on all purchases',
                    'Free express delivery',
                    '5x birthday bonus',
                    'VIP customer support 24/7',
                    'Exclusive platinum member gifts',
                    'Personalized product recommendations',
                    'Extended return policy (60 days)',
                ],
                'badge_color' => 'purple',
                'badge_icon' => 'heroicon-o-trophy',
                'order' => 4,
            ],
        ];

        foreach ($tiers as $tier) {
            LoyaltyTier::create([
                'organization_id' => $organization->id,
                ...$tier,
            ]);
        }

        $this->command->info('✅ Loyalty tiers seeded successfully');
    }
}

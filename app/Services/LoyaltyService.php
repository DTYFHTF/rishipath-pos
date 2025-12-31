<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTier;
use App\Models\Reward;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    /**
     * Calculate and award points for a sale
     */
    public function awardPointsForSale(Sale $sale): ?LoyaltyPoint
    {
        if (!$sale->customer_id) {
            return null;
        }

        $customer = $sale->customer;

        // Enroll customer if not already enrolled
        if (!$customer->isLoyaltyMember()) {
            $this->enrollCustomer($customer);
        }

        // Calculate base points (₹1 = 1 point)
        $basePoints = (int) floor($sale->total_amount);

        // Apply tier multiplier
        $multiplier = $customer->loyaltyTier?->points_multiplier ?? 1.0;
        $points = (int) floor($basePoints * $multiplier);

        if ($points <= 0) {
            return null;
        }

        // Award points
        return $this->addPoints(
            customer: $customer,
            points: $points,
            type: 'earned',
            description: "Purchase #{$sale->receipt_number}",
            saleId: $sale->id,
            expiresAt: now()->addYear()
        );
    }

    /**
     * Redeem a reward for a customer
     */
    public function redeemReward(Customer $customer, Reward $reward, ?int $processedBy = null): array
    {
        if (!$reward->canBeRedeemedBy($customer)) {
            return [
                'success' => false,
                'message' => 'Cannot redeem this reward',
            ];
        }

        DB::beginTransaction();

        try {
            // Deduct points
            $loyaltyPoint = $this->addPoints(
                customer: $customer,
                points: -$reward->points_required,
                type: 'redeemed',
                description: "Redeemed: {$reward->name}",
                rewardId: $reward->id,
                processedBy: $processedBy
            );

            // Increment total redemptions
            $reward->increment('total_redemptions');

            // Update tier if needed
            $this->updateCustomerTier($customer);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Reward redeemed successfully',
                'loyalty_point' => $loyaltyPoint,
                'discount_value' => $reward->discount_value,
                'discount_type' => $reward->type,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add points to customer account
     */
    public function addPoints(
        Customer $customer,
        int $points,
        string $type,
        string $description,
        ?int $saleId = null,
        ?int $rewardId = null,
        ?\DateTime $expiresAt = null,
        ?int $processedBy = null
    ): LoyaltyPoint {
        DB::beginTransaction();

        try {
            // Update customer's total points
            $customer->increment('loyalty_points', $points);
            $customer->refresh();

            // Create loyalty point record
            $loyaltyPoint = LoyaltyPoint::create([
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'sale_id' => $saleId,
                'reward_id' => $rewardId,
                'type' => $type,
                'points' => $points,
                'balance_after' => $customer->loyalty_points,
                'description' => $description,
                'expires_at' => $expiresAt,
                'processed_by' => $processedBy,
            ]);

            // Update customer tier based on new points balance
            $this->updateCustomerTier($customer);

            DB::commit();

            return $loyaltyPoint;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Award birthday bonus points
     */
    public function awardBirthdayBonus(Customer $customer, int $bonusPoints = 100): ?LoyaltyPoint
    {
        if (!$customer->isBirthdayBonusDue()) {
            return null;
        }

        $loyaltyPoint = $this->addPoints(
            customer: $customer,
            points: $bonusPoints,
            type: 'bonus',
            description: 'Happy Birthday! 🎂',
            expiresAt: now()->addYear()
        );

        // Update last birthday bonus date
        $customer->update(['last_birthday_bonus_at' => now()]);

        return $loyaltyPoint;
    }

    /**
     * Enroll customer in loyalty program
     */
    public function enrollCustomer(Customer $customer, int $welcomeBonus = 50): void
    {
        if ($customer->isLoyaltyMember()) {
            return;
        }

        DB::beginTransaction();

        try {
            // Get starter tier
            $starterTier = LoyaltyTier::where('organization_id', $customer->organization_id)
                ->where('active', true)
                ->orderBy('min_points')
                ->first();

            // Enroll customer
            $customer->update([
                'loyalty_enrolled_at' => now(),
                'loyalty_tier_id' => $starterTier?->id,
            ]);

            // Award welcome bonus
            if ($welcomeBonus > 0) {
                $this->addPoints(
                    customer: $customer,
                    points: $welcomeBonus,
                    type: 'bonus',
                    description: 'Welcome bonus! 🎉',
                    expiresAt: now()->addYear()
                );
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update customer's tier based on points
     */
    public function updateCustomerTier(Customer $customer): void
    {
        $appropriateTier = LoyaltyTier::where('organization_id', $customer->organization_id)
            ->where('active', true)
            ->where('min_points', '<=', $customer->loyalty_points)
            ->where(function ($q) use ($customer) {
                $q->whereNull('max_points')
                    ->orWhere('max_points', '>=', $customer->loyalty_points);
            })
            ->orderBy('min_points', 'desc')
            ->first();

        if ($appropriateTier && $customer->loyalty_tier_id !== $appropriateTier->id) {
            $customer->update(['loyalty_tier_id' => $appropriateTier->id]);
        }
    }

    /**
     * Expire old points
     */
    public function expireOldPoints(): int
    {
        $expiredPoints = LoyaltyPoint::where('type', 'earned')
            ->where('expires_at', '<', now())
            ->whereNotNull('expires_at')
            ->get();

        $totalExpired = 0;

        foreach ($expiredPoints as $point) {
            // Only expire if not already processed
            if ($point->points > 0) {
                $customer = $point->customer;
                
                $this->addPoints(
                    customer: $customer,
                    points: -$point->points,
                    type: 'expired',
                    description: "Expired points from {$point->created_at->format('M Y')}"
                );

                $totalExpired += $point->points;
            }
        }

        return $totalExpired;
    }

    /**
     * Get customer's loyalty summary
     */
    public function getCustomerSummary(Customer $customer): array
    {
        $tier = $customer->loyaltyTier;
        $nextTier = $tier?->getNextTier();

        return [
            'points_balance' => $customer->loyalty_points,
            'tier' => $tier?->name ?? 'No Tier',
            'tier_color' => $tier?->badge_color ?? 'gray',
            'tier_benefits' => $tier?->benefits ?? [],
            'points_multiplier' => $tier?->points_multiplier ?? 1.0,
            'discount_percentage' => $tier?->discount_percentage ?? 0,
            'next_tier' => $nextTier?->name,
            'points_to_next_tier' => $tier?->pointsToNextTier($customer->loyalty_points),
            'lifetime_earned' => $customer->loyaltyPoints()->where('type', 'earned')->sum('points'),
            'lifetime_redeemed' => abs($customer->loyaltyPoints()->where('type', 'redeemed')->sum('points')),
            'enrolled_at' => $customer->loyalty_enrolled_at,
            'is_birthday_soon' => $customer->birthday && $customer->birthday->isSameMonth(now()),
        ];
    }

    /**
     * Get available rewards for customer
     */
    public function getAvailableRewards(Customer $customer): array
    {
        return Reward::where('organization_id', $customer->organization_id)
            ->where('active', true)
            ->where('points_required', '<=', $customer->loyalty_points)
            ->where(function ($q) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->orderBy('points_required')
            ->get()
            ->filter(fn ($reward) => $reward->canBeRedeemedBy($customer))
            ->values()
            ->toArray();
    }
}

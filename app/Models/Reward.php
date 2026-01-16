<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reward extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'type',
        'points_required',
        'discount_value',
        'product_variant_id',
        'quantity',
        'valid_from',
        'valid_until',
        'max_redemptions_per_customer',
        'total_redemptions',
        'tier_restrictions',
        'image_url',
        'active',
    ];

    protected $casts = [
        'points_required' => 'integer',
        'discount_value' => 'decimal:2',
        'quantity' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'max_redemptions_per_customer' => 'integer',
        'total_redemptions' => 'integer',
        'tier_restrictions' => 'array',
        'active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function loyaltyPoints(): HasMany
    {
        return $this->hasMany(LoyaltyPoint::class);
    }

    /**
     * Check if reward is currently valid
     */
    public function isValid(): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->valid_from && now()->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && now()->gt($this->valid_until)) {
            return false;
        }

        return true;
    }

    /**
     * Check if customer can redeem this reward
     */
    public function canBeRedeemedBy(Customer $customer): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        // Check if customer has enough points
        if ($customer->loyalty_points < $this->points_required) {
            return false;
        }

        // Check tier restrictions
        if (! empty($this->tier_restrictions) && $customer->loyaltyTier) {
            if (! in_array($customer->loyaltyTier->id, $this->tier_restrictions)) {
                return false;
            }
        }

        // Check redemption limit
        if ($this->max_redemptions_per_customer) {
            $customerRedemptions = $this->loyaltyPoints()
                ->where('customer_id', $customer->id)
                ->where('type', 'redeemed')
                ->count();

            if ($customerRedemptions >= $this->max_redemptions_per_customer) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get human-readable reward description
     */
    public function getDisplayValue(): string
    {
        return match ($this->type) {
            'discount_percentage' => "{$this->discount_value}% off",
            'discount_fixed' => "₹{$this->discount_value} off",
            'free_product' => "Free {$this->productVariant?->product->name}",
            'cashback' => "₹{$this->discount_value} cashback",
            default => 'Special reward',
        };
    }
}

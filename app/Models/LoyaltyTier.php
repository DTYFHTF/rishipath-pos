<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyTier extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'min_points',
        'max_points',
        'points_multiplier',
        'discount_percentage',
        'benefits',
        'badge_color',
        'badge_icon',
        'order',
        'active',
    ];

    protected $casts = [
        'min_points' => 'integer',
        'max_points' => 'integer',
        'points_multiplier' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'benefits' => 'array',
        'order' => 'integer',
        'active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Check if a customer qualifies for this tier
     */
    public function qualifiesForTier(int $points): bool
    {
        if ($points < $this->min_points) {
            return false;
        }

        if ($this->max_points === null) {
            return true;
        }

        return $points <= $this->max_points;
    }

    /**
     * Get the next tier for progression
     */
    public function getNextTier(): ?LoyaltyTier
    {
        return static::where('organization_id', $this->organization_id)
            ->where('min_points', '>', $this->min_points)
            ->where('active', true)
            ->orderBy('min_points')
            ->first();
    }

    /**
     * Get points needed for next tier
     */
    public function pointsToNextTier(int $currentPoints): ?int
    {
        $nextTier = $this->getNextTier();

        if (! $nextTier) {
            return null;
        }

        return max(0, $nextTier->min_points - $currentPoints);
    }
}

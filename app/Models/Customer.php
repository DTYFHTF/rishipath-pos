<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'organization_id',
        'customer_code',
        'name',
        'phone',
        'email',
        'address',
        'city',
        'date_of_birth',
        'total_purchases',
        'total_spent',
        'loyalty_points',
        'loyalty_tier_id',
        'birthday',
        'last_birthday_bonus_at',
        'loyalty_enrolled_at',
        'notes',
        'active',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'birthday' => 'date',
        'last_birthday_bonus_at' => 'date',
        'loyalty_enrolled_at' => 'datetime',
        'total_spent' => 'decimal:2',
        'loyalty_points' => 'integer',
        'active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function loyaltyTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class);
    }

    public function loyaltyPoints(): HasMany
    {
        return $this->hasMany(LoyaltyPoint::class);
    }

    /**
     * Check if customer is enrolled in loyalty program
     */
    public function isLoyaltyMember(): bool
    {
        return $this->loyalty_enrolled_at !== null;
    }

    /**
     * Check if birthday bonus is due
     */
    public function isBirthdayBonusDue(): bool
    {
        if (!$this->birthday) {
            return false;
        }

        $today = now();
        $birthday = $this->birthday->setYear($today->year);

        // Check if today is birthday
        if (!$today->isSameDay($birthday)) {
            return false;
        }

        // Check if bonus already given this year
        if ($this->last_birthday_bonus_at && 
            $this->last_birthday_bonus_at->year === $today->year) {
            return false;
        }

        return true;
    }
}

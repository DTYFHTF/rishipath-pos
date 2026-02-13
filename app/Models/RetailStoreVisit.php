<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailStoreVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'retail_store_id',
        'visited_by',
        'visit_date',
        'visit_time',
        'visit_purpose',
        'visit_outcome',
        'stock_available',
        'good_display',
        'clean_store',
        'staff_trained',
        'has_competition',
        'order_placed',
        'payment_collected',
        'has_refrigeration',
        'store_condition_rating',
        'customer_footfall_rating',
        'cooperation_rating',
        'issues_found',
        'action_items',
        'notes',
        'competitor_notes',
        'next_visit_date',
        'order_value',
        'photos',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'next_visit_date' => 'date',
        'stock_available' => 'boolean',
        'good_display' => 'boolean',
        'clean_store' => 'boolean',
        'staff_trained' => 'boolean',
        'has_competition' => 'boolean',
        'order_placed' => 'boolean',
        'payment_collected' => 'boolean',
        'has_refrigeration' => 'boolean',
        'photos' => 'array',
        'order_value' => 'decimal:2',
    ];

    // ─── Relationships ───────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function retailStore(): BelongsTo
    {
        return $this->belongsTo(RetailStore::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visited_by');
    }

    // ─── Accessors ───────────────────────────────

    public function getPositiveFeedbackCountAttribute(): int
    {
        return collect([
            $this->stock_available,
            $this->good_display,
            $this->clean_store,
            $this->staff_trained,
            ! $this->has_competition,
        ])->filter()->count();
    }

    public function getAverageRatingAttribute(): ?float
    {
        $ratings = array_filter([
            $this->store_condition_rating,
            $this->customer_footfall_rating,
            $this->cooperation_rating,
        ]);

        return empty($ratings) ? null : round(array_sum($ratings) / count($ratings), 1);
    }

    // ─── Methods ─────────────────────────────────

    public function isSuccessful(): bool
    {
        return $this->visit_outcome === 'successful'
            && $this->getPositiveFeedbackCountAttribute() >= 3;
    }
}

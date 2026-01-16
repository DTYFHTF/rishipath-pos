<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertRule extends Model
{
    protected $fillable = [
        'name',
        'type',
        'conditions',
        'recipients',
        'active',
        'frequency',
        'last_triggered_at',
        'trigger_count',
        'store_id',
        'created_by',
    ];

    protected $casts = [
        'conditions' => 'array',
        'recipients' => 'array',
        'active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if alert should run based on frequency
     */
    public function shouldCheck(): bool
    {
        if (! $this->active) {
            return false;
        }

        if (! $this->last_triggered_at) {
            return true;
        }

        return match ($this->frequency) {
            'immediate' => true,
            'hourly' => $this->last_triggered_at->addHour()->isPast(),
            'daily' => $this->last_triggered_at->addDay()->isPast(),
            default => false,
        };
    }

    /**
     * Mark alert as triggered
     */
    public function markAsTriggered(): void
    {
        $this->increment('trigger_count');
        $this->update(['last_triggered_at' => now()]);
    }

    /**
     * Get rule type display name
     */
    public function getTypeNameAttribute(): string
    {
        return match ($this->type) {
            'low_stock' => 'Low Stock Alert',
            'high_value_sale' => 'High Value Sale',
            'cashier_variance' => 'Cashier Variance',
            'inventory_discrepancy' => 'Inventory Discrepancy',
            'sales_target' => 'Sales Target',
            default => ucwords(str_replace('_', ' ', $this->type)),
        };
    }

    /**
     * Get condition summary for display
     */
    public function getConditionSummaryAttribute(): string
    {
        if (empty($this->conditions)) {
            return 'No conditions set';
        }

        $parts = [];

        if (isset($this->conditions['threshold'])) {
            $parts[] = "Threshold: {$this->conditions['threshold']}";
        }

        if (isset($this->conditions['comparison'])) {
            $parts[] = "When {$this->conditions['comparison']}";
        }

        return implode(' | ', $parts) ?: 'Custom conditions';
    }

    /**
     * Scope for active rules
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by frequency
     */
    public function scopeByFrequency($query, string $frequency)
    {
        return $query->where('frequency', $frequency);
    }
}

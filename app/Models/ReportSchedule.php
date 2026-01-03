<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class ReportSchedule extends Model
{
    protected $fillable = [
        'name',
        'report_type',
        'frequency',
        'cron_expression',
        'parameters',
        'recipients',
        'format',
        'active',
        'last_run_at',
        'next_run_at',
        'created_by',
    ];

    protected $casts = [
        'parameters' => 'array',
        'recipients' => 'array',
        'active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ScheduledReportRun::class);
    }

    public function latestRun()
    {
        return $this->hasOne(ScheduledReportRun::class)->latestOfMany();
    }

    /**
     * Calculate next run time based on frequency
     */
    public function calculateNextRun(): Carbon
    {
        $now = now();
        
        return match($this->frequency) {
            'daily' => $now->addDay()->startOfDay()->addHours(8), // 8 AM next day
            'weekly' => $now->addWeek()->startOfWeek()->addHours(8), // Monday 8 AM
            'monthly' => $now->addMonth()->startOfMonth()->addHours(8), // 1st of month 8 AM
            'custom' => $this->calculateFromCron($now),
            default => $now->addDay(),
        };
    }

    protected function calculateFromCron(Carbon $from): Carbon
    {
        // Simplified cron parsing - in production use a library like dragonmantank/cron-expression
        return $from->addDay();
    }

    /**
     * Check if schedule is due to run
     */
    public function isDue(): bool
    {
        if (!$this->active) {
            return false;
        }

        if (!$this->next_run_at) {
            return true; // Never run before
        }

        return $this->next_run_at->isPast();
    }

    /**
     * Get report type display name
     */
    public function getReportTypeNameAttribute(): string
    {
        return match($this->report_type) {
            'sales' => 'Sales Report',
            'inventory' => 'Inventory Report',
            'customer_analytics' => 'Customer Analytics',
            'cashier_performance' => 'Cashier Performance',
            default => ucwords(str_replace('_', ' ', $this->report_type)),
        };
    }

    /**
     * Scope for active schedules
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for due schedules
     */
    public function scopeDue($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                  ->orWhere('next_run_at', '<=', now());
            });
    }
}

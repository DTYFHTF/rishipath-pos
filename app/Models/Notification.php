<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'type',
        'title',
        'message',
        'severity',
        'data',
        'recipients',
        'sent',
        'sent_at',
        'send_error',
        'related_id',
        'related_type',
        'triggered_by',
    ];

    protected $casts = [
        'data' => 'array',
        'recipients' => 'array',
        'sent' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /**
     * Get the related model (polymorphic)
     */
    public function related()
    {
        return $this->morphTo('related');
    }

    /**
     * Mark notification as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'sent' => true,
            'sent_at' => now(),
            'send_error' => null,
        ]);
    }

    /**
     * Mark notification as failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'sent' => false,
            'send_error' => $error,
        ]);
    }

    /**
     * Get severity color for UI
     */
    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            'info' => 'blue',
            'warning' => 'yellow',
            'error' => 'orange',
            'critical' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get severity icon
     */
    public function getSeverityIconAttribute(): string
    {
        return match($this->severity) {
            'info' => 'heroicon-o-information-circle',
            'warning' => 'heroicon-o-exclamation-triangle',
            'error' => 'heroicon-o-x-circle',
            'critical' => 'heroicon-o-fire',
            default => 'heroicon-o-bell',
        };
    }

    /**
     * Scope for unsent notifications
     */
    public function scopeUnsent($query)
    {
        return $query->where('sent', false);
    }

    /**
     * Scope for sent notifications
     */
    public function scopeSent($query)
    {
        return $query->where('sent', true);
    }

    /**
     * Scope by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by severity
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }
}

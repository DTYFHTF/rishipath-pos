<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feedback extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'feedbacks';

    protected $fillable = [
        'organization_id',
        'feedbackable_type',
        'feedbackable_id',
        'user_id',
        'parent_id',
        'type',
        'subject',
        'message',
        'status',
        'priority',
        'assigned_to',
        'resolved_at',
        'resolved_by',
        'attachments',
        'metadata',
    ];

    protected $casts = [
        'attachments' => 'array',
        'metadata' => 'array',
        'resolved_at' => 'datetime',
    ];

    // ==================== Relationships ====================

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The model this feedback belongs to (RetailStore, BulkOrderInquiry, etc.)
     */
    public function feedbackable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * User who created this feedback
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * User assigned to handle this feedback
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * User who resolved this feedback
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Parent feedback (for replies/threads)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Feedback::class, 'parent_id');
    }

    /**
     * Replies to this feedback
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Feedback::class, 'parent_id')->with('user')->latest();
    }

    // ==================== Scopes ====================

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOfStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOfPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', ['new', 'in_progress']);
    }

    public function scopeAssignedToMe($query)
    {
        return $query->where('assigned_to', auth()->id());
    }

    public function scopeForRetailStores($query)
    {
        return $query->where('feedbackable_type', RetailStore::class);
    }

    public function scopeForBulkOrders($query)
    {
        return $query->where('feedbackable_type', BulkOrderInquiry::class);
    }

    // ==================== Accessors ====================

    public function getIsResolvedAttribute(): bool
    {
        return in_array($this->status, ['resolved', 'closed']);
    }

    public function getIsUrgentAttribute(): bool
    {
        return $this->priority === 'urgent';
    }

    public function getRepliesCountAttribute(): int
    {
        return $this->replies()->count();
    }

    // ==================== Methods ====================

    /**
     * Mark feedback as resolved
     */
    public function markResolved(?int $resolvedBy = null): self
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy ?? auth()->id(),
        ]);

        return $this;
    }

    /**
     * Reopen resolved feedback
     */
    public function reopen(): self
    {
        $this->update([
            'status' => 'new',
            'resolved_at' => null,
            'resolved_by' => null,
        ]);

        return $this;
    }

    /**
     * Add a reply to this feedback
     */
    public function addReply(string $message, ?int $userId = null): self
    {
        return self::create([
            'organization_id' => $this->organization_id,
            'feedbackable_type' => $this->feedbackable_type,
            'feedbackable_id' => $this->feedbackable_id,
            'user_id' => $userId ?? auth()->id(),
            'parent_id' => $this->id,
            'type' => 'note',
            'message' => $message,
            'status' => $this->status,
            'priority' => $this->priority,
        ]);
    }
}

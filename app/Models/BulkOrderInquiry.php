<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class BulkOrderInquiry extends Model
{
    use HasFactory;

    /** Minimum quantity per product in a bulk order */
    public const MIN_QUANTITY = 10;

    protected $fillable = [
        'organization_id',
        'retail_store_id',
        'user_id',
        'name',
        'email',
        'phone',
        'phone_country_code',
        'company_name',
        'tax_number',
        'shipping_address',
        'shipping_area',
        'shipping_landmark',
        'shipping_city',
        'shipping_state',
        'shipping_pincode',
        'shipping_country',
        'products',
        'message',
        'special_instructions',
        'expected_delivery_date',
        'budget_range',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'products' => 'array',
        'expected_delivery_date' => 'date',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** All quotations generated for this inquiry */
    public function quotations(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'invoiceable')->where('type', 'quotation');
    }

    /** Latest quotation */
    public function latestQuotation(): MorphOne
    {
        return $this->morphOne(Invoice::class, 'invoiceable')
            ->where('type', 'quotation')
            ->latestOfMany();
    }

    // ─── Accessors ───────────────────────────────

    public function getTotalQuantityAttribute(): int
    {
        if (! is_array($this->products)) {
            return 0;
        }

        return (int) collect($this->products)->sum('quantity');
    }

    public function getProductNamesAttribute(): string
    {
        if (! is_array($this->products)) {
            return '';
        }

        return collect($this->products)->pluck('product_name')->join(', ');
    }

    // ─── Scopes ──────────────────────────────────

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }
}

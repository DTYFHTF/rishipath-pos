<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generic Invoice model — supports types: invoice, quotation, credit_note, proforma.
 *
 * Polymorphic via `invoiceable` — links to Sale, BulkOrderInquiry, or any model.
 * Reusable across the codebase: POS sale invoices, bulk order quotations, etc.
 */
class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'invoice_number',
        'type',
        'status',
        'invoiceable_type',
        'invoiceable_id',
        'customer_id',
        'retail_store_id',
        'recipient_name',
        'recipient_email',
        'recipient_phone',
        'recipient_address',
        'subtotal',
        'discount_amount',
        'discount_type',
        'tax_amount',
        'tax_details',
        'shipping_amount',
        'total_amount',
        'amount_paid',
        'amount_due',
        'currency',
        'issue_date',
        'due_date',
        'paid_date',
        'payment_method',
        'payment_reference',
        'notes',
        'terms_and_conditions',
        'footer_text',
        'metadata',
    ];

    protected $casts = [
        'tax_details' => 'array',
        'metadata' => 'array',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_date' => 'date',
    ];

    // ─── Relationships ───────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invoiceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function retailStore(): BelongsTo
    {
        return $this->belongsTo(RetailStore::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    // ─── Scopes ──────────────────────────────────

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeQuotations($query)
    {
        return $query->where('type', 'quotation');
    }

    public function scopeInvoices($query)
    {
        return $query->where('type', 'invoice');
    }

    // ─── Accessors ───────────────────────────────

    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== 'paid'
            && $this->due_date
            && $this->due_date->isPast();
    }

    public function getBalanceDueAttribute(): float
    {
        return (float) $this->total_amount - (float) $this->amount_paid;
    }

    // ─── Methods ─────────────────────────────────

    /**
     * Generate the next invoice number based on type and date.
     */
    public static function generateNumber(string $type = 'invoice', ?int $organizationId = null): string
    {
        $prefix = match ($type) {
            'quotation' => 'QUO',
            'credit_note' => 'CRN',
            'proforma' => 'PRO',
            default => 'INV',
        };

        $date = now()->format('Ymd');
        $query = static::where('invoice_number', 'like', "{$prefix}-{$date}-%");

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        $last = $query->orderByDesc('invoice_number')->first();

        if ($last) {
            $lastNum = (int) substr($last->invoice_number, -4);
            $next = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $next = '0001';
        }

        return "{$prefix}-{$date}-{$next}";
    }

    /**
     * Recalculate totals from line items.
     */
    public function recalculateTotals(): void
    {
        $this->loadMissing('lines');

        $subtotal = $this->lines->sum('line_total');
        $tax = $this->lines->sum('tax_amount');

        $this->subtotal = $subtotal;
        $this->tax_amount = $tax;
        $this->total_amount = $subtotal + $tax + (float) $this->shipping_amount - (float) $this->discount_amount;
        $this->amount_due = $this->total_amount - (float) $this->amount_paid;
        $this->saveQuietly();
    }

    /**
     * Mark invoice as paid.
     */
    public function markPaid(?string $method = null, ?string $reference = null): void
    {
        $this->update([
            'status' => 'paid',
            'amount_paid' => $this->total_amount,
            'amount_due' => 0,
            'paid_date' => now(),
            'payment_method' => $method,
            'payment_reference' => $reference,
        ]);
    }
}

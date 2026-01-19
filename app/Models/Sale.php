<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'organization_id',
        'store_id',
        'terminal_id',
        'receipt_number',
        'invoice_number',
        'date',
        'time',
        'cashier_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'subtotal',
        'discount_amount',
        'discount_type',
        'discount_reason',
        'tax_amount',
        'tax_details',
        'total_amount',
        'payment_method',
        'payment_status',
        'payment_reference',
        'amount_paid',
        'amount_change',
        'notes',
        'status',
        'is_synced',
        'synced_at',
    ];

    
    protected $casts = [
        'date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_change' => 'decimal:2',
        'tax_details' => 'array',
        'is_synced' => 'boolean',
        'synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($sale) {
            if (empty($sale->receipt_number)) {
                $sale->receipt_number = self::generateReceiptNumber($sale->store_id);
            }
        });

        static::saved(function ($sale) {
            if ($sale->customer_id && $sale->wasChanged(['status', 'total_amount'])) {
                $sale->customer?->recalculateTotals();
            }
        });

        static::deleted(function ($sale) {
            if ($sale->customer_id) {
                $sale->customer?->recalculateTotals();
            }
        });
    }

    public static function generateReceiptNumber(?int $storeId = null): string
    {
        $prefix = 'RCP';
        if ($storeId) {
            $store = \App\Models\Store::find($storeId);
            if ($store && $store->code) {
                $prefix = $store->code.'-RCP';
            }
        }
        $date = now()->format('ymd');
        $lastSale = self::where('receipt_number', 'like', "{$prefix}-{$date}%")
            ->orderBy('id', 'desc')
            ->first();
        $sequence = $lastSale ? (int) substr($lastSale->receipt_number, -4) + 1 : 1;
        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }

    /**
     * Recalculate sale totals from items.
     */
    public function recalculateTotals(): void
    {
        $this->loadMissing('items');
        
        $itemsSubtotal = $this->items->sum(fn($item) => $item->quantity * $item->selling_price);
        $itemsTax = $this->items->sum('tax_amount');
        
        $this->subtotal = $itemsSubtotal;
        $this->tax_amount = $itemsTax;
        $this->total_amount = $itemsSubtotal + $itemsTax - ($this->discount_amount ?? 0);
        $this->saveQuietly(); // Avoid triggering observers again
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function paymentSplits(): HasMany
    {
        return $this->hasMany(PaymentSplit::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CustomerLedgerEntry::class, 'reference_id')
            ->where('reference_type', 'Sale');
    }

    /**
     * Check if this sale has split payments
     */
    public function hasSplitPayments(): bool
    {
        return $this->paymentSplits()->count() > 1;
    }

    /**
     * Get total paid via split payments
     */
    public function getSplitPaymentsTotal(): float
    {
        return (float) $this->paymentSplits()->sum('amount');
    }
}

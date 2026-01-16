<?php

namespace App\Models;

use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Purchase extends Model
{
    protected $fillable = [
        'organization_id',
        'store_id',
        'supplier_id',
        'purchase_number',
        'purchase_date',
        'expected_delivery_date',
        'received_date',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'shipping_cost',
        'total',
        'amount_paid',
        'payment_status',
        'supplier_invoice_number',
        'invoice_file',
        'notes',
        'created_by',
        'received_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'expected_delivery_date' => 'date',
        'received_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function ($purchase) {
            if (empty($purchase->purchase_number)) {
                $purchase->purchase_number = self::generatePurchaseNumber($purchase->store_id);
            }
            if (empty($purchase->created_by)) {
                $purchase->created_by = Auth::id();
            }
        });
    }

    public static function generatePurchaseNumber(?int $storeId = null): string
    {
        $prefix = 'PUR';
        if ($storeId) {
            $store = Store::find($storeId);
            if ($store && $store->code) {
                $prefix = $store->code.'-PUR';
            }
        }

        $lastPurchase = self::where('purchase_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();

        $lastNumber = 0;
        if ($lastPurchase) {
            preg_match('/(\d+)$/', $lastPurchase->purchase_number, $matches);
            $lastNumber = (int) ($matches[1] ?? 0);
        }

        return $prefix.'-'.str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SupplierLedgerEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Recalculate totals from items.
     */
    public function recalculateTotals(): void
    {
        $subtotal = $this->items->sum('line_total');
        $taxAmount = $this->items->sum('tax_amount');
        $discountAmount = $this->items->sum('discount_amount');

        $this->subtotal = $subtotal;
        $this->tax_amount = $taxAmount;
        $this->discount_amount = $discountAmount;
        $this->total = $subtotal + $taxAmount - $discountAmount + $this->shipping_cost;
        $this->save();
    }

    /**
     * Mark purchase as received and update stock.
     */
    public function receive(?int $userId = null): void
    {
        DB::transaction(function () use ($userId) {
            foreach ($this->items as $item) {
                $qtyToReceive = $item->quantity_ordered - $item->quantity_received;

                if ($qtyToReceive > 0) {
                    // Update stock with audit trail
                    InventoryService::increaseStock(
                        $item->product_variant_id,
                        $this->store_id,
                        $qtyToReceive,
                        'purchase',
                        'Purchase',
                        $this->id,
                        $item->unit_cost,
                        "Purchase {$this->purchase_number}"
                    );

                    // Update item received quantity
                    $item->quantity_received = $item->quantity_ordered;
                    $item->save();

                    // Update variant cost price if needed
                    $variant = ProductVariant::find($item->product_variant_id);
                    if ($variant && $item->unit_cost > 0) {
                        $variant->cost_price = $item->unit_cost;
                        $variant->save();
                    }
                }
            }

            $this->status = 'received';
            $this->received_date = now();
            $this->received_by = $userId ?? Auth::id();
            $this->save();

            // Create supplier ledger entry for payable
            if ($this->supplier_id && $this->total > 0) {
                SupplierLedgerEntry::createPurchaseEntry($this);
            }
        });
    }

    /**
     * Record a payment against this purchase.
     */
    public function recordPayment(float $amount, string $paymentMethod, ?string $reference = null, ?string $notes = null): void
    {
        DB::transaction(function () use ($amount, $paymentMethod, $reference, $notes) {
            $this->amount_paid += $amount;

            if ($this->amount_paid >= $this->total) {
                $this->payment_status = 'paid';
            } elseif ($this->amount_paid > 0) {
                $this->payment_status = 'partial';
            }

            $this->save();

            if ($this->supplier_id) {
                SupplierLedgerEntry::createPaymentEntry($this, $amount, $paymentMethod, $reference, $notes);
            }
        });
    }

    public function getOutstandingAmountAttribute(): float
    {
        return max(0, $this->total - $this->amount_paid);
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->items->every(fn ($item) => $item->quantity_received >= $item->quantity_ordered);
    }
}

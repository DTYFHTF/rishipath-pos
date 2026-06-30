<?php

namespace App\Models;

use App\Services\InventoryService;
use App\Services\PricingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Purchase extends Model
{
    use HasFactory;

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

        // NOTE: Supplier ledger entries are created in:
        // - receive() method: SupplierLedgerEntry::createPurchaseEntry() for payables
        // - recordPayment() method: SupplierLedgerEntry::createPaymentEntry() for payments
        // We do NOT create entries on ::created because the purchase may not be received yet

        static::updated(function ($purchase) {
            // Auto-receive when status changes to 'received' and no batches exist yet
            if ($purchase->status === 'received' &&
                $purchase->wasChanged('status') &&
                $purchase->batches()->count() === 0) {
                $purchase->receive();
            }

            // Update ledger entry if payment status changed directly (e.g. from Filament admin)
            if ($purchase->supplier_id && $purchase->wasChanged('payment_status')) {
                if ($purchase->payment_status === 'paid' && $purchase->getOriginal('payment_status') !== 'paid') {
                    // Calculate the remaining amount that was just paid
                    $remainingAmount = (float) $purchase->total - (float) $purchase->getOriginal('amount_paid');
                    if ($remainingAmount > 0) {
                        // Prevent duplicate payment entries when payments are recorded via recordPayment()
                        // Check if a payment ledger entry for this exact amount already exists for this purchase
                        $existing = \App\Models\SupplierLedgerEntry::where('purchase_id', $purchase->id)
                            ->where('type', 'payment')
                            ->where('amount', -$remainingAmount)
                            ->exists();

                        if (! $existing) {
                            SupplierLedgerEntry::createPaymentEntry(
                                $purchase,
                                $remainingAmount,
                                'bank_transfer',
                                null,
                                "Full payment for {$purchase->purchase_number}"
                            );
                        }
                    }
                }
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

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class, 'purchase_id');
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

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
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
        $this->total = $subtotal + $this->shipping_cost;
        $this->save();
    }

    /**
     * Mark purchase as received and update stock.
     * This is the PRIMARY entry point for inventory - creates ProductBatches with full traceability.
     */
    public function receive(?int $quantity = null, ?int $userId = null): void
    {
        DB::transaction(function () use ($quantity, $userId) {
            foreach ($this->items as $item) {
                $remaining = $item->quantity_ordered - $item->quantity_received;
                $qtyToReceive = $quantity ? min($quantity, $remaining) : $remaining;

                if ($qtyToReceive > 0) {
                    // Create ProductBatch (source of truth) instead of directly updating stock
                    // This ensures full traceability: which batch came from which purchase
                    $batch = ProductBatch::create([
                        'purchase_id' => $this->id,
                        'product_variant_id' => $item->product_variant_id,
                        'store_id' => $this->store_id,
                        'batch_number' => $this->generateBatchNumber($item),
                        'supplier_id' => $this->supplier_id,
                        'purchase_date' => $this->purchase_date,
                        'expiry_date' => $item->expiry_date,
                        'purchase_price' => $item->unit_cost,
                        'quantity_received' => $qtyToReceive,
                        'quantity_remaining' => $qtyToReceive,
                        'quantity_sold' => 0,
                        'quantity_damaged' => 0,
                        'quantity_returned' => 0,
                        'notes' => "Purchase: {$this->purchase_number}",
                    ]);

                    // Link batch to purchase item for reference
                    $item->batch_id = $batch->id;

                    // Observer automatically syncs StockLevel from batch
                    // No need to call InventoryService::increaseStock() - that's for adjustments only

                    // Update item received quantity incrementally
                    $item->quantity_received += $qtyToReceive;
                    $item->save();

                    // Update variant cost price and selling prices from received supplier invoice
                    $variant = ProductVariant::find($item->product_variant_id);
                    if ($variant && $item->unit_cost > 0) {
                        $variant->cost_price = $item->unit_cost;

                        if (! $variant->manual_price_locked) {
                            $suggested = PricingService::suggestVariantPrices(
                                (float) $item->unit_cost,
                                (float) $variant->pack_size,
                                (string) $variant->unit,
                            );

                            $variant->base_price = $suggested['base_price'];
                            $variant->mrp_india = $suggested['mrp_india'];
                            $variant->selling_price_nepal = $suggested['selling_price_nepal'];
                        }

                        $variant->save();
                    }

                    // Create inventory movement audit trail
                    InventoryMovement::create([
                        'organization_id' => $this->organization_id,
                        'store_id' => $this->store_id,
                        'product_variant_id' => $item->product_variant_id,
                        'batch_id' => $batch->id,
                        'type' => 'purchase',
                        'quantity' => $qtyToReceive,
                        'unit' => $item->unit,
                        'from_quantity' => 0,
                        'to_quantity' => $qtyToReceive,
                        'reference_type' => 'Purchase',
                        'reference_id' => $this->id,
                        'cost_price' => $item->unit_cost,
                        'user_id' => $userId ?? Auth::id(),
                        'notes' => "Purchase received: {$this->purchase_number}",
                    ]);
                }
            }

            // Update status based on received quantities
            $allReceived = $this->items->every(fn ($item) => $item->quantity_received >= $item->quantity_ordered);
            $anyReceived = $this->items->some(fn ($item) => $item->quantity_received > 0);

            if ($allReceived) {
                $this->status = 'received';
                $this->received_date = now();
                $this->received_by = $userId ?? Auth::id();
            } elseif ($anyReceived && $this->status === 'draft') {
                $this->status = 'partial';
            }

            $this->save();

            // Create supplier ledger entry for payable
            if ($this->supplier_id && $this->total > 0) {
                SupplierLedgerEntry::createPurchaseEntry($this);
            }
        });
    }

    /**
     * Generate unique batch number for purchase item.
     */
    protected function generateBatchNumber($item): string
    {
        $variant = $item->productVariant;
        $date = now()->format('Ymd');

        // Format: PUR-YYYYMMDD-SKU-XXX
        $prefix = "PUR-{$date}-{$variant->sku}";

        $lastBatch = ProductBatch::where('batch_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        $sequence = 1;
        if ($lastBatch) {
            preg_match('/-(\d+)$/', $lastBatch->batch_number, $matches);
            $sequence = isset($matches[1]) ? (int) $matches[1] + 1 : 1;
        }

        return $prefix.'-'.str_pad($sequence, 3, '0', STR_PAD_LEFT);
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

    /**
     * Process a return for this purchase.
     *
     * @param  array<int, int>  $returnItems  Map of purchase_item_id => quantity_to_return
     * @return array<int, array{quantity_returned: int, return_amount: float}>
     */
    public function processReturn(array $returnItems, string $reason, ?string $notes): array
    {
        $processed = [];

        DB::transaction(function () use ($returnItems, $reason, $notes, &$processed) {
            foreach ($returnItems as $itemId => $qty) {
                $qty = (int) $qty;
                if ($qty <= 0) {
                    continue;
                }

                $item = $this->items()->find($itemId);
                if (! $item) {
                    throw new \InvalidArgumentException("Purchase item #{$itemId} not found.");
                }

                $maxReturnable = $item->quantity_received ?? $item->quantity_ordered;
                if ($qty > $maxReturnable) {
                    throw new \InvalidArgumentException(
                        "Cannot return {$qty} units of {$item->product_name}. Only {$maxReturnable} received."
                    );
                }

                $unitCost = (float) $item->unit_cost;
                $returnAmount = round($unitCost * $qty, 2);

                $return = PurchaseReturn::create([
                    'organization_id' => $this->organization_id,
                    'purchase_id' => $this->id,
                    'purchase_item_id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'batch_id' => $item->batch_id,
                    'store_id' => $this->store_id,
                    'quantity_returned' => $qty,
                    'unit_cost' => $unitCost,
                    'return_amount' => $returnAmount,
                    'reason' => $reason,
                    'notes' => $notes,
                    'status' => 'pending',
                ]);

                $processed[] = [
                    'quantity_returned' => $return->quantity_returned,
                    'return_amount' => $return->return_amount,
                ];
            }
        });

        return $processed;
    }
}

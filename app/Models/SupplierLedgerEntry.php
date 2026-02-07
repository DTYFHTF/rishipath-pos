<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierLedgerEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'supplier_id',
        'purchase_id',
        'type',
        'amount',
        'balance_after',
        'payment_method',
        'reference_number',
        'notes',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Create a ledger entry for a purchase (increases payable).
     * 
     * IMPORTANT: Only creates entries for unpaid/partial purchases (credit purchases).
     * Paid purchases should NOT create ledger entries as they don't create a payable.
     * 
     * Supplier Ledger = Accounts Payable (money we OWE suppliers)
     * - We only track purchases where we still owe money
     * - Paid purchases are not payables, so they don't appear in the ledger
     */
    public static function createPurchaseEntry(Purchase $purchase): ?self
    {
        // Only create ledger entry for unpaid or partially paid purchases
        // Paid purchases don't create a payable, so no ledger entry needed
        if ($purchase->payment_status === 'paid') {
            return null;
        }

        return DB::transaction(function () use ($purchase) {
            $supplier = Supplier::lockForUpdate()->find($purchase->supplier_id);

            $newBalance = ($supplier->current_balance ?? 0) + $purchase->total;
            $supplier->current_balance = $newBalance;
            $supplier->save();

            return self::create([
                'organization_id' => $purchase->organization_id,
                'supplier_id' => $purchase->supplier_id,
                'purchase_id' => $purchase->id,
                'type' => 'purchase',
                'amount' => $purchase->total,
                'balance_after' => $newBalance,
                'notes' => "Purchase {$purchase->purchase_number}",
                'created_by' => Auth::id(),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Create a ledger entry for a payment (decreases payable).
     */
    public static function createPaymentEntry(
        Purchase $purchase,
        float $amount,
        string $paymentMethod,
        ?string $reference = null,
        ?string $notes = null
    ): self {
        return DB::transaction(function () use ($purchase, $amount, $paymentMethod, $reference, $notes) {
            $supplier = Supplier::lockForUpdate()->find($purchase->supplier_id);

            $newBalance = ($supplier->current_balance ?? 0) - $amount;
            $supplier->current_balance = $newBalance;
            $supplier->save();

            return self::create([
                'organization_id' => $purchase->organization_id,
                'supplier_id' => $purchase->supplier_id,
                'purchase_id' => $purchase->id,
                'type' => 'payment',
                'amount' => -$amount,
                'balance_after' => $newBalance,
                'payment_method' => $paymentMethod,
                'reference_number' => $reference,
                'notes' => $notes ?? "Payment for {$purchase->purchase_number}",
                'created_by' => Auth::id(),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Create a return entry (decreases payable).
     */
    public static function createReturnEntry(
        Purchase $purchase,
        float $amount,
        ?string $notes = null
    ): self {
        return DB::transaction(function () use ($purchase, $amount, $notes) {
            $supplier = Supplier::lockForUpdate()->find($purchase->supplier_id);

            $newBalance = ($supplier->current_balance ?? 0) - $amount;
            $supplier->current_balance = $newBalance;
            $supplier->save();

            return self::create([
                'organization_id' => $purchase->organization_id,
                'supplier_id' => $purchase->supplier_id,
                'purchase_id' => $purchase->id,
                'type' => 'return',
                'amount' => -$amount,
                'balance_after' => $newBalance,
                'notes' => $notes ?? "Return for {$purchase->purchase_number}",
                'created_by' => Auth::id(),
                'created_at' => now(),
            ]);
        });
    }
}

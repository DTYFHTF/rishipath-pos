<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerLedgerEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'store_id',
        'customer_id', // Deprecated: kept for backward compatibility
        'ledgerable_type',
        'ledgerable_id',
        'entry_type',
        'reference_type',
        'reference_id',
        'reference_number',
        'debit_amount',
        'credit_amount',
        'balance',
        'description',
        'notes',
        'transaction_date',
        'due_date',
        'payment_method',
        'payment_reference',
        'status',
        'created_by',
    ];

    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'transaction_date' => 'date',
        'due_date' => 'date',
    ];

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Polymorphic relationship - can be Customer or Supplier
     */
    public function ledgerable(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scopes
     */
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('ledgerable_type', Customer::class)
            ->where('ledgerable_id', $customerId);
    }

    public function scopeForSupplier($query, $supplierId)
    {
        return $query->where('ledgerable_type', Supplier::class)
            ->where('ledgerable_id', $supplierId);
    }

    public function scopeForLedgerable($query, $ledgerable)
    {
        return $query->where('ledgerable_type', get_class($ledgerable))
            ->where('ledgerable_id', $ledgerable->id);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue')
            ->orWhere(function ($q) {
                $q->where('status', 'pending')
                    ->where('due_date', '<', now());
            });
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    /**
     * Helper Methods
     */
    public function isOverdue(): bool
    {
        return $this->status === 'overdue' ||
               ($this->status === 'pending' && $this->due_date && $this->due_date->isPast());
    }

    public function getDaysOverdue(): int
    {
        if (! $this->isOverdue() || ! $this->due_date) {
            return 0;
        }

        return now()->diffInDays($this->due_date);
    }

    public function getAgingBucket(): string
    {
        $days = $this->getDaysOverdue();

        if ($days <= 0) {
            return 'Current';
        }
        if ($days <= 30) {
            return '1-30 days';
        }
        if ($days <= 60) {
            return '31-60 days';
        }
        if ($days <= 90) {
            return '61-90 days';
        }

        return '90+ days';
    }

    /**
     * Static Methods
     */
    public static function createSaleEntry(Sale $sale): ?self
    {
        $customer = $sale->customer;
        if (!$customer) {
            return null;
        }
        
        $previousBalance = self::getLedgerableBalance($customer);
        
        // Customer Ledger (Accounts Receivable) standard accounting:
        // - DEBIT = Customer OWES us (increases their debt)
        // - CREDIT = Customer PAYS us (decreases their debt)
        
        // Full Audit Trail Approach:
        // 1. Credit Sale: Record DEBIT (customer owes) - balance increases
        // 2. Cash/Card/UPI Sale: Record DEBIT (sale) + CREDIT (payment) - balance stays same (full audit)
        
        $isCredit = $sale->payment_method === 'credit';
        
        // Normalize payment method to allowed values
        $allowedMethods = ['cash', 'card', 'upi', 'bank_transfer', 'cheque', 'credit'];
        $paymentMethod = in_array($sale->payment_method, $allowedMethods) ? $sale->payment_method : null;
        
        if ($isCredit) {
            // Credit sale: Customer OWES us (DEBIT only)
            return self::create([
                'organization_id' => $sale->organization_id,
                'store_id' => $sale->store_id,
                'customer_id' => $customer->id, // Backward compatibility
                'ledgerable_type' => Customer::class,
                'ledgerable_id' => $customer->id,
                'entry_type' => 'receivable',
                'reference_type' => 'Sale',
                'reference_id' => $sale->id,
                'reference_number' => $sale->invoice_number,
                'debit_amount' => $sale->total_amount,
                'credit_amount' => 0,
                'balance' => $previousBalance + $sale->total_amount,
                'description' => "Credit Sale - Invoice #{$sale->invoice_number} ({$sale->store->name})",
                'transaction_date' => $sale->date,
                'due_date' => now()->addDays(30),
                'payment_method' => $paymentMethod,
                'status' => 'pending',
                'created_by' => $sale->cashier_id,
            ]);
        } else {
            // Cash/Card/UPI sale: Record TWO entries for full audit trail
            // Entry 1: DEBIT (sale transaction)
            $saleEntry = self::create([
                'organization_id' => $sale->organization_id,
                'store_id' => $sale->store_id,
                'customer_id' => $customer->id, // Backward compatibility
                'ledgerable_type' => Customer::class,
                'ledgerable_id' => $customer->id,
                'entry_type' => 'receivable',
                'reference_type' => 'Sale',
                'reference_id' => $sale->id,
                'reference_number' => $sale->invoice_number,
                'debit_amount' => $sale->total_amount,
                'credit_amount' => 0,
                'balance' => $previousBalance + $sale->total_amount,
                'description' => "Sale - Invoice #{$sale->invoice_number} ({$sale->store->name})",
                'transaction_date' => $sale->date,
                'due_date' => null,
                'payment_method' => $paymentMethod,
                'status' => 'completed',
                'created_by' => $sale->cashier_id,
            ]);
            
            // Entry 2: CREDIT (immediate payment)
            self::create([
                'organization_id' => $sale->organization_id,
                'store_id' => $sale->store_id,
                'customer_id' => $customer->id, // Backward compatibility
                'ledgerable_type' => Customer::class,
                'ledgerable_id' => $customer->id,
                'entry_type' => 'payment',
                'reference_type' => 'Sale',
                'reference_id' => $sale->id,
                'reference_number' => $sale->invoice_number,
                'debit_amount' => 0,
                'credit_amount' => $sale->total_amount,
                'balance' => $previousBalance + $sale->total_amount - $sale->total_amount, // Net zero
                'description' => "Payment - Invoice #{$sale->invoice_number} ({$sale->store->name}) - Paid via {$paymentMethod}",
                'transaction_date' => $sale->date,
                'due_date' => null,
                'payment_method' => $paymentMethod,
                'payment_reference' => $sale->invoice_number,
                'status' => 'completed',
                'created_by' => $sale->cashier_id,
            ]);
            
            return $saleEntry;
        }
    }

    public static function createPaymentEntry(Customer $customer, array $data): self
    {
        $previousBalance = self::getLedgerableBalance($customer);

        return self::create([
            'organization_id' => $data['organization_id'],
            'store_id' => $data['store_id'] ?? null,
            'customer_id' => $customer->id, // Backward compatibility
            'ledgerable_type' => Customer::class,
            'ledgerable_id' => $customer->id,
            'entry_type' => 'payment',
            'reference_number' => $data['reference_number'] ?? null,
            'debit_amount' => 0,
            'credit_amount' => $data['amount'],
            'balance' => $previousBalance - $data['amount'],
            'description' => $data['description'] ?? 'Payment received',
            'transaction_date' => $data['transaction_date'] ?? now(),
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['payment_reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'completed',
            'created_by' => $data['created_by'] ?? auth()->id(),
        ]);
    }

    public static function getCustomerBalance($customerId): float
    {
        $latestEntry = self::forCustomer($customerId)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $latestEntry ? (float) $latestEntry->balance : 0;
    }

    public static function getCustomerOutstanding($customerId): float
    {
        return (float) self::forCustomer($customerId)
            ->where('status', 'pending')
            ->sum('debit_amount');
    }

    /**
     * Polymorphic Balance Methods
     */
    public static function getLedgerableBalance($ledgerable): float
    {
        $latestEntry = self::forLedgerable($ledgerable)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $latestEntry ? (float) $latestEntry->balance : 0;
    }

    public static function getLedgerableOutstanding($ledgerable): float
    {
        return (float) self::forLedgerable($ledgerable)
            ->where('status', 'pending')
            ->sum('debit_amount');
    }

    /**
     * Create supplier payment entry (for purchases)
     */
    public static function createSupplierPaymentEntry(Supplier $supplier, array $data): self
    {
        $previousBalance = self::getLedgerableBalance($supplier);

        // Supplier Ledger (Accounts Payable) - inverted logic:
        // - CREDIT = We OWE supplier (increases our debt)
        // - DEBIT = We PAY supplier (decreases our debt)
        
        return self::create([
            'organization_id' => $data['organization_id'],
            'store_id' => $data['store_id'] ?? null,
            'ledgerable_type' => Supplier::class,
            'ledgerable_id' => $supplier->id,
            'entry_type' => 'payment',
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'debit_amount' => $data['amount'], // Payment reduces our debt
            'credit_amount' => 0,
            'balance' => $previousBalance - $data['amount'],
            'description' => $data['description'] ?? 'Payment made',
            'transaction_date' => $data['transaction_date'] ?? now(),
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['payment_reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'completed',
            'created_by' => $data['created_by'] ?? auth()->id(),
        ]);
    }

    /**
     * Create supplier purchase entry
     */
    public static function createSupplierPurchaseEntry($purchase): ?self
    {
        if (!$purchase->supplier_id) {
            return null;
        }

        $supplier = Supplier::find($purchase->supplier_id);
        if (!$supplier) {
            return null;
        }

        $previousBalance = self::getLedgerableBalance($supplier);
        $isCredit = $purchase->payment_status === 'unpaid';

        if ($isCredit) {
            // Credit purchase: We OWE supplier (CREDIT)
            return self::create([
                'organization_id' => $purchase->organization_id,
                'store_id' => $purchase->store_id,
                'ledgerable_type' => Supplier::class,
                'ledgerable_id' => $supplier->id,
                'entry_type' => 'receivable',
                'reference_type' => 'Purchase',
                'reference_id' => $purchase->id,
                'reference_number' => $purchase->purchase_number,
                'debit_amount' => 0,
                'credit_amount' => (float) $purchase->total,
                'balance' => $previousBalance + (float) $purchase->total,
                'description' => "Credit Purchase - {$purchase->purchase_number}",
                'transaction_date' => $purchase->purchase_date,
                'due_date' => $purchase->expected_delivery_date ?? now()->addDays(30),
                'status' => 'pending',
                'created_by' => $purchase->created_by,
            ]);
        } else {
            // Paid purchase: Record TWO entries
            $purchaseEntry = self::create([
                'organization_id' => $purchase->organization_id,
                'store_id' => $purchase->store_id,
                'ledgerable_type' => Supplier::class,
                'ledgerable_id' => $supplier->id,
                'entry_type' => 'receivable',
                'reference_type' => 'Purchase',
                'reference_id' => $purchase->id,
                'reference_number' => $purchase->purchase_number,
                'debit_amount' => 0,
                'credit_amount' => (float) $purchase->total,
                'balance' => $previousBalance + $purchase->total,
                'description' => "Purchase - {$purchase->purchase_number}",
                'transaction_date' => $purchase->purchase_date,
                'status' => 'completed',
                'created_by' => $purchase->created_by,
            ]);

            // Payment entry
            self::create([
                'organization_id' => $purchase->organization_id,
                'store_id' => $purchase->store_id,
                'ledgerable_type' => Supplier::class,
                'ledgerable_id' => $supplier->id,
                'entry_type' => 'payment',
                'reference_type' => 'Purchase',
                'reference_id' => $purchase->id,
                'reference_number' => $purchase->purchase_number,
                'debit_amount' => (float) $purchase->total,
                'credit_amount' => 0,
                'balance' => $previousBalance + $purchase->total - $purchase->total,
                'description' => "Payment - {$purchase->purchase_number}",
                'transaction_date' => $purchase->purchase_date,
                'status' => 'completed',
                'created_by' => $purchase->created_by,
            ]);

            return $purchaseEntry;
        }
    }
}

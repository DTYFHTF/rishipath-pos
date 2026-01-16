<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerLedgerEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'store_id',
        'customer_id',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scopes
     */
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
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
        if (!$this->isOverdue() || !$this->due_date) {
            return 0;
        }
        
        return now()->diffInDays($this->due_date);
    }

    public function getAgingBucket(): string
    {
        $days = $this->getDaysOverdue();
        
        if ($days <= 0) return 'Current';
        if ($days <= 30) return '1-30 days';
        if ($days <= 60) return '31-60 days';
        if ($days <= 90) return '61-90 days';
        
        return '90+ days';
    }

    /**
     * Static Methods
     */
    public static function createSaleEntry(Sale $sale): self
    {
        $customer = $sale->customer;
        $previousBalance = self::getCustomerBalance($customer->id);
        // Normalize payment method to allowed ledger methods
        $allowed = ['cash', 'card', 'upi', 'bank_transfer', 'cheque', 'credit'];
        $ledgerPaymentMethod = in_array($sale->payment_method, $allowed, true) ? $sale->payment_method : null;

        return self::create([
            'organization_id' => $sale->organization_id,
            'store_id' => $sale->store_id,
            'customer_id' => $customer->id,
            'entry_type' => 'sale',
            'reference_type' => 'Sale',
            'reference_id' => $sale->id,
            'reference_number' => $sale->invoice_number,
            'debit_amount' => $sale->total_amount,
            'credit_amount' => 0,
            'balance' => $previousBalance + $sale->total_amount,
            'description' => "Sale - Invoice #{$sale->invoice_number}",
            'transaction_date' => $sale->date,
            'due_date' => $sale->payment_method === 'credit' ? now()->addDays(30) : null,
            'payment_method' => $ledgerPaymentMethod,
            'status' => $sale->payment_method === 'credit' ? 'pending' : 'completed',
            'created_by' => $sale->cashier_id,
        ]);
    }

    public static function createPaymentEntry(Customer $customer, array $data): self
    {
        $previousBalance = self::getCustomerBalance($customer->id);
        
        return self::create([
            'organization_id' => $data['organization_id'],
            'store_id' => $data['store_id'] ?? null,
            'customer_id' => $customer->id,
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
            ->orderBy('created_at', 'desc')
            ->first();
        
        return $latestEntry ? (float) $latestEntry->balance : 0;
    }

    public static function getCustomerOutstanding($customerId): float
    {
        return (float) self::forCustomer($customerId)
            ->where('status', 'pending')
            ->sum('debit_amount');
    }
}

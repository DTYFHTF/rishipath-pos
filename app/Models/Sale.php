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
}

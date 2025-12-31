<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    protected $fillable = [
        'sale_id',
        'payment_method',
        'amount',
        'payment_gateway',
        'transaction_id',
        'payment_status',
        'payment_response',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_response' => 'array',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_variant_id',
        'store_id',
        'quantity',
        'reserved_quantity',
        'reorder_level',
        'last_counted_at',
        'last_movement_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'last_counted_at' => 'datetime',
        'last_movement_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['available_quantity'];

    public function getAvailableQuantityAttribute(): int
    {
        return (int) ($this->quantity - $this->reserved_quantity);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}

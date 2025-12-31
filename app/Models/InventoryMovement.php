<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'store_id',
        'product_variant_id',
        'batch_id',
        'type',
        'quantity',
        'unit',
        'from_quantity',
        'to_quantity',
        'reference_type',
        'reference_id',
        'cost_price',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'from_quantity' => 'decimal:3',
        'to_quantity' => 'decimal:3',
        'cost_price' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

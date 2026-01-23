<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductBatch extends Model
{
    protected $fillable = [
        'product_variant_id',
        'purchase_id',
        'store_id',
        'batch_number',
        'manufactured_date',
        'expiry_date',
        'purchase_date',
        'purchase_price',
        'supplier_id',
        'quantity_received',
        'quantity_remaining',
        'quantity_sold',
        'quantity_damaged',
        'quantity_returned',
        'notes',
    ];

    protected $casts = [
        'manufactured_date' => 'date',
        'expiry_date' => 'date',
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
        'quantity_received' => 'integer',
        'quantity_remaining' => 'integer',
        'quantity_sold' => 'integer',
        'quantity_damaged' => 'integer',
        'quantity_returned' => 'integer',
    ];

    protected static function booted(): void
    {
        // Enforce purchase_id requirement for NEW batches only (traceability)
        // Note: Existing batches without purchase_id are grandfathered in
        static::creating(function ($batch) {
            if (empty($batch->purchase_id)) {
                \Log::warning('Attempted to create batch without purchase_id', [
                    'batch_number' => $batch->batch_number,
                    'variant_id' => $batch->product_variant_id,
                ]);
                throw new \Exception('Batches can only be created through Purchase Orders. Please create a Purchase Order and receive it to generate batches.');
            }
        });
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'batch_id');
    }
}

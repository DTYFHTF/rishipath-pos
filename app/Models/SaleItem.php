<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_variant_id',
        'batch_id',
        'product_name',
        'product_sku',
        'quantity',
        'unit',
        'price_per_unit',
        'cost_price',
        'subtotal',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_per_unit' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function ($item) {
            $item->calculateTotals();
        });

        static::updating(function ($item) {
            $item->calculateTotals();
        });

        static::saved(function ($item) {
            if ($item->sale) {
                $item->sale->recalculateTotals();
            }
        });

        static::deleted(function ($item) {
            if ($item->sale) {
                $item->sale->recalculateTotals();
            }
        });
    }

    protected function calculateTotals(): void
    {
        $this->subtotal = $this->quantity * $this->price_per_unit;
        $this->tax_amount = $this->subtotal * ($this->tax_rate / 100);
        $this->total = $this->subtotal + $this->tax_amount - ($this->discount_amount ?? 0);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }
}

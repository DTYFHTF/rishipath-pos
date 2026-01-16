<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_variant_id',
        'product_name',
        'product_sku',
        'quantity_ordered',
        'quantity_received',
        'unit',
        'unit_cost',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'line_total',
        'batch_id',
        'expiry_date',
        'notes',
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function ($item) {
            $item->calculateTotals();
            $item->populateProductDetails();
        });

        static::updating(function ($item) {
            $item->calculateTotals();
        });

        static::saved(function ($item) {
            $item->purchase->recalculateTotals();
        });

        static::deleted(function ($item) {
            $item->purchase->recalculateTotals();
        });
    }

    protected function calculateTotals(): void
    {
        $subtotal = $this->quantity_ordered * $this->unit_cost;
        $this->tax_amount = $subtotal * ($this->tax_rate / 100);
        $this->line_total = $subtotal + $this->tax_amount - ($this->discount_amount ?? 0);
    }

    protected function populateProductDetails(): void
    {
        if ($this->product_variant_id && empty($this->product_name)) {
            $variant = ProductVariant::with('product')->find($this->product_variant_id);
            if ($variant) {
                $this->product_name = $variant->product->name . ' - ' . $variant->pack_size . $variant->unit;
                $this->product_sku = $variant->sku;
                $this->unit = $variant->unit;
            }
        }
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }

    public function getPendingQuantityAttribute(): float
    {
        return max(0, $this->quantity_ordered - $this->quantity_received);
    }
}

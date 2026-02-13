<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_variant_id',
        'item_name',
        'item_sku',
        'item_description',
        'quantity',
        'unit',
        'unit_price',
        'discount_amount',
        'tax_amount',
        'tax_rate',
        'line_total',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'line_total' => 'decimal:2',
        'metadata' => 'array',
    ];

    // ─── Relationships ───────────────────────────

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    // ─── Methods ─────────────────────────────────

    /**
     * Recalculate line total from quantity, price, discount, tax.
     */
    public function recalculate(): void
    {
        $base = (float) $this->quantity * (float) $this->unit_price;
        $afterDiscount = $base - (float) $this->discount_amount;
        $this->tax_amount = $afterDiscount * ((float) $this->tax_rate / 100);
        $this->line_total = $afterDiscount + (float) $this->tax_amount;
    }
}

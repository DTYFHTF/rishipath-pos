<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStorePricing extends Model
{
    protected $table = 'product_store_pricing';

    protected $fillable = [
        'product_variant_id',
        'store_id',
        'custom_price',
        'custom_tax_rate',
        'reorder_level',
        'max_stock_level',
    ];

    protected $casts = [
        'custom_price' => 'decimal:2',
        'custom_tax_rate' => 'decimal:2',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}

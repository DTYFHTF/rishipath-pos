<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductComposition extends Model
{
    protected $fillable = [
        'product_id',
        'component_product_id',
        'ingredient_id',
        'name',
        'quantity',
        'sort',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    /**
     * The blend/finished product this line belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The raw-material product used for costing.
     */
    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}

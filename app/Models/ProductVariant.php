<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductVariant extends Model
{
    use HasFactory;
    protected $fillable = [
        'product_id',
        'sku',
        'pack_size',
        'unit',
        'base_price',
        'mrp_india',
        'selling_price_nepal',
        'cost_price',
        'barcode',
        'hsn_code',
        'weight',
        'active',
    ];

    protected $casts = [
        'pack_size' => 'decimal:3',
        'base_price' => 'decimal:2',
        'mrp_india' => 'decimal:2',
        'selling_price_nepal' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'weight' => 'decimal:3',
        'active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function storePricing(): HasMany
    {
        return $this->hasMany(ProductStorePricing::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Get the selling price based on organization context
     */
    public function getSellingPrice(?\App\Models\Organization $organization = null): float
    {
        return \App\Services\PricingService::getSellingPrice($this, $organization);
    }

    /**
     * Get formatted price with currency symbol
     */
    public function getFormattedPrice(?\App\Models\Organization $organization = null, int $decimals = 2): string
    {
        $price = $this->getSellingPrice($organization);
        return \App\Services\PricingService::formatPrice($price, $organization, $decimals);
    }
}

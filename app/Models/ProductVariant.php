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
        'image_1',
        'image_2',
        'image_3',
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
     * Generate semantic variant SKU: [Product SKU]-[Size][Unit]
     * Example: AYU-OIL-AML-200ML
     */
    public static function generateVariantSku(
        ?string $productSku,
        $packSize,
        ?string $unit
    ): string {
        $base = $productSku ?? 'PROD';
        
        // Format size (remove decimals if whole number)
        $size = is_numeric($packSize) ? (int) $packSize : $packSize;
        
        // Abbreviate unit
        $unitCode = static::abbreviateUnit($unit ?? 'PCS');
        
        return strtoupper("{$base}-{$size}{$unitCode}");
    }

    /**
     * Get standard unit abbreviations
     */
    protected static function abbreviateUnit(string $unit): string
    {
        $map = [
            'GMS' => 'G',
            'KG' => 'KG',
            'ML' => 'ML',
            'L' => 'L',
            'PCS' => 'PC',
        ];

        $upper = strtoupper($unit);
        return $map[$upper] ?? substr($upper, 0, 2);
    }

    protected static function booted()
    {
        parent::booted();

        static::creating(function ($variant) {
            if (empty($variant->sku)) {
                $variant->sku = static::generateSkuFromVariant($variant);
            }
        });

        static::updating(function ($variant) {
            // Regenerate SKU if key fields changed
            if ($variant->isDirty(['product_id', 'pack_size', 'unit'])) {
                $variant->sku = static::generateSkuFromVariant($variant);
            }
        });
    }

    /**
     * Generate SKU from variant instance
     */
    protected static function generateSkuFromVariant(ProductVariant $variant): string
    {
        $productSku = $variant->product?->sku ?? 
            Product::find($variant->product_id)?->sku;
        
        return static::generateVariantSku(
            $productSku,
            $variant->pack_size,
            $variant->unit
        );
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

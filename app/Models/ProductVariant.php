<?php

namespace App\Models;

use App\Services\PricingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'manual_price_locked',
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
        'manual_price_locked' => 'boolean',
        'cost_price' => 'decimal:2',
        'weight' => 'decimal:3',
        'active' => 'boolean',
    ];

    /**
     * Human pack label — "500 G", "1 KG".
     *
     * pack_size is cast to decimal:3, so concatenating it raw renders
     * "500.000 g" on screen. Every surface that shows a pack size (POS search,
     * cart, dealer price list) goes through here so they cannot drift apart.
     */
    public function getPackLabelAttribute(): string
    {
        $size = (float) $this->pack_size;

        $displaySize = floor($size) == $size
            ? (string) (int) $size
            : rtrim(rtrim(number_format($size, 3, '.', ''), '0'), '.');

        $displayUnit = match (strtoupper(trim((string) $this->unit))) {
            'GMS', 'GM', 'GRAM', 'GRAMS' => 'G',
            'KGS', 'KILOGRAM', 'KILOGRAMS' => 'KG',
            default => strtoupper(trim((string) $this->unit)),
        };

        return trim($displaySize.' '.$displayUnit);
    }

    /**
     * Pack size normalised to grams/ml for sorting and comparison.
     *
     * Ordering by the raw pack_size column puts "1 KG" before "20 G", because
     * it only sees 1 < 20. Null for packs measured in units we cannot compare
     * (pcs, packets), which sort last.
     */
    public function getComparableSizeAttribute(): ?float
    {
        $size = (float) $this->pack_size;

        return match (strtoupper(trim((string) $this->unit))) {
            'G', 'GM', 'GMS', 'GRAM', 'GRAMS', 'ML' => $size,
            'KG', 'KGS', 'KILOGRAM', 'KILOGRAMS', 'L' => $size * 1000,
            default => null,
        };
    }

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
            static::fillSuggestedPrices($variant);

            if (empty($variant->sku)) {
                $variant->sku = static::generateSkuFromVariant($variant);
            }
        });

        static::updating(function ($variant) {
            static::fillSuggestedPrices($variant);

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

    protected static function fillSuggestedPrices(ProductVariant $variant): void
    {
        if ($variant->manual_price_locked) {
            return;
        }

        if ($variant->cost_price === null || $variant->pack_size === null || blank($variant->unit)) {
            return;
        }

        $suggested = PricingService::suggestVariantPrices(
            (float) $variant->cost_price,
            (float) $variant->pack_size,
            (string) $variant->unit,
        );

        if ($variant->base_price === null) {
            $variant->base_price = $suggested['base_price'];
        }

        if ($variant->mrp_india === null) {
            $variant->mrp_india = $suggested['mrp_india'];
        }

        if ($variant->selling_price_nepal === null) {
            $variant->selling_price_nepal = $suggested['selling_price_nepal'];
        }
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

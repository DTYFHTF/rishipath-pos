<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'category_id',
        'sku',
        'name',
        'name_nepali',
        'name_hindi',
        'name_sanskrit',
        'description',
        'product_type',
        'unit_type',
        'has_variants',
        'tax_category',
        'requires_batch',
        'requires_expiry',
        'shelf_life_months',
        'is_prescription_required',
        'ingredients',
        'usage_instructions',
        'image_url',
        'image_1',
        'image_2',
        'image_3',
        'active',
    ];

    protected $casts = [
        'has_variants' => 'boolean',
        'requires_batch' => 'boolean',
        'requires_expiry' => 'boolean',
        'is_prescription_required' => 'boolean',
        'active' => 'boolean',
        'ingredients' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Generate semantic SKU: [Category]-[Type]-[Product]
     * Example: AYU-OIL-AML (Ayurveda Oil - Amla)
     */
    public static function generateSemanticSku(
        ?string $categoryName,
        ?string $productType,
        ?string $productName
    ): string {
        $categoryCode = static::abbreviate($categoryName ?? 'PROD', 3);
        $typeCode = static::abbreviateProductType($productType ?? 'GEN');
        $nameCode = static::abbreviate($productName ?? 'ITEM', 3);

        return strtoupper("{$categoryCode}-{$typeCode}-{$nameCode}");
    }

    /**
     * Abbreviate a string intelligently
     */
    protected static function abbreviate(string $text, int $length = 3): string
    {
        // Remove parentheses and their content
        $text = preg_replace('/\([^)]*\)/', '', $text);
        
        // Remove common words
        $text = preg_replace('/\b(the|and|for|with|oil|powder|tea|capsule|choorna|tailam)\b/i', '', $text);
        $text = trim($text);

        // Split into words
        $words = preg_split('/[\s\-_]+/', $text);
        $words = array_filter($words, fn($w) => strlen($w) > 0);

        if (count($words) > 1) {
            // Use first letters of each word
            $abbr = '';
            foreach ($words as $word) {
                if (strlen($word) > 0) {
                    $abbr .= strtoupper(substr($word, 0, 1));
                }
                if (strlen($abbr) >= $length) {
                    break;
                }
            }
            return substr($abbr, 0, $length);
        }

        // Single word: take first N letters
        return strtoupper(substr($text, 0, $length));
    }

    /**
     * Get standard product type codes
     */
    protected static function abbreviateProductType(string $type): string
    {
        $map = [
            'choorna' => 'PWD',  // Powder
            'tailam' => 'OIL',
            'ghritam' => 'GHE',  // Ghee
            'rasayana' => 'RAS',
            'capsules' => 'CAP',
            'tea' => 'TEA',
            'honey' => 'HNY',
            'others' => 'OTH',
        ];

        return $map[strtolower($type)] ?? strtoupper(substr($type, 0, 3));
    }

    /**
     * Boot to auto-generate product SKU when creating/updating
     */
    protected static function booted()
    {
        parent::booted();

        static::creating(function ($product) {
            if (empty($product->sku)) {
                $product->sku = static::generateSkuFromProduct($product);
            }
        });

        static::updating(function ($product) {
            // Regenerate SKU if key fields changed
            if ($product->isDirty(['name', 'category_id', 'product_type'])) {
                $product->sku = static::generateSkuFromProduct($product);
            }
        });
    }

    /**
     * Generate SKU from product instance
     */
    protected static function generateSkuFromProduct(Product $product): string
    {
        $categoryName = $product->category?->name ?? 
            Category::find($product->category_id)?->name;
        
        return static::generateSemanticSku(
            $categoryName,
            $product->product_type,
            $product->name
        );
    }
}

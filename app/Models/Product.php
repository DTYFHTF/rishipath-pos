<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'category_id',
        'sku',
        'name',
        'name_nepali',
        'name_romanized',
        'name_hindi',
        'name_sanskrit',
        'description',
        'product_type',
        'unit_type',
        'retail_markup',
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
        'retail_markup' => 'decimal:2',
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
     * Blend recipe lines (for composed products like Garam Masala).
     */
    public function compositions(): HasMany
    {
        return $this->hasMany(ProductComposition::class)->orderBy('sort');
    }

    /**
     * Raw-material cost per kg, derived from the cheapest-per-gram costed
     * variant (prefers the largest pack). Null when no variant has a cost.
     */
    public function costPerKg(): ?float
    {
        $variant = $this->variants
            ->where('active', true)
            ->filter(fn ($v) => (float) $v->cost_price > 0)
            ->sortByDesc(fn ($v) => strtoupper($v->unit ?? 'GMS') === 'KG' ? (float) $v->pack_size : (float) $v->pack_size / 1000)
            ->first();

        if (! $variant) {
            return null;
        }

        $kg = strtoupper($variant->unit ?? 'GMS') === 'KG'
            ? (float) $variant->pack_size
            : (float) $variant->pack_size / 1000;

        return $kg > 0 ? (float) $variant->cost_price / $kg : null;
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
        $words = array_filter($words, fn ($w) => strlen($w) > 0);

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

    /**
     * Turns a raw image_url/image_1/2/3 value into a URL a browser can load.
     *
     * The column holds two different kinds of value depending on how the photo
     * got there: a Filament FileUpload or products:sync-web-images writes a
     * bare storage-disk path ("product-images/web/...", needs Storage::url());
     * the legacy productv2 seeder and older imports write a path already
     * rooted at public/ ("/images/productv2-webp/...", needs asset() as-is).
     * Mixing the two up — passing the raw value straight to <img src> — is why
     * a chunk of recently-synced photos silently fell back to a placeholder.
     */
    public static function resolveImageUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return str_starts_with($path, '/')
            ? asset(ltrim($path, '/'))
            : \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    }
}

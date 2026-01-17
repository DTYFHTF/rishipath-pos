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
}

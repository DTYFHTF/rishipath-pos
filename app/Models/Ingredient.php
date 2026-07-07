<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_hero' => 'boolean',
        'active' => 'boolean',
        'taste_sweet' => 'integer',
        'taste_bitter' => 'integer',
        'taste_pungent' => 'integer',
        'aroma' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * POS product carrying this ingredient's live pricing (nullable).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function compositions(): HasMany
    {
        return $this->hasMany(ProductComposition::class);
    }
}

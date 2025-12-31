<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'organization_id',
        'customer_code',
        'name',
        'phone',
        'email',
        'address',
        'city',
        'date_of_birth',
        'total_purchases',
        'total_spent',
        'loyalty_points',
        'notes',
        'active',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'total_spent' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}

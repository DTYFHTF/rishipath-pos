<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Supplier extends Model
{
    use HasFactory;
    protected $fillable = [
        'organization_id',
        'supplier_code',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'country_code',
        'tax_number',
        'notes',
        'active',
        'current_balance',
    ];

    protected $casts = [
        'active' => 'boolean',
        'current_balance' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(CustomerLedgerEntry::class, 'ledgerable');
    }
}

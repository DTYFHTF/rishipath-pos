<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Terminal extends Model
{
    protected $fillable = [
        'store_id',
        'code',
        'name',
        'device_id',
        'printer_config',
        'scanner_config',
        'last_receipt_number',
        'last_synced_at',
        'active',
    ];

    protected $casts = [
        'printer_config' => 'array',
        'scanner_config' => 'array',
        'active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}

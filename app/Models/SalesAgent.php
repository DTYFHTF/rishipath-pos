<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesAgent extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'agent_code',
        'name',
        'phone',
        'email',
        'address',
        'territory',
        'commission_retail_pct',
        'commission_wholesale_profit_pct',
        'min_wholesale_amount',
        'active',
        'notes',
    ];

    protected $casts = [
        'commission_retail_pct' => 'decimal:2',
        'commission_wholesale_profit_pct' => 'decimal:2',
        'min_wholesale_amount' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'sales_agent_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SalesAgentLedger::class);
    }

    public function getCurrentBalanceAttribute(): float
    {
        $credit = (float) $this->ledgerEntries()->where('entry_type', 'credit')->sum('amount');
        $debit = (float) $this->ledgerEntries()->where('entry_type', 'debit')->sum('amount');
        $adjust = (float) $this->ledgerEntries()->where('entry_type', 'adjustment')->sum('amount');

        return $credit - $debit + $adjust;
    }
}

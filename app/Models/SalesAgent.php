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

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'sales_agent_id');
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

    /**
     * Computed: total agent earnings (commission entries not yet settled)
     */
    public function getCurrentEarnings(): float
    {
        return (float) $this->ledgerEntries()
            ->where('entry_type', 'commission')
            ->sum('amount');
    }

    /**
     * Get today's sales metrics
     */
    public function getTodayMetrics(): array
    {
        $today = now()->toDateString();
        
        $totalSales = (float) $this->sales()
            ->where('date', $today)
            ->sum('total_amount');

        $collections = (float) $this->sales()
            ->where('date', $today)
            ->where('payment_status', 'paid')
            ->sum('amount_paid');

        $earnings = (float) $this->ledgerEntries()
            ->where('entry_type', 'commission')
            ->whereDate('created_at', $today)
            ->sum('amount');

        return [
            'total_sales' => $totalSales,
            'collections' => $collections,
            'earnings' => $earnings,
        ];
    }

    /**
     * Get settlement summary by payment mode
     */
    public function getSettlementSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $query = $this->sales()
            ->where('payment_status', 'paid');

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $sales = $query->get();

        $summary = [
            'cash' => 0,
            'upi' => 0,
            'card' => 0,
            'esewa' => 0,
            'khalti' => 0,
            'other' => 0,
            'total' => 0,
        ];

        foreach ($sales as $sale) {
            $mode = $sale->payment_method ?? 'other';
            if (isset($summary[$mode])) {
                $summary[$mode] += (float) $sale->amount_paid;
            }
            $summary['total'] += (float) $sale->amount_paid;
        }

        return $summary;
    }
}

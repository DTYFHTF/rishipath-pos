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
        'loyalty_tier_id',
        'birthday',
        'last_birthday_bonus_at',
        'loyalty_enrolled_at',
        'notes',
        'active',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'birthday' => 'date',
        'last_birthday_bonus_at' => 'date',
        'loyalty_enrolled_at' => 'datetime',
        'total_spent' => 'decimal:2',
        'loyalty_points' => 'integer',
        'active' => 'boolean',
    ];

    /**
     * Boot function to auto-generate customer code
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($customer) {
            if (empty($customer->customer_code)) {
                // Generate code: CUST-YYYYMMDD-XXXX
                $date = date('Ymd');
                $lastCustomer = static::where('customer_code', 'like', "CUST-{$date}-%")
                    ->orderBy('customer_code', 'desc')
                    ->first();
                
                if ($lastCustomer) {
                    $lastNumber = (int) substr($lastCustomer->customer_code, -4);
                    $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                } else {
                    $newNumber = '0001';
                }
                
                $customer->customer_code = "CUST-{$date}-{$newNumber}";
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function loyaltyTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class);
    }

    public function loyaltyPoints(): HasMany
    {
        return $this->hasMany(LoyaltyPoint::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CustomerLedgerEntry::class);
    }

    /**
     * Check if customer is enrolled in loyalty program
     */
    public function isLoyaltyMember(): bool
    {
        return $this->loyalty_enrolled_at !== null;
    }

    /**
     * Check if birthday bonus is due
     */
    public function isBirthdayBonusDue(): bool
    {
        if (!$this->birthday) {
            return false;
        }

        $today = now();
        $birthday = $this->birthday->setYear($today->year);

        // Check if today is birthday
        if (!$today->isSameDay($birthday)) {
            return false;
        }

        // Check if bonus already given this year
        if ($this->last_birthday_bonus_at && 
            $this->last_birthday_bonus_at->year === $today->year) {
            return false;
        }

        return true;
    }

    /**
     * Get current outstanding balance
     */
    public function getOutstandingBalance(): float
    {
        return CustomerLedgerEntry::getCustomerBalance($this->id);
    }

    /**
     * Get pending/overdue amounts
     */
    public function getOutstandingAmount(): float
    {
        return CustomerLedgerEntry::getCustomerOutstanding($this->id);
    }

    /**
     * Check if customer has credit limit exceeded
     */
    public function hasCreditLimitExceeded(float $creditLimit): bool
    {
        return $this->getOutstandingBalance() > $creditLimit;
    }

    /**
     * Get ledger statement for date range
     */
    public function getLedgerStatement($startDate = null, $endDate = null)
    {
        $query = $this->ledgerEntries()->orderBy('transaction_date', 'desc');

        if ($startDate) {
            $query->where('transaction_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('transaction_date', '<=', $endDate);
        }

        return $query->get();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSplit extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'payment_method',
        'amount',
        'reference_number',
        'card_last4',
        'card_type',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Helper Methods
     */
    public function getPaymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'cash' => 'Cash',
            'card' => 'Card',
            'upi' => 'QR',
            'bank_transfer' => 'Bank Transfer',
            'cheque' => 'Cheque',
            'credit' => 'Credit',
            'wallet' => 'Wallet',
            default => ucfirst($this->payment_method),
        };
    }

    public function hasCardDetails(): bool
    {
        return ! empty($this->card_last4) || ! empty($this->card_type);
    }

    public function getFormattedCardInfo(): ?string
    {
        if (! $this->hasCardDetails()) {
            return null;
        }

        $parts = array_filter([
            $this->card_type,
            $this->card_last4 ? "****{$this->card_last4}" : null,
        ]);

        return implode(' ', $parts);
    }
}

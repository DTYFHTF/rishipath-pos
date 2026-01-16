<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PosSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'store_id',
        'cashier_id',
        'customer_id',
        'session_key',
        'session_name',
        'cart_items',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'status',
        'parked_at',
        'completed_at',
        'notes',
        'display_order',
    ];

    protected $casts = [
        'cart_items' => 'array',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'parked_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeParked($query)
    {
        return $query->where('status', 'parked');
    }

    public function scopeForCashier($query, $cashierId)
    {
        return $query->where('cashier_id', $cashierId);
    }

    public function scopeForStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Helper Methods
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isParked(): bool
    {
        return $this->status === 'parked';
    }

    public function getItemCount(): int
    {
        if (! $this->cart_items) {
            return 0;
        }

        return collect($this->cart_items)->sum('quantity');
    }

    public function park(): bool
    {
        return $this->update([
            'status' => 'parked',
            'parked_at' => now(),
        ]);
    }

    public function resume(): bool
    {
        return $this->update([
            'status' => 'active',
            'parked_at' => null,
        ]);
    }

    public function complete(): bool
    {
        return $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Static Methods
     */
    public static function createNew(array $data): self
    {
        return self::create([
            'organization_id' => $data['organization_id'],
            'store_id' => $data['store_id'],
            'cashier_id' => $data['cashier_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'session_key' => Str::uuid()->toString(),
            'session_name' => $data['session_name'] ?? 'New Cart',
            'cart_items' => [],
            'status' => 'active',
            'display_order' => self::getNextDisplayOrder($data['cashier_id']),
        ]);
    }

    public static function getActiveSessions($cashierId): \Illuminate\Database\Eloquent\Collection
    {
        return self::forCashier($cashierId)
            ->whereIn('status', ['active', 'parked'])
            ->orderBy('display_order')
            ->get();
    }

    public static function getNextDisplayOrder($cashierId): int
    {
        return self::forCashier($cashierId)
            ->whereIn('status', ['active', 'parked'])
            ->max('display_order') + 1;
    }

    public function updateCart(array $cartItems): bool
    {
        // Calculate totals
        $subtotal = collect($cartItems)->sum(fn ($item) => $item['price'] * $item['quantity']);
        $discountAmount = collect($cartItems)->sum(fn ($item) => $item['discount'] ?? 0);
        $taxAmount = collect($cartItems)->sum(fn ($item) => $item['tax'] ?? 0);
        $totalAmount = $subtotal - $discountAmount + $taxAmount;

        return $this->update([
            'cart_items' => $cartItems,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ]);
    }
}

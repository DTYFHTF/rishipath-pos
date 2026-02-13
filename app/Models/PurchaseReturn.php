<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class PurchaseReturn extends Model
{
    protected $fillable = [
        'organization_id',
        'purchase_id',
        'purchase_item_id',
        'product_variant_id',
        'batch_id',
        'store_id',
        'return_number',
        'return_date',
        'quantity_returned',
        'unit_cost',
        'return_amount',
        'reason',
        'notes',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'return_date' => 'date',
        'quantity_returned' => 'integer',
        'unit_cost' => 'decimal:2',
        'return_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($return) {
            if (empty($return->return_number)) {
                $return->return_number = self::generateReturnNumber($return->store_id);
            }
            if (empty($return->created_by)) {
                $return->created_by = Auth::id();
            }
            if (empty($return->return_date)) {
                $return->return_date = now();
            }
        });
    }

    public static function generateReturnNumber(?int $storeId = null): string
    {
        $prefix = 'RET';
        if ($storeId) {
            $store = Store::find($storeId);
            if ($store && $store->code) {
                $prefix = $store->code . '-RET';
            }
        }

        $lastReturn = self::where('return_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();

        $lastNumber = 0;
        if ($lastReturn) {
            preg_match('/(\d+)$/', $lastReturn->return_number, $matches);
            $lastNumber = (int) ($matches[1] ?? 0);
        }

        return $prefix . '-' . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

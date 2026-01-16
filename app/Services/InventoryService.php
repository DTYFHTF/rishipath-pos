<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Adjust stock with full audit trail.
     *
     * @param  float  $quantityChange  Positive = increase, negative = decrease
     * @param  string  $type  One of: purchase, sale, adjustment, transfer, damage, return
     * @param  string|null  $referenceType  e.g. 'Sale', 'Purchase', 'StockAdjustment'
     */
    public static function adjustStock(
        int $variantId,
        int $storeId,
        float $quantityChange,
        string $type,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?float $costPrice = null,
        ?string $notes = null,
        ?int $userId = null
    ): StockLevel {
        return DB::transaction(function () use ($variantId, $storeId, $quantityChange, $type, $referenceType, $referenceId, $costPrice, $notes, $userId) {
            $variant = ProductVariant::findOrFail($variantId);

            $stock = StockLevel::lockForUpdate()->firstOrCreate(
                [
                    'product_variant_id' => $variantId,
                    'store_id' => $storeId,
                ],
                [
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'reorder_level' => 10,
                ]
            );

            $fromQuantity = $stock->quantity;
            $toQuantity = $fromQuantity + $quantityChange;

            // Prevent negative stock (optional: remove if allowing negative)
            if ($toQuantity < 0) {
                $toQuantity = 0;
            }

            $stock->quantity = $toQuantity;
            $stock->last_movement_at = now();
            $stock->save();

            // Create movement record
            InventoryMovement::create([
                'organization_id' => $variant->product->organization_id ?? Auth::user()?->organization_id ?? 1,
                'store_id' => $storeId,
                'product_variant_id' => $variantId,
                'batch_id' => null,
                'type' => $type,
                'quantity' => abs($quantityChange),
                'unit' => $variant->unit ?? 'pcs',
                'from_quantity' => $fromQuantity,
                'to_quantity' => $toQuantity,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'cost_price' => $costPrice ?? $variant->cost_price,
                'user_id' => $userId ?? Auth::id(),
                'notes' => $notes,
            ]);

            return $stock;
        });
    }

    /**
     * Decrease stock (convenience wrapper for sales).
     */
    public static function decreaseStock(
        int $variantId,
        int $storeId,
        float $quantity,
        string $type = 'sale',
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?float $costPrice = null,
        ?string $notes = null,
        ?int $userId = null
    ): StockLevel {
        return self::adjustStock(
            $variantId,
            $storeId,
            -abs($quantity),
            $type,
            $referenceType,
            $referenceId,
            $costPrice,
            $notes,
            $userId
        );
    }

    /**
     * Increase stock (convenience wrapper for purchases).
     */
    public static function increaseStock(
        int $variantId,
        int $storeId,
        float $quantity,
        string $type = 'purchase',
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?float $costPrice = null,
        ?string $notes = null,
        ?int $userId = null
    ): StockLevel {
        return self::adjustStock(
            $variantId,
            $storeId,
            abs($quantity),
            $type,
            $referenceType,
            $referenceId,
            $costPrice,
            $notes,
            $userId
        );
    }

    /**
     * Transfer stock between stores.
     */
    public static function transferStock(
        int $variantId,
        int $fromStoreId,
        int $toStoreId,
        float $quantity,
        ?string $notes = null,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use ($variantId, $fromStoreId, $toStoreId, $quantity, $notes, $userId) {
            $fromStock = self::decreaseStock(
                $variantId,
                $fromStoreId,
                $quantity,
                'transfer',
                'StoreTransfer',
                $toStoreId,
                null,
                "Transfer out to store {$toStoreId}".($notes ? ": {$notes}" : ''),
                $userId
            );

            $toStock = self::increaseStock(
                $variantId,
                $toStoreId,
                $quantity,
                'transfer',
                'StoreTransfer',
                $fromStoreId,
                null,
                "Transfer in from store {$fromStoreId}".($notes ? ": {$notes}" : ''),
                $userId
            );

            return ['from' => $fromStock, 'to' => $toStock];
        });
    }

    /**
     * Get current stock for a variant at a store.
     */
    public static function getStock(int $variantId, int $storeId): float
    {
        return StockLevel::where('product_variant_id', $variantId)
            ->where('store_id', $storeId)
            ->value('quantity') ?? 0;
    }

    /**
     * Get stock movement history for a variant.
     */
    public static function getMovementHistory(int $variantId, ?int $storeId = null, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        $query = InventoryMovement::where('product_variant_id', $variantId)
            ->orderByDesc('created_at');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->limit($limit)->get();
    }
}

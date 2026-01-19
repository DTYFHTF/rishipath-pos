<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\ProductBatch;
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
        int $productVariantId,
        int $storeId,
        float $quantityChange,
        string $type,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?float $costPrice = null,
        ?string $notes = null,
        ?int $userId = null
    ): StockLevel {
        return DB::transaction(function () use ($productVariantId, $storeId, $quantityChange, $type, $referenceType, $referenceId, $costPrice, $notes, $userId) {
            $variant = ProductVariant::findOrFail($productVariantId);

            $stock = StockLevel::lockForUpdate()->firstOrCreate(
                [
                    'product_variant_id' => $productVariantId,
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

            // Prevent negative stock and throw when insufficient
            if ($toQuantity < 0) {
                throw new \Exception('Insufficient stock');
            }

            $stock->quantity = $toQuantity;
            $stock->last_movement_at = now();
            $stock->save();

            // Sync Product Batches: update sum of remaining quantities
            self::syncBatchQuantities($productVariantId, $storeId);

            // Create movement record
            InventoryMovement::create([
                'organization_id' => $variant->product->organization_id ?? Auth::user()?->organization_id ?? 1,
                'store_id' => $storeId,
                'product_variant_id' => $productVariantId,
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
     * Sync batch quantities to match StockLevel total.
     * Distributes stock level quantity proportionally across non-expired batches.
     */
    public static function syncBatchQuantities(int $productVariantId, int $storeId): void
    {
        $stock = StockLevel::where('product_variant_id', $productVariantId)
            ->where('store_id', $storeId)
            ->first();

        if (!$stock) {
            return;
        }

        $batches = ProductBatch::where('product_variant_id', $productVariantId)
            ->where('store_id', $storeId)
            ->where('quantity_remaining', '>', 0)
            ->where(function($q) {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>=', now());
            })
            ->orderBy('expiry_date', 'asc')
            ->get();

        if ($batches->isEmpty()) {
            return;
        }

        // Calculate total batch quantity
        $totalBatchQty = $batches->sum('quantity_remaining');
        $stockQty = $stock->quantity;

        // If stock and batch totals match, no sync needed
        if ($totalBatchQty === $stockQty) {
            return;
        }

        // Distribute stock as integers across batches while preserving totals.
        if ($totalBatchQty > 0) {
            $assigned = 0;
            $newQuantities = [];

            foreach ($batches as $batch) {
                $ratio = $batch->quantity_remaining / $totalBatchQty;
                $qty = (int) floor($stockQty * $ratio);
                $newQuantities[] = $qty;
                $assigned += $qty;
            }

            // Distribute any remainder (+1) to the earliest batches
            $remainder = $stockQty - $assigned;
            for ($i = 0; $i < $remainder; $i++) {
                $newQuantities[$i % count($newQuantities)]++;
            }

            foreach ($batches as $idx => $batch) {
                $batch->quantity_remaining = $newQuantities[$idx];
                $batch->save();
            }
        } else {
            // No existing batches with quantity, distribute evenly
            $count = $batches->count();
            $qtyPerBatch = intdiv($stockQty, $count);
            $remainder = $stockQty % $count;

            foreach ($batches as $idx => $batch) {
                $batchQty = $qtyPerBatch + ($idx < $remainder ? 1 : 0);
                $batch->quantity_remaining = $batchQty;
                $batch->save();
            }
        }
    }

    /**
     * Decrease stock (convenience wrapper for sales).
     */
    public static function decreaseStock(
        int $productVariantId,
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
            $productVariantId,
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
        int $productVariantId,
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
            $productVariantId,
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
        int $productVariantId,
        int $fromStoreId,
        int $toStoreId,
        float $quantity,
        ?string $notes = null,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use ($productVariantId, $fromStoreId, $toStoreId, $quantity, $notes, $userId) {
            $fromStock = self::decreaseStock(
                $productVariantId,
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
                $productVariantId,
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

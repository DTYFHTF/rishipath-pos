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
     * @param  bool  $skipBatchSync  Skip batch synchronization (used when batches already allocated via FIFO)
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
        ?int $userId = null,
        bool $skipBatchSync = false
    ): StockLevel {
        return DB::transaction(function () use ($productVariantId, $storeId, $quantityChange, $type, $referenceType, $referenceId, $costPrice, $notes, $userId, $skipBatchSync) {
            return self::adjustStockInternal($productVariantId, $storeId, $quantityChange, $type, $referenceType, $referenceId, $costPrice, $notes, $userId, $skipBatchSync);
        });
    }

    /**
     * Internal method to adjust stock without transaction wrapper.
     * Use this when already inside a transaction.
     */
    protected static function adjustStockInternal(
        int $productVariantId,
        int $storeId,
        float $quantityChange,
        string $type,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?float $costPrice = null,
        ?string $notes = null,
        ?int $userId = null,
        bool $skipBatchSync = false
    ): StockLevel {
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
        // Skip sync when batches were already allocated (e.g., from decreaseStock with FIFO)
        if (!$skipBatchSync) {
            self::syncBatchQuantities($productVariantId, $storeId);
        }

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
     * Uses FIFO batch allocation - deducts from oldest expiring batches first.
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
        return DB::transaction(function () use ($productVariantId, $storeId, $quantity, $type, $referenceType, $referenceId, $costPrice, $notes, $userId) {
            // For sales and transfers, allocate from batches using FIFO (oldest expiring first)
            if (in_array($type, ['sale', 'transfer'], true)) {
                self::allocateFromBatches($productVariantId, $storeId, $quantity, $referenceType, $referenceId, $notes, $userId);
            }
            
            // Update stock_levels (skip batch sync since we already allocated via FIFO)
            // Use internal method to avoid nested transactions
            return self::adjustStockInternal(
                $productVariantId,
                $storeId,
                -abs($quantity),
                $type,
                $referenceType,
                $referenceId,
                $costPrice,
                $notes,
                $userId,
                true // skipBatchSync = true (batches already allocated above)
            );
        });
    }
    
    /**
     * Decrease stock and return allocated batch information.
     * Useful when you need to know which batches were used (e.g., for sale_items.batch_id).
     */
    public static function decreaseStockWithBatchInfo(
        int $productVariantId,
        int $storeId,
        float $quantity,
        string $type = 'sale',
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?float $costPrice = null,
        ?string $notes = null,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use ($productVariantId, $storeId, $quantity, $type, $referenceType, $referenceId, $costPrice, $notes, $userId) {
            $allocatedBatches = [];
            
            // For sales and transfers, allocate from batches using FIFO (oldest expiring first)
            if (in_array($type, ['sale', 'transfer'], true)) {
                $allocatedBatches = self::allocateFromBatches($productVariantId, $storeId, $quantity, $referenceType, $referenceId, $notes, $userId);
            }
            
            // Update stock_levels (skip batch sync since we already allocated via FIFO)
            // Use internal method to avoid nested transactions
            $stockLevel = self::adjustStockInternal(
                $productVariantId,
                $storeId,
                -abs($quantity),
                $type,
                $referenceType,
                $referenceId,
                $costPrice,
                $notes,
                $userId,
                true // skipBatchSync = true (batches already allocated above)
            );
            
            return [
                'stock_level' => $stockLevel,
                'allocated_batches' => $allocatedBatches,
            ];
        });
    }

    /**
     * Allocate quantity from product batches using FIFO (First Expiry, First Out).
     * Updates quantity_remaining and quantity_sold on batches.
     * Returns array of allocated batches with quantities.
     */
    protected static function allocateFromBatches(
        int $productVariantId,
        int $storeId,
        float $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $userId = null
    ): array {
        $remaining = $quantity;
        $allocatedBatches = [];
        
        // Get batches ordered by expiry (FIFO), then by ID (oldest first)
        $batches = ProductBatch::where('product_variant_id', $productVariantId)
            ->where('store_id', $storeId)
            ->where('quantity_remaining', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>=', now());
            })
            ->orderBy('expiry_date', 'asc')
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();

        // Disable ProductBatchObserver to prevent automatic StockLevel sync during batch updates
        // We'll manually update StockLevel after all batches are allocated
        ProductBatch::withoutEvents(function () use ($batches, &$remaining, $productVariantId, $storeId, $referenceType, $referenceId, $notes, $userId, &$allocatedBatches) {
            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $allocate = min($remaining, $batch->quantity_remaining);
                
                // Track allocated batch
                $allocatedBatches[] = [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'quantity' => $allocate,
                ];
                
                // Update batch quantities
                $batch->quantity_remaining -= $allocate;
                $batch->quantity_sold += $allocate;
                $batch->save();

                // Create movement record linked to this batch
                $variant = ProductVariant::find($productVariantId);
                InventoryMovement::create([
                    'organization_id' => $variant->product->organization_id ?? Auth::user()?->organization_id ?? 1,
                    'store_id' => $storeId,
                    'product_variant_id' => $productVariantId,
                    'batch_id' => $batch->id,
                    'type' => 'sale',
                    'quantity' => $allocate,
                    'unit' => $variant->unit ?? 'pcs',
                    'from_quantity' => $batch->quantity_remaining + $allocate,
                    'to_quantity' => $batch->quantity_remaining,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'cost_price' => $batch->purchase_price ?? $variant->cost_price,
                    'user_id' => $userId ?? Auth::id(),
                    'notes' => $notes ? "{$notes} (Batch: {$batch->batch_number})" : "Batch: {$batch->batch_number}",
                ]);

                $remaining -= $allocate;
            }
        });

        if ($remaining > 0) {
            throw new \Exception("Insufficient batch stock. Needed {$quantity}, allocated " . ($quantity - $remaining));
        }
        
        return $allocatedBatches;
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

            // Create a receiving ProductBatch record at the destination store
            // so transferred stock has traceability and can be allocated later.
            $variant = ProductVariant::find($productVariantId);
            $batch = ProductBatch::create([
                'purchase_id' => null,
                'product_variant_id' => $productVariantId,
                'store_id' => $toStoreId,
                'batch_number' => 'TRF-' . now()->format('YmdHis') . '-' . ($fromStoreId) . '-' . ($toStoreId),
                'manufactured_date' => null,
                'expiry_date' => null,
                'purchase_date' => now(),
                'purchase_price' => $variant?->cost_price ?? 0,
                'supplier_id' => null,
                'quantity_received' => (int) $quantity,
                'quantity_remaining' => (int) $quantity,
                'quantity_sold' => 0,
                'quantity_damaged' => 0,
                'quantity_returned' => 0,
                'notes' => "Transfer from store {$fromStoreId}" . ($notes ? ": {$notes}" : ''),
            ]);

            // Now increase stock at destination (this will sync batch quantities)
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

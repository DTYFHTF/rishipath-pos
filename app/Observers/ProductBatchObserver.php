<?php

namespace App\Observers;

use App\Models\ProductBatch;
use App\Models\StockLevel;

class ProductBatchObserver
{
    /**
     * Handle the ProductBatch "created" event.
     */
    public function created(ProductBatch $batch): void
    {
        $this->syncStockLevel($batch);
    }

    /**
     * Handle the ProductBatch "updated" event.
     */
    public function updated(ProductBatch $batch): void
    {
        $this->syncStockLevel($batch);
    }

    /**
     * Handle the ProductBatch "deleted" event.
     */
    public function deleted(ProductBatch $batch): void
    {
        $this->syncStockLevel($batch);
    }

    /**
     * Sync stock level for the batch's variant and store.
     */
    protected function syncStockLevel(ProductBatch $batch): void
    {
        // Calculate total quantity from all batches for this variant+store
        $totalQuantity = ProductBatch::where('product_variant_id', $batch->product_variant_id)
            ->where('store_id', $batch->store_id)
            ->sum('quantity_remaining');

        // Update or create stock level
        StockLevel::updateOrCreate(
            [
                'product_variant_id' => $batch->product_variant_id,
                'store_id' => $batch->store_id,
            ],
            [
                'quantity' => (int) $totalQuantity,
                'last_movement_at' => now(),
            ]
        );
    }
}

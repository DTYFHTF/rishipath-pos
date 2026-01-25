<?php

namespace Database\Seeders;

use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use Illuminate\Database\Seeder;

class InitialBatchSeeder extends Seeder
{
    public function run(): void
    {
        $stocks = StockLevel::where('quantity', '>', 0)->get();

        foreach ($stocks as $stock) {
            $existing = ProductBatch::where('product_variant_id', $stock->product_variant_id)
                ->where('store_id', $stock->store_id)
                ->exists();

            if ($existing) {
                continue;
            }

            $variant = ProductVariant::find($stock->product_variant_id);

            if (! $variant) {
                continue;
            }

            $expiry = null;
            if ($variant->product?->requires_expiry && $variant->product?->shelf_life_months) {
                $expiry = now()->addMonths($variant->product->shelf_life_months)->toDateString();
            }

            // Create a legacy initial batch. We intentionally bypass model events
            // because ProductBatch::creating enforces a purchase_id for new batches.
            ProductBatch::withoutEvents(function () use ($stock, $variant, $expiry) {
                ProductBatch::create([
                    'product_variant_id' => $stock->product_variant_id,
                    'store_id' => $stock->store_id,
                    'batch_number' => 'INIT-'.$stock->product_variant_id.'-'.time(),
                    'purchase_id' => null,
                    'purchase_date' => now()->toDateString(),
                    'purchase_price' => $variant->cost_price ?? 0,
                    'quantity_received' => (int) $stock->quantity,
                    'quantity_remaining' => (int) $stock->quantity,
                    'quantity_sold' => 0,
                    'expiry_date' => $expiry,
                    'notes' => 'Initial batch created from stock level during seeding',
                ]);
            });
        }

        $this->command->info('✅ Initial batches created for variants missing batches.');
    }
}

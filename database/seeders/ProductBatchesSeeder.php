<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ProductBatchesSeeder extends Seeder
{
    public function run()
    {
        // For each product_variant, create 1-3 batches and update stock_levels
        $variants = DB::table('product_variants')->get();
        // Load existing suppliers to associate with batches
        $supplierIds = DB::table('suppliers')->pluck('id')->toArray();

        foreach ($variants as $variant) {
            // find or create stock level for store 1 (safe default)
            $storeId = 1;

            $stock = DB::table('stock_levels')
                ->where('product_variant_id', $variant->id)
                ->where('store_id', $storeId)
                ->first();

            if (! $stock) {
                $stockId = DB::table('stock_levels')->insertGetId([
                    'organization_id' => $variant->organization_id ?? 1,
                    'store_id' => $storeId,
                    'product_variant_id' => $variant->id,
                    'quantity' => 0,
                    'unit' => $variant->unit ?? 'pcs',
                    'last_movement_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $stockId = $stock->id;
            }

            // create between 1 and 3 batches
            $batchCount = rand(1, 3);
            $totalAdded = 0;

            for ($i = 0; $i < $batchCount; $i++) {
                $qty = rand(10, 200);
                $totalAdded += $qty;

                // Assign a supplier if available
                $supplierId = count($supplierIds) ? $supplierIds[array_rand($supplierIds)] : null;

                // Determine expiry: some expired, some expiring soon, others normal
                $rand = rand(1, 100);
                if ($rand <= 10) {
                    // 10% expired (30+ days in the past)
                    $expiry = Carbon::now()->subDays(rand(30, 365))->toDateString();
                } elseif ($rand <= 30) {
                    // next 20% expiring within 7 days
                    $expiry = Carbon::now()->addDays(rand(0, 7))->toDateString();
                } else {
                    // normal expiry 3-24 months
                    $expiry = Carbon::now()->addMonths(rand(3, 24))->toDateString();
                }

                // For expired batches, set some of the quantity as damaged/returned
                $quantityRemaining = $qty;
                $quantityDamaged = 0;
                if (Carbon::parse($expiry)->isPast()) {
                    $quantityDamaged = (int) floor($qty * (rand(20, 60) / 100));
                    $quantityRemaining = max(0, $qty - $quantityDamaged);
                }

                $batchId = DB::table('product_batches')->insertGetId([
                    'product_variant_id' => $variant->id,
                    'store_id' => $storeId,
                    'batch_number' => strtoupper(Str::random(8)),
                    'manufactured_date' => null,
                    'expiry_date' => $expiry,
                    'purchase_date' => now()->toDateString(),
                    'purchase_price' => $variant->cost_price ?? 0,
                    'supplier_id' => $supplierId,
                    'quantity_received' => $qty,
                    'quantity_remaining' => $quantityRemaining,
                    'quantity_sold' => 0,
                    'quantity_damaged' => $quantityDamaged,
                    'quantity_returned' => 0,
                    'notes' => 'Seeded batch',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Optionally create stock movements history
                DB::table('inventory_movements')->insert([
                    'organization_id' => 1,
                    'store_id' => $storeId,
                    'product_variant_id' => $variant->id,
                    'batch_id' => $batchId,
                    'type' => 'purchase',
                    'quantity' => $qty,
                    'unit' => $variant->unit ?? 'pcs',
                    'from_quantity' => $stock->quantity ?? 0,
                    'to_quantity' => ($stock->quantity ?? 0) + $totalAdded,
                    'reference_type' => 'Seeder',
                    'reference_id' => null,
                    'cost_price' => $variant->cost_price ?? 0,
                    'user_id' => null,
                    'notes' => 'Seeded batch ' . ($i + 1),
                    'created_at' => now(),
                ]);
            }

            // update stock_levels quantity and last_movement_at
            DB::table('stock_levels')
                ->where('id', $stockId)
                ->update([
                    'quantity' => DB::raw('quantity + ' . (int) $totalAdded),
                    'last_movement_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $this->command->info('Product batches seeded and stock levels updated.');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StockLevel;
use Illuminate\Database\Seeder;

class SampleProductBatchesSeeder extends Seeder
{
    /**
     * Seed sample product batches for existing products to make dashboard metrics meaningful
     */
    public function run(): void
    {
        $org = Organization::where('slug', 'rishipath')->first();
        
        if (!$org) {
            $this->command->error('❌ Organization not found. Run InitialSetupSeeder first.');
            return;
        }

        $store = Store::where('organization_id', $org->id)->first();
        
        if (!$store) {
            $this->command->error('❌ Store not found. Run InitialSetupSeeder first.');
            return;
        }

        $variants = ProductVariant::whereHas('product', function($q) use ($org) {
            $q->where('organization_id', $org->id);
        })->get();

        if ($variants->isEmpty()) {
            $this->command->error('❌ No product variants found. Please create products first.');
            return;
        }

        $this->command->info('📦 Creating sample product batches...');

        $batchesCreated = 0;

        foreach ($variants as $variant) {
            // Create 1-3 batches per variant
            $numBatches = rand(1, 3);

            for ($i = 0; $i < $numBatches; $i++) {
                $manufacturedDate = now()->subDays(rand(30, 180));
                $expiryMonths = rand(12, 36); // 1-3 years shelf life
                
                // Some batches should be expiring soon or expired for testing
                if ($i === 0 && rand(1, 5) === 1) {
                    // 20% chance - expired batch
                    $expiryDate = now()->subDays(rand(1, 30));
                } elseif ($i === 1 && rand(1, 4) === 1) {
                    // 25% chance - expiring soon (within 30 days)
                    $expiryDate = now()->addDays(rand(1, 30));
                } else {
                    // Normal expiry
                    $expiryDate = $manufacturedDate->copy()->addMonths($expiryMonths);
                }

                $purchasePrice = $variant->cost_price ?? ($variant->base_price * 0.6); // 60% of base price
                $quantity = rand(10, 200);

                ProductBatch::create([
                    'product_variant_id' => $variant->id,
                    'store_id' => $store->id,
                    'batch_number' => 'BATCH-' . strtoupper(substr(md5($variant->id . $i . time()), 0, 8)),
                    'manufactured_date' => $manufacturedDate,
                    'expiry_date' => $expiryDate,
                    'purchase_date' => $manufacturedDate->copy()->addDays(rand(1, 7)),
                    'purchase_price' => $purchasePrice,
                    'quantity_purchased' => $quantity,
                    'quantity_remaining' => $quantity - rand(0, min($quantity, 50)), // Some sold
                    'supplier_id' => null, // Optional
                    'notes' => null,
                ]);

                $batchesCreated++;
            }
        }

        $this->command->info("✅ Created {$batchesCreated} product batches");

        // Update stock levels to match batch quantities
        $this->command->info('📊 Updating stock levels...');

        foreach ($variants as $variant) {
            $totalQuantity = ProductBatch::where('product_variant_id', $variant->id)
                ->where('store_id', $store->id)
                ->sum('quantity_remaining');

            StockLevel::updateOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'store_id' => $store->id,
                ],
                [
                    'quantity' => $totalQuantity,
                    'reorder_level' => max(10, $totalQuantity * 0.2), // 20% of current stock
                    'max_stock_level' => $totalQuantity * 2,
                ]
            );
        }

        $this->command->info('✅ Stock levels updated');

        // Show statistics
        $totalValue = ProductBatch::join('product_variants', 'product_batches.product_variant_id', '=', 'product_variants.id')
            ->selectRaw('SUM(product_batches.quantity_remaining * product_batches.purchase_price) as total')
            ->value('total');

        $expiredCount = ProductBatch::where('expiry_date', '<', now())
            ->where('quantity_remaining', '>', 0)
            ->count();

        $expiringSoonCount = ProductBatch::whereBetween('expiry_date', [now(), now()->addDays(30)])
            ->where('quantity_remaining', '>', 0)
            ->count();

        $lowStockCount = StockLevel::whereColumn('quantity', '<=', 'reorder_level')->count();

        $this->command->info('');
        $this->command->info('📈 Dashboard Metrics Preview:');
        $this->command->info("💰 Inventory Value: ₹" . number_format($totalValue, 2));
        $this->command->info("🔴 Expired Batches: {$expiredCount}");
        $this->command->info("⚠️  Expiring Soon (30 days): {$expiringSoonCount}");
        $this->command->info("📉 Low Stock Items: {$lowStockCount}");
        $this->command->info('');
        $this->command->info('✅ Sample data ready! Refresh your dashboard to see metrics.');
    }
}

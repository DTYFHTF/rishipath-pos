<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class DemoInventorySeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::first();
        $store = Store::first();
        $supplier = Supplier::first();

        if (!$org || !$store || !$supplier) {
            $this->command->info('⚠️  Skipping demo inventory - run InitialSetupSeeder first');
            return;
        }

        $this->command->info('📦 Creating demo purchases...');

        // Create 5 sample purchases
        for ($i = 1; $i <= 5; $i++) {
                $purchase = Purchase::create([
                'organization_id' => $org->id,
                'store_id' => $store->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->subDays(rand(5, 30)),
                'supplier_invoice_number' => 'DEMO-INV-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'status' => $i <= 3 ? 'received' : 'ordered',
                    'total' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'shipping_cost' => rand(0, 500),
                'notes' => 'Demo purchase order #' . $i,
            ]);

            // Add 3-7 items per purchase
            $numItems = rand(3, 7);
            $variants = ProductVariant::with('product')->inRandomOrder()->limit($numItems)->get();

            foreach ($variants as $variant) {
                $qtyOrdered = rand(20, 200);
                $unitCost = $variant->cost_price ?? rand(50, 500);
                $taxRate = [0, 5, 12, 18][array_rand([0, 5, 12, 18])];

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_variant_id' => $variant->id,
                    'quantity_ordered' => $qtyOrdered,
                    'quantity_received' => $i <= 3 ? $qtyOrdered : 0,
                    'unit_cost' => $unitCost,
                    'tax_rate' => $taxRate,
                    'discount_amount' => 0,
                    'unit' => $variant->unit,
                'expiry_date' => now()->addYears(rand(1, 3)),
                'batch_id' => null,
                ]);
            }

            // Receive first 3 purchases to populate stock
            if ($i <= 3) {
                $purchase->receive($qtyOrdered ?? 100);
            }

            $this->command->info("  ✓ Created purchase #{$purchase->id} with {$numItems} items");
        }

        $this->command->info('✅ Demo inventory seeded successfully');
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Organization;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\ProductVariant;
use App\Models\Purchase;
use Carbon\Carbon;

class CreateBulkPurchaseOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:bulk-purchase-order {--supplier-id=} {--store-id=} {--organization-id=} {--qty=1000 : Quantity per product variant}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a purchase order containing all product variants with a fixed quantity each (default 1000)';

    public function handle(): int
    {
        $qty = (int) $this->option('qty');

        DB::beginTransaction();
        try {
            $org = null;
            if ($this->option('organization-id')) {
                $org = Organization::find($this->option('organization-id'));
            }
            $org = $org ?: Organization::first();
            if (! $org) {
                $this->error('No Organization found. Please create one or pass --organization-id.');
                return 1;
            }

            $store = null;
            if ($this->option('store-id')) {
                $store = Store::find($this->option('store-id'));
            }
            $store = $store ?: Store::where('organization_id', $org->id)->first();
            if (! $store) {
                $store = Store::create([
                    'organization_id' => $org->id,
                    'code' => 'AUTO',
                    'name' => 'Auto Store',
                    'active' => true,
                ]);
            }

            $supplier = null;
            if ($this->option('supplier-id')) {
                $supplier = Supplier::find($this->option('supplier-id'));
            }
            $supplier = $supplier ?: Supplier::where('organization_id', $org->id)->first();
            if (! $supplier) {
                $supplier = Supplier::create([
                    'organization_id' => $org->id,
                    'supplier_code' => 'AUTO-SUP-1',
                    'name' => 'Auto Supplier',
                    'active' => true,
                ]);
            }

            $this->info('Gathering product variants...');
            $variants = ProductVariant::with('product')->get();
            if ($variants->isEmpty()) {
                $this->error('No product variants found. Nothing to add.');
                DB::rollBack();
                return 1;
            }

            $purchase = Purchase::create([
                'organization_id' => $org->id,
                'store_id' => $store->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => Carbon::now()->toDateString(),
                'expected_delivery_date' => Carbon::now()->addDays(7)->toDateString(),
                'status' => 'ordered',
                'shipping_cost' => 0,
                'notes' => 'Auto-generated bulk purchase order: 1,000 units per variant',
            ]);

            $this->info('Creating purchase items...');
            $itemCount = 0;
            foreach ($variants as $variant) {
                $itemCount++;
                // 30% of items expire within 30 days, rest in 6-24 months
                $expiryDate = null;
                if ($itemCount % 10 < 3) {
                    // Near expiry: 1-30 days from now
                    $expiryDate = Carbon::now()->addDays(rand(1, 30))->toDateString();
                } else {
                    // Normal expiry: 6-24 months from now
                    $expiryDate = Carbon::now()->addMonths(rand(6, 24))->toDateString();
                }

                $purchase->items()->create([
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name ?? 'Unknown',
                    'product_sku' => $variant->sku,
                    'quantity_ordered' => $qty,
                    'quantity_received' => 0,
                    'unit' => $variant->unit ?? 'pcs',
                    'unit_cost' => $variant->cost_price ?? 0,
                    'tax_rate' => 0,
                    'discount_amount' => 0,
                    'expiry_date' => $expiryDate,
                ]);
            }

            // Recalculate totals
            $purchase->refresh();
            $purchase->recalculateTotals();

            DB::commit();

            $this->info("Created purchase {$purchase->purchase_number} (ID: {$purchase->id}) with {$variants->count()} items.");
            $this->info('Subtotal: ₹' . number_format($purchase->subtotal, 2));
            $this->info('Total: ₹' . number_format($purchase->total, 2));

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error creating purchase: ' . $e->getMessage());
            return 1;
        }
    }
}

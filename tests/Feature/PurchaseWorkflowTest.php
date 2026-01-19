<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $org;
    protected Store $store;
    protected Supplier $supplier;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
        $this->store = Store::factory()->create(['organization_id' => $this->org->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->org->id]);

        $product = Product::factory()->create(['organization_id' => $this->org->id]);
        $this->variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->actingAs($this->user);
    }

    public function test_purchase_can_be_created_with_items()
    {
        $purchase = Purchase::factory()->create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'draft',
        ]);

        PurchaseItem::factory()->create([
            'purchase_id' => $purchase->id,
            'product_variant_id' => $this->variant->id,
            'quantity_ordered' => 50,
            'unit_cost' => 100,
        ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
        ]);

        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchase->id,
            'product_variant_id' => $this->variant->id,
            'quantity_ordered' => 50,
        ]);
    }

    public function test_receiving_purchase_updates_stock()
    {
        $initialStock = InventoryService::getStock($this->variant->id, $this->store->id);

        $purchase = Purchase::factory()->create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'ordered',
        ]);

        PurchaseItem::factory()->create([
            'purchase_id' => $purchase->id,
            'product_variant_id' => $this->variant->id,
            'quantity_ordered' => 75,
            'quantity_received' => 0,
            'unit_cost' => 150,
        ]);

        $purchase->receive();

        $newStock = InventoryService::getStock($this->variant->id, $this->store->id);
        $this->assertEquals($initialStock + 75, $newStock);

        $purchase->refresh();
        $this->assertEquals('received', $purchase->status);
        $this->assertNotNull($purchase->received_date);
    }

    public function test_purchase_totals_calculated_correctly()
    {
        $purchase = Purchase::create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now(),
            'status' => 'draft',
            'shipping_cost' => 500,
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 0,
        ]);

        // Item 1: 10 units @ 100 each, 5% tax = 1000 + 50 tax = 1050
        $purchase->items()->create([
            'product_variant_id' => $this->variant->id,
            'product_name' => $this->variant->product->name,
            'product_sku' => $this->variant->sku,
            'quantity_ordered' => 10,
            'unit_cost' => 100,
            'tax_rate' => 5,
            'tax_amount' => 50,
            'discount_amount' => 0,
            'line_total' => 1050,
            'unit' => 'pcs',
        ]);

        $purchase->recalculateTotals();
        $purchase->refresh();

        $this->assertEquals(1050, $purchase->subtotal);
        $this->assertEquals(50, $purchase->tax_amount);
        $this->assertEquals(1550, $purchase->total); // subtotal + tax + shipping
    }

    public function test_partial_receive_updates_status()
    {
        $purchase = Purchase::factory()->create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'ordered',
        ]);

        $item = PurchaseItem::factory()->create([
            'purchase_id' => $purchase->id,
            'product_variant_id' => $this->variant->id,
            'quantity_ordered' => 100,
            'quantity_received' => 0,
        ]);

        // Receive partial quantity
        $purchase->receive(50);

        $item->refresh();
        $this->assertEquals(50, $item->quantity_received);

        $stock = InventoryService::getStock($this->variant->id, $this->store->id);
        $this->assertEquals(50, $stock);
    }
}

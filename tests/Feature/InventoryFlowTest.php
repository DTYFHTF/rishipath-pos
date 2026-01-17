<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $org;
    protected Store $store;
    protected ProductVariant $variant;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
        $this->store = Store::factory()->create(['organization_id' => $this->org->id]);
        
        $product = Product::factory()->create(['organization_id' => $this->org->id]);
        $this->variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($this->user);
    }

    /** @test */
    public function purchase_receive_increases_stock()
    {
        $inventoryService = app(InventoryService::class);
        
        // Initial stock should be 0
        $initialStock = $inventoryService->getStock($this->variant->id, $this->store->id);
        $this->assertEquals(0, $initialStock);

        // Create and receive purchase
        $purchase = Purchase::factory()->create([
            'organization_id' => $this->org->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'ordered',
        ]);

        PurchaseItem::factory()->create([
            'purchase_id' => $purchase->id,
            'product_variant_id' => $this->variant->id,
            'quantity_ordered' => 100,
            'quantity_received' => 0,
        ]);

        // Receive the purchase
        $purchase->receive(100);

        // Verify stock increased
        $newStock = $inventoryService->getStock($this->variant->id, $this->store->id);
        $this->assertEquals(100, $newStock);
    }

    /** @test */
    public function sale_decreases_stock()
    {
        $inventoryService = app(InventoryService::class);

        // Add initial stock
        $inventoryService->increaseStock(
            productVariantId: $this->variant->id,
            storeId: $this->store->id,
            quantity: 50,
            type: 'adjustment',
            referenceType: 'test',
            referenceId: null,
            notes: 'Test setup',
            userId: $this->user->id
        );

        // Make a sale
        $inventoryService->decreaseStock(
            productVariantId: $this->variant->id,
            storeId: $this->store->id,
            quantity: 10,
            type: 'sale',
            referenceType: 'Sale',
            referenceId: null,
            notes: 'Test sale',
            userId: $this->user->id
        );

        // Verify stock decreased
        $finalStock = $inventoryService->getStock($this->variant->id, $this->store->id);
        $this->assertEquals(40, $finalStock);
    }

    /** @test */
    public function transfer_moves_stock_between_stores()
    {
        $inventoryService = app(InventoryService::class);
        $store2 = Store::factory()->create(['organization_id' => $this->org->id]);

        // Add stock to first store
        $inventoryService->increaseStock(
            productVariantId: $this->variant->id,
            storeId: $this->store->id,
            quantity: 30,
            type: 'adjustment',
            referenceType: 'test',
            referenceId: null,
            notes: 'Setup',
            userId: $this->user->id
        );

        // Transfer to second store
        $inventoryService->transferStock(
            productVariantId: $this->variant->id,
            fromStoreId: $this->store->id,
            toStoreId: $store2->id,
            quantity: 15,
            notes: 'Test transfer',
            userId: $this->user->id
        );

        // Verify stocks
        $store1Stock = $inventoryService->getStock($this->variant->id, $this->store->id);
        $store2Stock = $inventoryService->getStock($this->variant->id, $store2->id);

        $this->assertEquals(15, $store1Stock);
        $this->assertEquals(15, $store2Stock);
    }

    /** @test */
    public function cannot_decrease_below_zero()
    {
        $inventoryService = app(InventoryService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock');

        // Try to decrease with no stock
        $inventoryService->decreaseStock(
            productVariantId: $this->variant->id,
            storeId: $this->store->id,
            quantity: 10,
            type: 'sale',
            referenceType: 'Sale',
            referenceId: null,
            notes: 'Test',
            userId: $this->user->id
        );
    }

    /** @test */
    public function audit_trail_is_created()
    {
        $inventoryService = app(InventoryService::class);

        $inventoryService->increaseStock(
            productVariantId: $this->variant->id,
            storeId: $this->store->id,
            quantity: 20,
            type: 'adjustment',
            referenceType: 'Test',
            referenceId: null,
            notes: 'Audit test',
            userId: $this->user->id
        );

        // Verify inventory movement was recorded
        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store->id,
            'quantity' => 20,
            'type' => 'adjustment',
            'user_id' => $this->user->id,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $org;
    protected Store $store1;
    protected Store $store2;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
        $this->store1 = Store::factory()->create(['organization_id' => $this->org->id]);
        $this->store2 = Store::factory()->create(['organization_id' => $this->org->id]);

        $product = Product::factory()->create(['organization_id' => $this->org->id]);
        $this->variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->actingAs($this->user);
    }

    public function test_stock_can_be_transferred_between_stores()
    {
        // Add initial stock to store1
        InventoryService::increaseStock(
            productVariantId: $this->variant->id,
            storeId: $this->store1->id,
            quantity: 100,
            type: 'adjustment',
            notes: 'Initial stock'
        );

        $initialStock1 = InventoryService::getStock($this->variant->id, $this->store1->id);
        $initialStock2 = InventoryService::getStock($this->variant->id, $this->store2->id);

        $this->assertEquals(100, $initialStock1);
        $this->assertEquals(0, $initialStock2);

        // Transfer 30 units
        InventoryService::transferStock(
            productVariantId: $this->variant->id,
            fromStoreId: $this->store1->id,
            toStoreId: $this->store2->id,
            quantity: 30,
            notes: 'Transfer test',
            userId: $this->user->id
        );

        $finalStock1 = InventoryService::getStock($this->variant->id, $this->store1->id);
        $finalStock2 = InventoryService::getStock($this->variant->id, $this->store2->id);

        $this->assertEquals(70, $finalStock1);
        $this->assertEquals(30, $finalStock2);
    }

    public function test_transfer_creates_two_inventory_movements()
    {
        InventoryService::increaseStock(
            productVariantId: $this->variant->id,
            storeId: $this->store1->id,
            quantity: 50,
            type: 'adjustment'
        );

        $initialMovementCount = InventoryMovement::where('product_variant_id', $this->variant->id)->count();

        InventoryService::transferStock(
            productVariantId: $this->variant->id,
            fromStoreId: $this->store1->id,
            toStoreId: $this->store2->id,
            quantity: 20
        );

        $finalMovementCount = InventoryMovement::where('product_variant_id', $this->variant->id)->count();

        // Should create 2 new movements (out + in)
        $this->assertEquals($initialMovementCount + 2, $finalMovementCount);

        // Verify both movements exist
        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store1->id,
            'type' => 'transfer',
            'quantity' => 20,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $this->variant->id,
            'store_id' => $this->store2->id,
            'type' => 'transfer',
            'quantity' => 20,
        ]);
    }

    public function test_cannot_transfer_more_than_available_stock()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock');

        InventoryService::increaseStock(
            productVariantId: $this->variant->id,
            storeId: $this->store1->id,
            quantity: 10,
            type: 'adjustment'
        );

        InventoryService::transferStock(
            productVariantId: $this->variant->id,
            fromStoreId: $this->store1->id,
            toStoreId: $this->store2->id,
            quantity: 20 // More than available
        );
    }

    public function test_transfer_with_notes_is_recorded()
    {
        InventoryService::increaseStock(
            productVariantId: $this->variant->id,
            storeId: $this->store1->id,
            quantity: 50,
            type: 'adjustment'
        );

        InventoryService::transferStock(
            productVariantId: $this->variant->id,
            fromStoreId: $this->store1->id,
            toStoreId: $this->store2->id,
            quantity: 15,
            notes: 'Stock rebalancing',
            userId: $this->user->id
        );

        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $this->variant->id,
            'type' => 'transfer',
            'user_id' => $this->user->id,
        ]);

        $movement = InventoryMovement::where('product_variant_id', $this->variant->id)
            ->where('type', 'transfer')
            ->where('store_id', $this->store1->id)
            ->first();

        $this->assertStringContainsString('Stock rebalancing', $movement->notes);
    }
}

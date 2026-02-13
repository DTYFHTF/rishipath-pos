<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Models\StockLevel;
use App\Models\InventoryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReturnTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $user;
    private Store $store;
    private Supplier $supplier;
    private ProductVariant $variant1;
    private ProductVariant $variant2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->store = Store::factory()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        
        // Create product variants for testing (must create products with our organization first)
        $product1 = Product::factory()->create(['organization_id' => $this->organization->id]);
        $product2 = Product::factory()->create(['organization_id' => $this->organization->id]);
        
        $this->variant1 = ProductVariant::factory()->create(['product_id' => $product1->id]);
        $this->variant2 = ProductVariant::factory()->create(['product_id' => $product2->id]);

        $this->actingAs($this->user);
    }

    /** @test */
    public function it_can_process_a_valid_return()
    {
        // Create and receive a purchase
        $purchase = Purchase::create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now(),
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'purchase_number' => 'PUR-TEST-001',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_variant_id' => $this->variant1->id,
            'product_name' => 'Test Product 1',
            'product_sku' => 'TEST-001',
            'quantity_ordered' => 100,
            'quantity_received' => 100,
            'unit_cost' => 10.00,
            'line_total' => 1000.00,
            'unit' => 'kg',
        ]);

        $purchase->receive();

        // Verify initial state
        $this->assertEquals('received', $purchase->fresh()->status);
        $batch = ProductBatch::where('purchase_id', $purchase->id)->first();
        $this->assertEquals(100, $batch->quantity_remaining);
        $this->assertEquals(0, $batch->quantity_returned);

        $stockLevel = StockLevel::where('product_variant_id', $this->variant1->id)->first();
        $initialStock = $stockLevel->quantity;

        // Process return
        $returns = $purchase->processReturn([
            $purchase->items->first()->id => 20
        ], 'Defective', 'Test return notes');

        // Verify return records created
        $this->assertCount(1, $returns);
        $returnRecord = $returns[0];
        $this->assertEquals(20, $returnRecord->quantity_returned);
        $this->assertEquals(200.00, $returnRecord->return_amount); // 20 * 10
        $this->assertEquals('Defective', $returnRecord->reason);
        $this->assertEquals('Test return notes', $returnRecord->notes);
        $this->assertEquals('approved', $returnRecord->status);

        // Verify batch updated
        $batch->refresh();
        $this->assertEquals(80, $batch->quantity_remaining);
        $this->assertEquals(20, $batch->quantity_returned);

        // Verify stock level updated
        $stockLevel->refresh();
        $this->assertEquals($initialStock - 20, $stockLevel->quantity);

        // Verify inventory movement created
        $movement = InventoryMovement::where('batch_id', $batch->id)
            ->where('type', 'return')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-20, $movement->quantity);

        // Verify supplier ledger entry created
        $ledgerEntry = SupplierLedgerEntry::where('purchase_id', $purchase->id)
            ->where('type', 'return')
            ->first();
        $this->assertNotNull($ledgerEntry);
        $this->assertEquals(-200.00, $ledgerEntry->amount);

        // Verify supplier balance reduced
        $this->supplier->refresh();
        $this->assertEquals(-200.00, $this->supplier->current_balance);
    }

    /** @test */
    public function it_prevents_returning_more_than_received()
    {
        $purchase = Purchase::create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now(),
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'purchase_number' => 'PUR-TEST-002',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_variant_id' => $this->variant1->id,
            'product_name' => 'Test Product 1',
            'product_sku' => 'TEST-001',
            'quantity_ordered' => 50,
            'quantity_received' => 50,
            'unit_cost' => 10.00,
            'line_total' => 500.00,
            'unit' => 'kg',
        ]);

        $purchase->receive();

        // Try to return more than received
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot return 60 units');

        $purchase->processReturn([
            $purchase->items->first()->id => 60
        ], 'Defective');
    }

    /** @test */
    public function it_prevents_duplicate_returns_exceeding_received_quantity()
    {
        $purchase = Purchase::create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now(),
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'purchase_number' => 'PUR-TEST-003',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_variant_id' => $this->variant1->id,
            'product_name' => 'Test Product 1',
            'product_sku' => 'TEST-001',
            'quantity_ordered' => 100,
            'quantity_received' => 100,
            'unit_cost' => 10.00,
            'line_total' => 1000.00,
            'unit' => 'kg',
        ]);

        $purchase->receive();

        // First return: 70 units
        $purchase->processReturn([
            $purchase->items->first()->id => 70
        ], 'Defective');

        // Second return: Try to return 40 more (total would be 110, exceeds 100)
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only 30 units available for return');

        $purchase->processReturn([
            $purchase->items->first()->id => 40
        ], 'Damaged');
    }

    /** @test */
    public function it_allocates_returns_across_multiple_batches_using_fifo()
    {
        $purchase = Purchase::create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now(),
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'purchase_number' => 'PUR-TEST-004',
        ]);

        // Create two items that will create two batches
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_variant_id' => $this->variant1->id,
            'product_name' => 'Test Product 1',
            'product_sku' => 'TEST-001',
            'quantity_ordered' => 50,
            'quantity_received' => 50,
            'unit_cost' => 10.00,
            'line_total' => 500.00,
            'unit' => 'kg',
        ]);

        $purchase->receive();

        // Manually create a second batch for the same variant (simulating multiple receipts)
        $batch2 = ProductBatch::create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'purchase_id' => $purchase->id,
            'product_variant_id' => $this->variant1->id,
            'batch_number' => 'BATCH-002',
            'quantity_received' => 30,
            'quantity_remaining' => 30,
            'quantity_sold' => 0,
            'quantity_damaged' => 0,
            'quantity_returned' => 0,
            'cost_price' => 10.00,
        ]);

        // Now we have 80 total units (50 + 30)
        // Return 60 units - should take 50 from first batch, 10 from second
        $returns = $purchase->processReturn([
            $purchase->items->first()->id => 60
        ], 'Overstocked');

        // Should create 2 return records (one per batch)
        $this->assertCount(2, $returns);

        // Verify first batch (FIFO - oldest first)
        $batch1 = ProductBatch::where('purchase_id', $purchase->id)
            ->orderBy('created_at', 'asc')
            ->first();
        $this->assertEquals(0, $batch1->quantity_remaining);
        $this->assertEquals(50, $batch1->quantity_returned);

        // Verify second batch
        $batch2->refresh();
        $this->assertEquals(20, $batch2->quantity_remaining);
        $this->assertEquals(10, $batch2->quantity_returned);
    }

    /** @test */
    public function it_handles_multiple_items_in_single_return()
    {
        $purchase = Purchase::create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now(),
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'purchase_number' => 'PUR-TEST-005',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_variant_id' => $this->variant1->id,
            'product_name' => 'Test Product 1',
            'product_sku' => 'TEST-001',
            'quantity_ordered' => 100,
            'quantity_received' => 100,
            'unit_cost' => 10.00,
            'line_total' => 1000.00,
            'unit' => 'kg',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_variant_id' => $this->variant2->id,
            'product_name' => 'Test Product 2',
            'product_sku' => 'TEST-002',
            'quantity_ordered' => 50,
            'quantity_received' => 50,
            'unit_cost' => 20.00,
            'line_total' => 1000.00,
            'unit' => 'kg',
        ]);

        $purchase->receive();

        // Return items from both products
        $items = $purchase->items;
        $returns = $purchase->processReturn([
            $items[0]->id => 20,  // Product 1: 20 units @ 10 = 200
            $items[1]->id => 10,  // Product 2: 10 units @ 20 = 200
        ], 'Mixed return', 'Partial return of multiple items');

        // Should create 2 return records
        $this->assertCount(2, $returns);

        // Verify total return amount in ledger
        $ledgerEntry = SupplierLedgerEntry::where('purchase_id', $purchase->id)
            ->where('type', 'return')
            ->first();
        $this->assertEquals(-400.00, $ledgerEntry->amount); // 200 + 200

        // Verify batches updated correctly
        $batch1 = ProductBatch::where('product_variant_id', $this->variant1->id)->first();
        $this->assertEquals(80, $batch1->quantity_remaining);
        $this->assertEquals(20, $batch1->quantity_returned);

        $batch2 = ProductBatch::where('product_variant_id', $this->variant2->id)->first();
        $this->assertEquals(40, $batch2->quantity_remaining);
        $this->assertEquals(10, $batch2->quantity_returned);
    }

    /** @test */
    public function it_creates_return_numbers_automatically()
    {
        $purchase = Purchase::create([
            'organization_id' => $this->organization->id,
            'store_id' => $this->store->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now(),
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'purchase_number' => 'PUR-TEST-006',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_variant_id' => $this->variant1->id,
            'product_name' => 'Test Product 1',
            'product_sku' => 'TEST-001',
            'quantity_ordered' => 100,
            'quantity_received' => 100,
            'unit_cost' => 10.00,
            'line_total' => 1000.00,
            'unit' => 'kg',
        ]);

        $purchase->receive();

        $returns = $purchase->processReturn([
            $purchase->items->first()->id => 10
        ], 'Test');

        $returnRecord = $returns[0];
        $this->assertNotNull($returnRecord->return_number);
        $this->assertStringStartsWith('MAIN-RET-', $returnRecord->return_number);
    }
}

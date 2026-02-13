<?php

// Manual test script for purchase returns
// Run with: php test_purchase_return_manual.php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

echo "Starting manual purchase return test...\n";

// Clean slate
DB::statement('DELETE FROM purchase_returns');
DB::statement('DELETE FROM product_batches');
DB::statement('DELETE FROM purchase_items');
DB::statement('DELETE FROM purchases');
DB::statement('DELETE FROM stock_levels');
DB::statement('DELETE FROM inventory_movements');
DB::statement('DELETE FROM supplier_ledger_entries');

// Get existing test organization and store (assuming they exist from seeding)
$org = Organization::first() ?? Organization::factory()->create();
$store = Store::where('organization_id', $org->id)->first() ?? Store::factory()->create([
    'organization_id' => $org->id,
]);
$user = User::where('organization_id', $org->id)->first() ?? User::factory()->create([
    'organization_id' => $org->id,
]);
$supplier = Supplier::where('organization_id', $org->id)->first() ?? Supplier::factory()->create([
    'organization_id' => $org->id,
]);
$product = Product::where('organization_id', $org->id)->first() ?? Product::factory()->create([
    'organization_id' => $org->id,
]);
$variant = ProductVariant::where('product_id', $product->id)->first() ?? ProductVariant::factory()->create([
    'product_id' => $product->id,
]);

Auth::login($user);

echo "Test data created:\n";
echo "- Organization: {$org->id}\n";
echo "- Store: {$store->name} ({$store->code})\n";
echo "- Supplier: {$supplier->name}\n";
echo "- Product: {$product->name}\n";
echo "- Variant: {$variant->sku}\n\n";

// Create a purchase
echo "Creating purchase...\n";
$purchase = Purchase::create([
    'organization_id' => $org->id,
    'store_id' => $store->id,
    'supplier_id' => $supplier->id,
    'purchase_date' => now(),
    'status' => 'draft',
    'payment_status' => 'unpaid',
    'purchase_number' => 'PUR-TEST-' . time(),
]);

PurchaseItem::create([
    'purchase_id' => $purchase->id,
    'product_variant_id' => $variant->id,
    'product_name' => $product->name,
    'product_sku' => $variant->sku,
    'quantity_ordered' => 100,
    'quantity_received' => 100,
    'unit_cost' => 10.00,
    'line_total' => 1000.00,
    'unit' => 'kg',
]);

echo "Purchase created: {$purchase->purchase_number}\n\n";

// Receive the purchase
echo "Receiving purchase...\n";
$purchase->receive();
echo "Purchase received successfully\n";
echo "Status: {$purchase->fresh()->status}\n\n";

// Check batch created
$batch = \App\Models\ProductBatch::where('purchase_id', $purchase->id)->first();
echo "Batch created: {$batch->batch_number}\n";
echo "- Quantity remaining: {$batch->quantity_remaining}\n";
echo "- Quantity returned: {$batch->quantity_returned}\n\n";

// Check stock level
$stockLevel = \App\Models\StockLevel::where('product_variant_id', $variant->id)
    ->where('store_id', $store->id)
    ->first();
echo "Stock level: {$stockLevel->quantity}\n\n";

// Process return
echo "Processing return of 20 units...\n";
try {
    $returns = $purchase->processReturn([
        $purchase->items->first()->id => 20
    ], 'Defective', 'Test return');
    
    echo "Return processed successfully!\n";
    echo "- Returns created: " . count($returns) . "\n";
    $returnRecord = $returns[0];
    echo "- Return number: {$returnRecord->return_number}\n";
    echo "- Quantity returned: {$returnRecord->quantity_returned}\n";
    echo "- Return amount: ₹{$returnRecord->return_amount}\n\n";
    
    // Check batch updated
    $batch->refresh();
    echo "Batch updated:\n";
    echo "- Quantity remaining: {$batch->quantity_remaining}\n";
    echo "- Quantity returned: {$batch->quantity_returned}\n\n";
    
    // Check stock level updated
    $stockLevel->refresh();
    echo "Stock level updated: {$stockLevel->quantity}\n\n";
    
    // Check ledger entry created
    $ledger = \App\Models\SupplierLedgerEntry::where('purchase_id', $purchase->id)
        ->where('type', 'return')
        ->first();
    echo "Ledger entry created:\n";
    echo "- Type: {$ledger->type}\n";
    echo "- Amount: ₹{$ledger->amount}\n\n";
    
    // Check supplier balance
    $supplier->refresh();
    echo "Supplier balance: ₹{$supplier->current_balance}\n\n";
    
    echo "✅ All checks passed!\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\nTest complete.\n";

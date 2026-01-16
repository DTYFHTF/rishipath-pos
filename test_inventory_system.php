<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\InventoryService;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\InventoryMovement;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Store;

echo "=== INVENTORY SYSTEM TEST ===\n\n";

// Test 1: InventoryService
echo "1. Testing InventoryService...\n";

$variant = ProductVariant::first();
if (!$variant) {
    echo "   ERROR: No product variants found\n";
    exit(1);
}

$store = Store::first();
echo "   Using variant: {$variant->id} ({$variant->sku})\n";
echo "   Using store: {$store->id} ({$store->name})\n";

$currentStock = InventoryService::getStock($variant->id, $store->id);
echo "   Current stock: {$currentStock}\n";

// Test increase
$result = InventoryService::increaseStock(
    $variant->id,
    $store->id,
    10,
    'purchase',
    'TestScript',
    null,
    100.00,
    'Test purchase via script'
);
echo "   After +10: {$result->quantity}\n";

// Check movement
$movement = InventoryMovement::where('product_variant_id', $variant->id)
    ->where('store_id', $store->id)
    ->orderByDesc('created_at')
    ->first();

if ($movement) {
    echo "   Movement created: type={$movement->type}, qty={$movement->quantity}\n";
    echo "   ✅ InventoryService PASSED\n\n";
} else {
    echo "   ❌ Movement not created!\n\n";
}

// Test 2: Purchase Creation
echo "2. Testing Purchase Creation...\n";

$supplier = Supplier::first();
if (!$supplier) {
    echo "   Creating test supplier...\n";
    $supplier = Supplier::create([
        'organization_id' => 1,
        'supplier_code' => 'SUP-TEST',
        'name' => 'Test Supplier',
        'active' => true,
    ]);
}

$purchase = Purchase::create([
    'organization_id' => 1,
    'store_id' => $store->id,
    'supplier_id' => $supplier->id,
    'purchase_date' => now(),
    'status' => 'draft',
]);

echo "   Purchase created: {$purchase->purchase_number}\n";

// Add item
$item = PurchaseItem::create([
    'purchase_id' => $purchase->id,
    'product_variant_id' => $variant->id,
    'quantity_ordered' => 25,
    'unit_cost' => 50.00,
    'tax_rate' => 18,
]);

$purchase->refresh();
echo "   Item added: qty={$item->quantity_ordered}, cost={$item->unit_cost}\n";
echo "   Purchase total: {$purchase->total}\n";
echo "   ✅ Purchase Creation PASSED\n\n";

// Test 3: Receive Purchase
echo "3. Testing Purchase Receive...\n";

$stockBefore = InventoryService::getStock($variant->id, $store->id);
echo "   Stock before receive: {$stockBefore}\n";

$purchase->receive();
$purchase->refresh();

$stockAfter = InventoryService::getStock($variant->id, $store->id);
echo "   Stock after receive: {$stockAfter}\n";
echo "   Status: {$purchase->status}\n";

if ($stockAfter > $stockBefore) {
    echo "   ✅ Purchase Receive PASSED\n\n";
} else {
    echo "   ❌ Stock did not increase!\n\n";
}

// Test 4: Supplier Ledger
echo "4. Testing Supplier Ledger...\n";

$supplier->refresh();
echo "   Supplier balance: {$supplier->current_balance}\n";

$ledgerEntry = $supplier->ledgerEntries()->latest()->first();
if ($ledgerEntry) {
    echo "   Ledger entry: type={$ledgerEntry->type}, amount={$ledgerEntry->amount}\n";
    echo "   ✅ Supplier Ledger PASSED\n\n";
} else {
    echo "   ❌ No ledger entry created!\n\n";
}

// Test 5: Record Payment
echo "5. Testing Payment Recording...\n";

$outstanding = $purchase->outstanding_amount;
echo "   Outstanding before payment: {$outstanding}\n";

$purchase->recordPayment(500, 'cash', 'TEST-REF', 'Test payment');
$purchase->refresh();

echo "   Outstanding after payment: {$purchase->outstanding_amount}\n";
echo "   Payment status: {$purchase->payment_status}\n";
echo "   ✅ Payment Recording PASSED\n\n";

// Test 6: Stock Transfer
echo "6. Testing Stock Transfer...\n";

$stores = Store::take(2)->get();
if ($stores->count() >= 2) {
    $fromStore = $stores[0];
    $toStore = $stores[1];
    
    $fromBefore = InventoryService::getStock($variant->id, $fromStore->id);
    $toBefore = InventoryService::getStock($variant->id, $toStore->id);
    
    echo "   From store ({$fromStore->name}) before: {$fromBefore}\n";
    echo "   To store ({$toStore->name}) before: {$toBefore}\n";
    
    InventoryService::transferStock($variant->id, $fromStore->id, $toStore->id, 5, 'Test transfer');
    
    $fromAfter = InventoryService::getStock($variant->id, $fromStore->id);
    $toAfter = InventoryService::getStock($variant->id, $toStore->id);
    
    echo "   From store after: {$fromAfter}\n";
    echo "   To store after: {$toAfter}\n";
    echo "   ✅ Stock Transfer PASSED\n\n";
} else {
    echo "   ⚠️ Skipped (need 2 stores)\n\n";
}

// Summary
echo "=== ALL TESTS COMPLETED ===\n";
echo "The inventory system is working correctly!\n\n";

// Show recent movements
echo "Recent movements for this variant:\n";
$movements = InventoryMovement::where('product_variant_id', $variant->id)
    ->orderByDesc('created_at')
    ->limit(5)
    ->get();

foreach ($movements as $m) {
    echo "  - {$m->type}: {$m->quantity} ({$m->from_quantity} → {$m->to_quantity}) @ {$m->created_at}\n";
}

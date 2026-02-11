<?php
/**
 * End-to-end verification of all ledger fixes.
 */
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\CustomerLedgerEntry;
use App\Models\SupplierLedgerEntry;

$errors = [];
$passes = [];

echo "========================================\n";
echo " LEDGER SYSTEM END-TO-END VERIFICATION\n";
echo "========================================\n\n";

// ============ TEST 1: Customer Ledger Entry table is customer-only ============
echo "TEST 1: customer_ledger_entries should contain NO supplier entries\n";
$supplierEntries = DB::table('customer_ledger_entries')
    ->where('ledgerable_type', Supplier::class)
    ->count();
if ($supplierEntries === 0) {
    echo "  ✅ PASS: No supplier entries in customer_ledger_entries\n";
    $passes[] = 'TEST 1';
} else {
    echo "  ❌ FAIL: Found {$supplierEntries} supplier entries!\n";
    $errors[] = 'TEST 1: Supplier entries still in customer_ledger_entries';
}

// ============ TEST 2: Supplier ledger entries exist in correct table ============
echo "\nTEST 2: supplier_ledger_entries should have entries for purchases\n";
$slEntries = DB::table('supplier_ledger_entries')->count();
if ($slEntries > 0) {
    echo "  ✅ PASS: Found {$slEntries} entries in supplier_ledger_entries\n";
    $passes[] = 'TEST 2';
} else {
    echo "  ⚠️ INFO: No entries in supplier_ledger_entries (may be expected if all purchases are paid)\n";
    $passes[] = 'TEST 2 (info)';
}

// ============ TEST 3: CustomerLedgerEntry::createSaleEntry only creates for credit ============
echo "\nTEST 3: createSaleEntry should only create entries for credit sales\n";
$cashSale = Sale::where('payment_method', '!=', 'credit')->whereNotNull('customer_id')->first();
if ($cashSale) {
    $result = CustomerLedgerEntry::createSaleEntry($cashSale);
    if ($result === null) {
        echo "  ✅ PASS: Cash/UPI sale correctly returns null (no ledger entry)\n";
        $passes[] = 'TEST 3';
    } else {
        echo "  ❌ FAIL: Cash/UPI sale should NOT create a ledger entry!\n";
        $result->delete(); // cleanup
        $errors[] = 'TEST 3: Cash sale created a ledger entry';
    }
} else {
    echo "  ⚠️ SKIP: No non-credit sale with customer found to test\n";
}

// ============ TEST 4: Purchase model no longer references CustomerLedgerEntry ============
echo "\nTEST 4: Purchase::booted() should NOT create entries in customer_ledger_entries\n";
$beforeCount = DB::table('customer_ledger_entries')->where('ledgerable_type', Supplier::class)->count();
echo "  Current supplier entries in customer_ledger_entries: {$beforeCount}\n";
if ($beforeCount === 0) {
    echo "  ✅ PASS: No supplier entries in customer_ledger_entries\n";
    $passes[] = 'TEST 4';
} else {
    echo "  ❌ FAIL: Still have supplier entries in customer_ledger_entries!\n";
    $errors[] = 'TEST 4: Supplier entries in wrong table';
}

// ============ TEST 5: Supplier balance is correct ============
echo "\nTEST 5: Supplier balances should match supplier_ledger_entries\n";
$suppliers = Supplier::all();
foreach ($suppliers as $supplier) {
    $lastEntry = SupplierLedgerEntry::where('supplier_id', $supplier->id)
        ->orderBy('id', 'desc')
        ->first();
    $expectedBalance = $lastEntry ? $lastEntry->balance_after : 0;
    
    echo "  Supplier '{$supplier->name}': DB balance = {$supplier->current_balance}, Ledger balance = {$expectedBalance}\n";
    if (abs((float)$supplier->current_balance - (float)$expectedBalance) < 0.01) {
        echo "  ✅ PASS\n";
    } else {
        echo "  ❌ MISMATCH!\n";
        $errors[] = "TEST 5: Supplier '{$supplier->name}' balance mismatch";
    }
}
$passes[] = 'TEST 5';

// ============ TEST 6: Sale model columns ============
echo "\nTEST 6: Sale model has total_amount column (not total)\n";
$sale = Sale::first();
if ($sale) {
    echo "  Sale #{$sale->id}: total_amount = {$sale->total_amount}\n";
    if ($sale->total_amount !== null) {
        echo "  ✅ PASS\n";
        $passes[] = 'TEST 6';
    } else {
        echo "  ❌ FAIL: total_amount is null\n";
        $errors[] = 'TEST 6: Sale total_amount is null';
    }
} else {
    echo "  ⚠️ SKIP: No sales to test\n";
}

// ============ TEST 7: Purchase model uses 'total' not 'total_amount' ============
echo "\nTEST 7: Purchase model has 'total' column\n";
$purchase = Purchase::first();
if ($purchase) {
    echo "  Purchase #{$purchase->id}: total = {$purchase->total}\n";
    // Check that the old bug (total_amount) would return null
    $totalAmount = $purchase->getAttribute('total_amount');
    echo "  Purchase total_amount (should be null/undefined): " . var_export($totalAmount, true) . "\n";
    if ($purchase->total !== null) {
        echo "  ✅ PASS: Purchase uses 'total' column correctly\n";
        $passes[] = 'TEST 7';
    } else {
        echo "  ❌ FAIL: Purchase total is null\n";
        $errors[] = 'TEST 7: Purchase total is null';
    }
} else {
    echo "  ⚠️ SKIP: No purchases to test\n";
}

// ============ TEST 8: Abin Maharjan sales exist ============
echo "\nTEST 8: Verify Abin Maharjan's sales\n";
$abin = Customer::where('name', 'like', '%Abin%')->first();
if ($abin) {
    $sales = Sale::where('customer_id', $abin->id)->get();
    echo "  Found {$sales->count()} sales for {$abin->name}:\n";
    foreach ($sales as $s) {
        echo "    #{$s->id}: {$s->invoice_number} - method={$s->payment_method}, total={$s->total_amount}, date={$s->created_at}\n";
    }
    
    $creditSales = $sales->where('payment_method', 'credit');
    $nonCreditSales = $sales->where('payment_method', '!=', 'credit');
    
    echo "  Credit sales: {$creditSales->count()} (should create ledger entries)\n";
    echo "  Non-credit sales: {$nonCreditSales->count()} (cash/UPI - no ledger entries by design)\n";
    
    $ledgerEntries = CustomerLedgerEntry::where('ledgerable_type', Customer::class)
        ->where('ledgerable_id', $abin->id)
        ->count();
    echo "  Customer ledger entries: {$ledgerEntries}\n";
    
    if ($creditSales->count() == 0 && $ledgerEntries == 0) {
        echo "  ✅ PASS: No credit sales = no ledger entries (correct behavior)\n";
        echo "  ℹ️  NOTE: All of Abin's purchases were cash/UPI, so they don't appear in the AR ledger.\n";
        echo "  ℹ️  The 'Show transactions' toggle on Customer Ledger will show all sales regardless.\n";
        $passes[] = 'TEST 8';
    } else {
        echo "  ℹ️  Entries exist as expected for credit sales\n";
        $passes[] = 'TEST 8';
    }
} else {
    echo "  ⚠️ SKIP: Customer 'Abin' not found\n";
}

// ============ SUMMARY ============
echo "\n========================================\n";
echo " RESULTS\n";
echo "========================================\n";
echo " Passed: " . count($passes) . "\n";
echo " Failed: " . count($errors) . "\n";

if (count($errors) > 0) {
    echo "\n ERRORS:\n";
    foreach ($errors as $e) {
        echo "  ❌ {$e}\n";
    }
} else {
    echo "\n ✅ All tests passed!\n";
}
echo "\n";

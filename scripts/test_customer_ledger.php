<?php
/**
 * Test Customer Ledger display with actual data.
 */
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Customer;
use App\Models\Sale;
use App\Models\CustomerLedgerEntry;

echo "=== CUSTOMER LEDGER DEBUGGING ===\n\n";

// Find Abin Maharjan
$customer = Customer::where('name', 'like', '%Abin%')->first();
if (!$customer) {
    echo "❌ Customer 'Abin' not found\n";
    exit(1);
}

echo "Customer: {$customer->name} (ID: {$customer->id})\n";
echo "Outstanding balance: {$customer->outstanding_balance}\n\n";

// Check all sales for this customer
$sales = Sale::where('customer_id', $customer->id)->get();
echo "Total sales for this customer: {$sales->count()}\n";
foreach ($sales as $sale) {
    echo "  Sale #{$sale->id}: {$sale->invoice_number} - {$sale->payment_method} - ₹{$sale->total_amount} - {$sale->created_at}\n";
}
echo "\n";

// Check ledger entries using the scope (like CustomerLedgerReport does)
echo "Querying CustomerLedgerEntry::forCustomer({$customer->id})...\n";
$entries = CustomerLedgerEntry::forCustomer($customer->id)->get();
echo "Found {$entries->count()} ledger entries\n";

if ($entries->count() === 0) {
    echo "  ℹ️  No ledger entries (this is correct if all sales were cash/UPI)\n\n";
    
    // Let's create a test CREDIT sale to verify the flow works
    echo "=== Creating a test CREDIT sale to verify ledger entry creation ===\n";
    
    DB::beginTransaction();
    try {
        $testSale = Sale::create([
            'organization_id' => $customer->organization_id,
            'store_id' => 1,
            'customer_id' => $customer->id,
            'invoice_number' => 'TEST-CREDIT-' . time(),
            'receipt_number' => 'TEST-RCP-' . time(),
            'date' => now(),
            'status' => 'completed',
            'payment_method' => 'credit',
            'payment_status' => 'unpaid',
            'subtotal' => 100.00,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 100.00,
            'amount_paid' => 0,
            'amount_change' => 0,
            'cashier_id' => 1,
        ]);
        
        echo "Created test sale: {$testSale->invoice_number}\n";
        
        // Now create the ledger entry manually (simulating what EnhancedPOS::completeSale does)
        $ledgerEntry = CustomerLedgerEntry::createSaleEntry($testSale);
        
        if ($ledgerEntry) {
            echo "✅ Ledger entry created successfully!\n";
            echo "  Entry ID: {$ledgerEntry->id}\n";
            echo "  Type: {$ledgerEntry->entry_type}\n";
            echo "  Debit: {$ledgerEntry->debit_amount}\n";
            echo "  Credit: {$ledgerEntry->credit_amount}\n";
            echo "  Balance: {$ledgerEntry->balance}\n";
            echo "  Description: {$ledgerEntry->description}\n\n";
            
            // Now test the query again
            echo "Re-querying with forCustomer scope...\n";
            $entriesAfter = CustomerLedgerEntry::forCustomer($customer->id)->get();
            echo "Found {$entriesAfter->count()} entries after creating test sale\n";
            
            if ($entriesAfter->count() > 0) {
                echo "✅ Query works! Entries are being returned.\n";
                foreach ($entriesAfter as $e) {
                    echo "  - Entry #{$e->id}: {$e->entry_type} - ₹{$e->debit_amount} - {$e->description}\n";
                }
            } else {
                echo "❌ BUG: Entry was created but query returns empty!\n";
            }
        } else {
            echo "⚠️  createSaleEntry returned null (this would happen for non-credit sales)\n";
        }
        
        DB::rollback();
        echo "\nTest transaction rolled back (no data saved)\n";
        
    } catch (Exception $e) {
        DB::rollback();
        echo "❌ Error creating test sale: {$e->getMessage()}\n";
        echo "Stack trace:\n{$e->getTraceAsString()}\n";
    }
} else {
    echo "Existing ledger entries:\n";
    foreach ($entries as $entry) {
        echo "  Entry #{$entry->id}:\n";
        echo "    Type: {$entry->entry_type}\n";
        echo "    Reference: {$entry->reference_type} #{$entry->reference_id} - {$entry->reference_number}\n";
        echo "    Debit: {$entry->debit_amount}\n";
        echo "    Credit: {$entry->credit_amount}\n";
        echo "    Balance: {$entry->balance}\n";
        echo "    Date: {$entry->transaction_date}\n";
        echo "    Status: {$entry->status}\n\n";
    }
}

echo "\n=== Checking CustomerLedgerReport generateReport logic ===\n";
// Simulate what the report page does
$startDate = now()->startOfMonth()->format('Y-m-d');
$endDate = now()->format('Y-m-d');

$query = CustomerLedgerEntry::forCustomer($customer->id)
    ->where('organization_id', 1)
    ->with(['store', 'createdBy'])
    ->where('transaction_date', '>=', $startDate)
    ->where('transaction_date', '<=', $endDate)
    ->orderBy('transaction_date', 'desc')
    ->orderBy('created_at', 'desc');

$ledgerEntries = $query->get();
echo "Query with date filters ({$startDate} to {$endDate}): {$ledgerEntries->count()} entries\n";

if ($ledgerEntries->count() === 0) {
    echo "  ℹ️  This is expected if:\n";
    echo "    1. All sales were cash/UPI (no credit)\n";
    echo "    2. No manual ledger entries exist\n";
    echo "    3. Date range doesn't match any entries\n";
}

echo "\nDone.\n";

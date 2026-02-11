<?php
/**
 * Migration script: Clean up stale supplier entries from customer_ledger_entries table.
 * 
 * After consolidating supplier ledger to use supplier_ledger_entries table only,
 * we need to remove the orphaned supplier entries from customer_ledger_entries.
 */
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Supplier;

echo "=== Cleaning up stale supplier entries from customer_ledger_entries ===\n\n";

// Find supplier entries in customer_ledger_entries
$supplierEntries = DB::table('customer_ledger_entries')
    ->where('ledgerable_type', Supplier::class)
    ->get();

echo "Found " . count($supplierEntries) . " supplier entries in customer_ledger_entries table:\n";
foreach ($supplierEntries as $entry) {
    echo "  ID {$entry->id}: {$entry->entry_type} - {$entry->reference_number} - debit={$entry->debit_amount}, credit={$entry->credit_amount}\n";
}

if (count($supplierEntries) > 0) {
    $deleted = DB::table('customer_ledger_entries')
        ->where('ledgerable_type', Supplier::class)
        ->delete();
    echo "\nDeleted {$deleted} supplier entries from customer_ledger_entries table.\n";
} else {
    echo "\nNo supplier entries to clean up.\n";
}

echo "\n=== Verifying supplier_ledger_entries ===\n";
$supplierLedgerEntries = DB::table('supplier_ledger_entries')->get();
echo "Found " . count($supplierLedgerEntries) . " entries in supplier_ledger_entries table:\n";
foreach ($supplierLedgerEntries as $entry) {
    echo "  ID {$entry->id}: type={$entry->type}, amount={$entry->amount}, balance_after={$entry->balance_after}, notes={$entry->notes}\n";
}

echo "\n=== Verifying customer_ledger_entries (should only have customer entries) ===\n";
$customerEntries = DB::table('customer_ledger_entries')->get();
echo "Found " . count($customerEntries) . " entries:\n";
foreach ($customerEntries as $entry) {
    echo "  ID {$entry->id}: type={$entry->ledgerable_type}, entry_type={$entry->entry_type}\n";
}

echo "\nDone!\n";

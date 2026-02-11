<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TABLES WITH LEDGER/SALE/PURCHASE/CUSTOMER/SUPPLIER ===\n";
$tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
foreach ($tables as $t) {
    if (str_contains($t->name, 'ledger') || str_contains($t->name, 'sale') || str_contains($t->name, 'purchase') || str_contains($t->name, 'customer') || str_contains($t->name, 'supplier')) {
        echo "  " . $t->name . "\n";
    }
}

echo "\n=== CUSTOMER_LEDGER_ENTRIES COUNT ===\n";
echo DB::table('customer_ledger_entries')->count() . "\n";

echo "\n=== SUPPLIER_LEDGER_ENTRIES TABLE ===\n";
try {
    echo "Count: " . DB::table('supplier_ledger_entries')->count() . "\n";
} catch (Exception $e) {
    echo "TABLE DOES NOT EXIST: " . $e->getMessage() . "\n";
}

echo "\n=== ALL CUSTOMER LEDGER ENTRIES ===\n";
$entries = DB::table('customer_ledger_entries')->orderBy('id', 'desc')->limit(20)->get();
foreach ($entries as $e) {
    echo json_encode($e) . "\n";
}

echo "\n=== RECENT SALES WITH CUSTOMER (last 10) ===\n";
$sales = DB::table('sales')->whereNotNull('customer_id')->orderBy('id', 'desc')->limit(10)->get(['id', 'sale_number', 'customer_id', 'total', 'payment_method', 'payment_status', 'created_at']);
foreach ($sales as $s) {
    echo json_encode($s) . "\n";
}

echo "\n=== ALL CUSTOMERS ===\n";
$customers = DB::table('customers')->get(['id', 'name', 'phone', 'outstanding_balance', 'loyalty_points']);
foreach ($customers as $c) {
    echo json_encode($c) . "\n";
}

echo "\n=== ALL SUPPLIERS ===\n";
$suppliers = DB::table('suppliers')->get(['id', 'name', 'current_balance']);
foreach ($suppliers as $s) {
    echo json_encode($s) . "\n";
}

echo "\n=== ALL PURCHASES ===\n";
$purchases = DB::table('purchases')->orderBy('id', 'desc')->limit(10)->get();
foreach ($purchases as $p) {
    echo json_encode($p) . "\n";
}

echo "\n=== CUSTOMER_LEDGER_ENTRIES COLUMNS ===\n";
$cols = DB::select("PRAGMA table_info(customer_ledger_entries)");
foreach ($cols as $c) {
    echo "  {$c->name} ({$c->type})\n";
}

echo "\n=== SUPPLIER_LEDGER_ENTRIES COLUMNS (if exists) ===\n";
try {
    $cols = DB::select("PRAGMA table_info(supplier_ledger_entries)");
    foreach ($cols as $c) {
        echo "  {$c->name} ({$c->type})\n";
    }
} catch (Exception $e) {
    echo "  N/A\n";
}

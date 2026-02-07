<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Customers: " . App\Models\Customer::count() . PHP_EOL;
echo "Suppliers: " . App\Models\Supplier::count() . PHP_EOL;
$c = App\Models\Customer::first();
$s = App\Models\Supplier::first();

echo "First customer: " . ($c?->name ?? 'none') . PHP_EOL;
echo "First supplier: " . ($s?->name ?? 'none') . PHP_EOL;

// show a small sample of ledger entries for the first supplier (if exists)
if ($s) {
    echo PHP_EOL . "Supplier ledger entries for: " . $s->name . PHP_EOL;
    $entries = App\Models\SupplierLedgerEntry::where('supplier_id', $s->id)->orderBy('id')->limit(10)->get();
    foreach ($entries as $e) {
        echo "ID: {$e->id} Type: {$e->type} Amt: {$e->amount} BalAfter: {$e->balance_after}\n";
    }
}

// sample customer ledger entries for the first customer
if ($c) {
    echo PHP_EOL . "Customer ledger entries for: " . $c->name . PHP_EOL;
    $entries = App\Models\CustomerLedgerEntry::where('customer_id', $c->id)->orderBy('id')->limit(10)->get();
    foreach ($entries as $e) {
        echo "ID: {$e->id} Type: {$e->type} Amt: {$e->amount} BalAfter: {$e->balance_after}\n";
    }
}

<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$name = 'Abin Maharjan';
$customer = App\Models\Customer::where('name', $name)->first();
if (! $customer) {
    echo "Customer '$name' not found\n";
    exit(1);
}

echo "Customer: {$customer->id} - {$customer->name}\n";

$sales = App\Models\Sale::where('customer_id', $customer->id)->orderBy('date', 'desc')->get();

if ($sales->isEmpty()) {
    echo "No sales found for this customer\n";
    exit(0);
}

foreach ($sales as $sale) {
    echo "Sale ID: {$sale->id} Invoice: {$sale->invoice_number} Date: " . ($sale->date?->format('Y-m-d') ?? $sale->created_at->format('Y-m-d')) . "\n";
    echo "  Total: {$sale->total_amount} Payment Method: {$sale->payment_method} Payment Status: {$sale->payment_status}\n";
    // find ledger entry referencing this sale
    $entry = App\Models\CustomerLedgerEntry::where('reference_type', 'Sale')
        ->where('reference_id', $sale->id)
        ->first();
    if ($entry) {
        echo "  Ledger Entry ID: {$entry->id} Type: {$entry->entry_type} Debit: {$entry->debit_amount} Credit: {$entry->credit_amount} Balance: {$entry->balance}\n";
    } else {
        echo "  No ledger entry found for this sale\n";
    }
    echo "\n";
}

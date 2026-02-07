<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Supplier;
use App\Models\Purchase;

echo "=== Recalculating Supplier Balances ===\n\n";

$suppliers = Supplier::all();

foreach ($suppliers as $supplier) {
    $oldBalance = $supplier->current_balance;
    
    // Calculate correct balance from unpaid/partial purchases
    $correctBalance = Purchase::where('supplier_id', $supplier->id)
        ->whereIn('payment_status', ['unpaid', 'partial'])
        ->sum('total');
    
    if ($oldBalance != $correctBalance) {
        echo "Supplier: {$supplier->name}\n";
        echo "  Old Balance: ₹" . number_format($oldBalance, 2) . "\n";
        echo "  Correct Balance: ₹" . number_format($correctBalance, 2) . "\n";
        
        $supplier->current_balance = $correctBalance;
        $supplier->save();
        
        echo "  ✅ Updated\n\n";
    }
}

echo "✅ All supplier balances recalculated!\n";

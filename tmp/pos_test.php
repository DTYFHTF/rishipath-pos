<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::first();
$org = App\Models\Organization::first();
$store = App\Models\Store::first();
$terminal = App\Models\Terminal::where('store_id', $store->id)->first();
$customer = App\Models\Customer::where('active', true)->first();
$variant = App\Models\ProductVariant::with('product')->find(2);

echo "Creating sale...\n";
try {
    $sale = App\Models\Sale::create([
        'organization_id' => $org->id,
        'store_id' => $store->id,
        'terminal_id' => $terminal->id,
        'customer_id' => $customer->id,
        'cashier_id' => $user->id,
        'receipt_number' => 'RCPT-TEST-'.uniqid(),
        'date' => now()->toDateString(),
        'time' => now()->toTimeString(),
        'subtotal' => 70,
        'tax_amount' => 8.4,
        'discount_amount' => 0,
        'total_amount' => 78.4,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'amount_paid' => 78.4,
        'amount_change' => 0,
        'status' => 'completed',
    ]);
    echo "Sale created: {$sale->id}\n";

    $price = 70.00;
    $quantity = 1;
    $subtotal = round($price * $quantity, 2);
    $taxRate = 12;
    $taxAmount = round((($subtotal - 0) * ($taxRate / 100)), 2);
    $total = round(($subtotal - 0) + $taxAmount, 2);

    $saleItem = App\Models\SaleItem::create([
        'sale_id' => $sale->id,
        'product_variant_id' => $variant->id,
        'product_name' => $variant->product->name,
        'product_sku' => $variant->sku ?? '',
        'quantity' => $quantity,
        'unit' => 'pcs',
        'price_per_unit' => $price,
        'cost_price' => 0,
        'subtotal' => $subtotal,
        'discount_amount' => 0,
        'tax_rate' => $taxRate,
        'tax_amount' => $taxAmount,
        'total' => $total,
    ]);

    echo "Sale item created: {$saleItem->id}\n";
    echo "Test sale complete.\n";
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
    echo 'File: '.$e->getFile().':'.$e->getLine()."\n";
}

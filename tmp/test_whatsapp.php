<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\WhatsAppService;

// Find a customer with phone
$customer = Customer::whereNotNull('phone')->first();

if (!$customer) {
    echo 'No customer with phone found. Creating test customer...' . PHP_EOL;
    $customer = Customer::create([
        'organization_id' => 1,
        'name' => 'Test WhatsApp Customer',
        'phone' => '9876543210',
        'email' => 'test@example.com',
        'active' => true,
    ]);
}

echo 'Using customer: ' . $customer->name . ' (' . $customer->phone . ')' . PHP_EOL;

// Get a product variant
$variant = ProductVariant::with('product')->first();

// Create test sale
$sale = Sale::create([
    'organization_id' => 1,
    'store_id' => 1,
    'terminal_id' => 1,
    'receipt_number' => 'RCPT-WA-TEST',
    'invoice_number' => 'INV-WA-TEST',
    'cashier_id' => 1,
    'customer_id' => $customer->id,
    'customer_name' => $customer->name,
    'customer_phone' => $customer->phone,
    'date' => now()->toDateString(),
    'time' => now()->toTimeString(),
    'subtotal' => 100,
    'tax_amount' => 10,
    'discount_amount' => 0,
    'total_amount' => 110,
    'payment_method' => 'cash',
    'payment_status' => 'paid',
    'amount_paid' => 110,
    'amount_change' => 0,
    'status' => 'completed',
]);

// Create sale item
SaleItem::create([
    'sale_id' => $sale->id,
    'product_variant_id' => $variant->id,
    'product_name' => $variant->product->name,
    'product_sku' => $variant->sku,
    'quantity' => 2,
    'unit' => $variant->unit,
    'price_per_unit' => 50,
    'cost_price' => 40,
    'subtotal' => 100,
    'discount_amount' => 0,
    'tax_rate' => 10,
    'tax_amount' => 10,
    'total' => 110,
]);

echo 'Created Sale #' . $sale->id . PHP_EOL;

// Test WhatsApp
$whatsapp = app(WhatsAppService::class);
echo 'WhatsApp configured: ' . ($whatsapp->isConfigured() ? 'Yes' : 'No (will log only)') . PHP_EOL;
$result = $whatsapp->sendReceipt($sale->fresh(['customer', 'store', 'items.productVariant.product', 'cashier']), $customer->phone);
echo 'Send result: ' . ($result ? 'SUCCESS' : 'FAILED') . PHP_EOL;
echo PHP_EOL . '✅ Check storage/logs/laravel.log for the receipt content!' . PHP_EOL;

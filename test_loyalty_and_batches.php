<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Customer;
use App\Models\Organization;
use App\Models\LoyaltyTier;
use App\Models\Store;
use App\Models\Terminal;
use App\Models\User;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ProductVariant;
use App\Services\InventoryService;

echo "=== LOYALTY SYSTEM TEST ===" . PHP_EOL . PHP_EOL;

// Step 1: Check Tiers
echo "Step 1: Loyalty Tiers" . PHP_EOL;
$org = Organization::where('slug', 'rishipath')->first();
$tiers = LoyaltyTier::where('organization_id', $org->id)
    ->orderBy('minimum_points')
    ->get();
foreach ($tiers as $tier) {
    echo sprintf("  %s: %d pts, %.2fx multiplier" . PHP_EOL, 
        $tier->name, $tier->minimum_points, $tier->points_multiplier);
}
echo PHP_EOL;

// Step 2: Create/Get Customer
echo "Step 2: Customer Setup" . PHP_EOL;
$customer = Customer::where('email', 'demo@customer.com')->first();
if (!$customer) {
    $bronzeTier = $tiers->first();
    $customer = Customer::create([
        'organization_id' => $org->id,
        'customer_code' => 'DEMO-' . rand(1000, 9999),
        'name' => 'Demo Loyalty Customer',
        'email' => 'demo@customer.com',
        'phone' => '9876543210',
        'customer_type' => 'retail',
        'active' => true,
        'loyalty_enrolled_at' => now(),
        'loyalty_points' => 0,
        'loyalty_tier_id' => $bronzeTier->id,
    ]);
    echo "  ✅ Created: " . $customer->name . PHP_EOL;
} else {
    echo "  ✅ Existing: " . $customer->name . PHP_EOL;
}
echo "  Points: " . $customer->loyalty_points . PHP_EOL;
echo "  Tier: " . ($customer->loyaltyTier ? $customer->loyaltyTier->name : 'None') . PHP_EOL;
echo PHP_EOL;

// Step 3: Create Sales with Batch Tracking
echo "Step 3: Creating Sales with Batch Tracking" . PHP_EOL;
$store = Store::where('organization_id', $org->id)->first();
$terminal = Terminal::where('store_id', $store->id)->first();
$cashier = User::where('organization_id', $org->id)->where('role_id', '!=', 1)->first();

// Get variants with stock
$variants = ProductVariant::whereHas('stockLevels', function($q) use ($store) {
    $q->where('store_id', $store->id)->where('quantity', '>', 0);
})->take(3)->get();

if ($variants->count() == 0) {
    echo "  ❌ No products with stock found!" . PHP_EOL;
    exit(1);
}

echo "  Found " . $variants->count() . " products with stock" . PHP_EOL . PHP_EOL;

// Create 3 sales
for ($i = 1; $i <= 3; $i++) {
    $variant = $variants->random();
    $quantity = rand(1, 3);
    
    // Get proper price - try multiple sources
    $price = 0;
    if ($variant->selling_price_india && $variant->selling_price_india > 0) {
        $price = (float)$variant->selling_price_india;
    } elseif ($variant->cost_price && $variant->cost_price > 0) {
        $price = (float)$variant->cost_price * 1.5; // Cost + 50% markup
    } else {
        $price = rand(100, 500); // Random price for testing
    }
    
    $saleAmount = $price * $quantity;
    
    echo "  Sale #{$i}:" . PHP_EOL;
    echo "    Product: {$variant->product->name} ({$variant->pack_size}{$variant->unit})" . PHP_EOL;
    echo "    Quantity: {$quantity} @ ₹" . number_format($price, 2) . " each" . PHP_EOL;
    
    // Create sale
    $sale = Sale::create([
        'organization_id' => $org->id,
        'store_id' => $store->id,
        'terminal_id' => $terminal->id,
        'customer_id' => $customer->id,
        'cashier_id' => $cashier->id,
        'date' => now()->toDateString(),
        'time' => now()->toTimeString(),
        'receipt_number' => 'TEST-' . now()->format('YmdHis') . '-' . $i,
        'invoice_number' => 'INV-' . now()->format('YmdHis') . '-' . $i,
        'subtotal' => $saleAmount,
        'discount_amount' => 0,
        'tax_amount' => round($saleAmount * 0.05, 2),
        'total_amount' => round($saleAmount * 1.05, 2),
        'amount_paid' => round($saleAmount * 1.05, 2),
        'amount_change' => 0,
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);
    
    // Allocate stock with FIFO and get batch info
    try {
        $allocationResult = InventoryService::decreaseStockWithBatchInfo(
            $variant->id,
            $store->id,
            $quantity,
            'sale',
            'Sale',
            $sale->id,
            $variant->cost_price,
            "Test Sale {$sale->receipt_number}"
        );
        
        // Get primary batch
        $primaryBatchId = null;
        if (!empty($allocationResult['allocated_batches'])) {
            $primaryBatchId = $allocationResult['allocated_batches'][0]['batch_id'];
            $batchNumber = $allocationResult['allocated_batches'][0]['batch_number'];
            echo "    ✅ Batch allocated: {$batchNumber} (ID: {$primaryBatchId})" . PHP_EOL;
        }
        
        // Create sale item with batch
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'batch_id' => $primaryBatchId,
            'product_name' => $variant->product->name,
            'product_sku' => $variant->sku,
            'quantity' => $quantity,
            'unit' => $variant->unit,
            'price_per_unit' => $price,
            'cost_price' => $variant->cost_price,
            'subtotal' => $saleAmount,
            'discount_amount' => 0,
            'tax_rate' => 5,
            'tax_amount' => round($saleAmount * 0.05, 2),
            'total' => round($saleAmount * 1.05, 2),
        ]);
        
        // Award loyalty points
        $tier = $customer->loyaltyTier;
        $pointsEarned = floor($sale->amount_paid * $tier->points_multiplier);
        $customer->loyalty_points += $pointsEarned;
        
        // Check for promotion
        $nextTier = LoyaltyTier::where('organization_id', $org->id)
            ->where('minimum_points', '<=', $customer->loyalty_points)
            ->orderBy('minimum_points', 'desc')
            ->first();
        
        $promoted = false;
        if ($nextTier && $nextTier->id != $customer->loyalty_tier_id) {
            $customer->loyalty_tier_id = $nextTier->id;
            $promoted = true;
        }
        
        $customer->save();
        
        echo "    Receipt: {$sale->receipt_number}" . PHP_EOL;
        echo "    Product: {$variant->product->name} (x{$quantity})" . PHP_EOL;
        echo "    Amount: ₹" . number_format($sale->total_amount, 2) . PHP_EOL;
        echo "    Points: +{$pointsEarned} (Total: {$customer->loyalty_points})" . PHP_EOL;
        echo "    Tier: {$customer->loyaltyTier->name}" . ($promoted ? " 🎉 PROMOTED!" : "") . PHP_EOL;
        
    } catch (\Exception $e) {
        echo "    ❌ Error: " . $e->getMessage() . PHP_EOL;
    }
    
    echo PHP_EOL;
    sleep(1); // Wait 1 second between sales for unique receipt numbers
}

// Calculate total spent
$totalSpent = Sale::where('customer_id', $customer->id)->sum('total_amount');

echo "=== TEST COMPLETE ===" . PHP_EOL;
echo "Customer: {$customer->name}" . PHP_EOL;
echo "Total Spent: ₹" . number_format($totalSpent, 2) . PHP_EOL;
echo "Total Points: {$customer->loyalty_points}" . PHP_EOL;
echo "Current Tier: {$customer->loyaltyTier->name}" . PHP_EOL;
echo "Tier Multiplier: {$customer->loyaltyTier->points_multiplier}x" . PHP_EOL;
echo PHP_EOL;

// Show rewards explanation
echo "=== REWARDS & REDEMPTION SYSTEM ===" . PHP_EOL;
echo PHP_EOL;
echo "📊 Points Summary:" . PHP_EOL;
echo "  • Points Earned: {$customer->loyalty_points}" . PHP_EOL;
echo "  • Points Value: ₹" . number_format($customer->loyalty_points * 0.01, 2) . " (@ ₹0.01 per point)" . PHP_EOL;
echo "  • Next Tier: ";

$nextTier = LoyaltyTier::where('organization_id', $org->id)
    ->where('minimum_points', '>', $customer->loyalty_points)
    ->orderBy('minimum_points', 'asc')
    ->first();

if ($nextTier) {
    $pointsNeeded = $nextTier->minimum_points - $customer->loyalty_points;
    $amountNeeded = ceil($pointsNeeded / $customer->loyaltyTier->points_multiplier);
    echo "{$nextTier->name} (Need {$pointsNeeded} more points or ₹{$amountNeeded} spend)" . PHP_EOL;
} else {
    echo "Already at highest tier! 🎉" . PHP_EOL;
}

echo PHP_EOL;
echo "💎 Tier Benefits:" . PHP_EOL;
foreach($tiers as $tier) {
    $icon = $tier->id == $customer->loyalty_tier_id ? '👉' : '  ';
    echo "  {$icon} {$tier->name}: {$tier->points_multiplier}x points on purchases" . PHP_EOL;
}

echo PHP_EOL;
echo "🎁 How to Redeem Rewards:" . PHP_EOL;
echo "  1. Go to POS → Select Customer → View Loyalty Points" . PHP_EOL;
echo "  2. During checkout, cashier can apply points as discount" . PHP_EOL;
echo "  3. 100 points = ₹1 discount (₹0.01 per point)" . PHP_EOL;
echo "  4. Points are deducted from customer's balance" . PHP_EOL;
echo PHP_EOL;
echo "  Example: Customer has {$customer->loyalty_points} points" . PHP_EOL;
echo "  • Can redeem: ₹" . number_format($customer->loyalty_points * 0.01, 2) . " discount" . PHP_EOL;
echo "  • Minimum redemption: 100 points (₹1)" . PHP_EOL;
echo PHP_EOL;

echo "🔄 Simulate Reward Redemption:" . PHP_EOL;
if ($customer->loyalty_points >= 100) {
    $redeemPoints = min(500, floor($customer->loyalty_points / 100) * 100);
    $discountAmount = $redeemPoints * 0.01;
    
    echo "  • Redeeming {$redeemPoints} points..." . PHP_EOL;
    echo "  • Discount Applied: ₹" . number_format($discountAmount, 2) . PHP_EOL;
    
    // Simulate redemption
    $customer->loyalty_points -= $redeemPoints;
    $customer->save();
    
    echo "  • Remaining Points: {$customer->loyalty_points}" . PHP_EOL;
    echo "  ✅ Reward redeemed successfully!" . PHP_EOL;
} else {
    echo "  ⚠️  Customer needs " . (100 - $customer->loyalty_points) . " more points to redeem rewards" . PHP_EOL;
    echo "  💡 Make more purchases to earn points!" . PHP_EOL;
}

echo PHP_EOL;

// Verify batch tracking
echo "=== BATCH TRACKING VERIFICATION ===" . PHP_EOL;
$recentItems = SaleItem::whereHas('sale', function($q) use ($customer) {
    $q->where('customer_id', $customer->id);
})
->with('batch')
->orderBy('id', 'desc')
->take(3)
->get();

foreach ($recentItems as $item) {
    echo sprintf("  Item #%d: %s | Batch: %s" . PHP_EOL,
        $item->id,
        $item->product_name,
        $item->batch_id ? $item->batch->batch_number : 'NULL ❌'
    );
}

echo PHP_EOL . "✅ Test completed successfully!" . PHP_EOL;

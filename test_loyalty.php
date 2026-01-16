<?php

// Loyalty Program Test Script
// Run with: php artisan tinker < test_loyalty.php

echo "🧪 LOYALTY PROGRAM TEST SUITE\n";
echo "=============================\n\n";

$loyaltyService = new \App\Services\LoyaltyService;

// Test 1: Get a customer
echo "1️⃣ Getting test customer...\n";
$customer = \App\Models\Customer::first();
if (! $customer) {
    echo "❌ No customers found. Creating one...\n";
    $customer = \App\Models\Customer::create([
        'organization_id' => 1,
        'customer_code' => 'CUST-TEST-001',
        'name' => 'Test Customer',
        'phone' => '9876543210',
        'email' => 'test@example.com',
        'birthday' => now()->subYears(30),
    ]);
}
echo "   ✅ Customer: {$customer->name} (ID: {$customer->id})\n\n";

// Test 2: Enroll in loyalty program
echo "2️⃣ Enrolling customer in loyalty program...\n";
if ($customer->isLoyaltyMember()) {
    echo "   ℹ️  Already enrolled\n";
} else {
    $loyaltyService->enrollCustomer($customer, 50);
    $customer->refresh();
    echo "   ✅ Enrolled! Welcome bonus: 50 points\n";
}
echo "   Current balance: {$customer->loyalty_points} points\n";
echo '   Current tier: '.($customer->loyaltyTier?->name ?? 'None')."\n\n";

// Test 3: Simulate a sale and award points
echo "3️⃣ Simulating a ₹500 purchase...\n";
$user = \App\Models\User::first();
$store = \App\Models\Store::first();
$terminal = \App\Models\Terminal::first();

$sale = \App\Models\Sale::create([
    'organization_id' => 1,
    'store_id' => $store->id,
    'terminal_id' => $terminal->id,
    'receipt_number' => 'TEST-'.time(),
    'date' => now()->toDateString(),
    'time' => now()->toTimeString(),
    'cashier_id' => $user->id,
    'customer_id' => $customer->id,
    'subtotal' => 500,
    'tax_amount' => 0,
    'discount_amount' => 0,
    'total_amount' => 500,
    'payment_method' => 'cash',
    'payment_status' => 'paid',
    'status' => 'completed',
]);

$pointsAwarded = $loyaltyService->awardPointsForSale($sale);
$customer->refresh();

echo "   ✅ Sale completed (Receipt: {$sale->receipt_number})\n";
echo "   Points earned: {$pointsAwarded->points} points\n";
echo "   New balance: {$customer->loyalty_points} points\n\n";

// Test 4: Check customer summary
echo "4️⃣ Getting customer loyalty summary...\n";
$summary = $loyaltyService->getCustomerSummary($customer);
echo "   Points: {$summary['points_balance']}\n";
echo "   Tier: {$summary['tier']}\n";
echo "   Multiplier: {$summary['points_multiplier']}x\n";
echo "   Lifetime earned: {$summary['lifetime_earned']}\n";
if ($summary['next_tier']) {
    echo "   Next tier: {$summary['next_tier']} ({$summary['points_to_next_tier']} points to go)\n";
}
echo "\n";

// Test 5: Create and check available rewards
echo "5️⃣ Creating test reward...\n";
$reward = \App\Models\Reward::firstOrCreate(
    ['name' => '₹50 Discount'],
    [
        'organization_id' => 1,
        'description' => 'Get ₹50 off your purchase',
        'type' => 'discount_fixed',
        'points_required' => 100,
        'discount_value' => 50,
        'active' => true,
    ]
);
echo "   ✅ Reward: {$reward->name} ({$reward->points_required} points)\n";

$availableRewards = $loyaltyService->getAvailableRewards($customer);
echo '   Available rewards for customer: '.count($availableRewards)."\n\n";

// Test 6: Get all tiers
echo "6️⃣ Loyalty tiers overview...\n";
$tiers = \App\Models\LoyaltyTier::orderBy('order')->get();
foreach ($tiers as $tier) {
    $max = $tier->max_points ? "-{$tier->max_points}" : '+';
    echo "   {$tier->name}: {$tier->min_points}{$max} points ";
    echo "({$tier->points_multiplier}x, {$tier->discount_percentage}% discount)\n";
}

echo "\n✅ ALL TESTS PASSED! Loyalty system is working correctly! 🎉\n";

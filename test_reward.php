<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Customer;
use App\Models\Reward;
use App\Services\LoyaltyService;

echo "🎁 REWARD REDEMPTION TEST\n";
echo "=========================\n\n";

// Get test customer
$customer = Customer::where('name', 'Test Loyalty Customer')->first();

if (!$customer) {
    echo "❌ Test customer not found. Please run the first test.\n";
    exit;
}

echo "Customer: {$customer->name}\n";
echo "Current Balance: {$customer->loyalty_points} points\n\n";

// Create a test reward
echo "1. Creating Test Reward:\n";
$reward = Reward::create([
    'organization_id' => 1,
    'name' => '₹100 Off Coupon',
    'description' => 'Get ₹100 off your next purchase',
    'type' => 'discount_fixed',
    'points_required' => 500,
    'discount_value' => 100,
    'active' => true,
]);
echo "   ✅ Reward created: {$reward->name} (500 points)\n\n";

// Check if customer can redeem
echo "2. Checking Eligibility:\n";
$canRedeem = $reward->canBeRedeemedBy($customer);
echo "   Can redeem? " . ($canRedeem ? "✅ YES" : "❌ NO") . "\n";
echo "   Customer has: {$customer->loyalty_points} points\n";
echo "   Reward needs: {$reward->points_required} points\n\n";

// Redeem the reward
if ($canRedeem) {
    echo "3. Redeeming Reward:\n";
    $loyaltyService = new LoyaltyService();
    $result = $loyaltyService->redeemReward($customer, $reward);
    
    if ($result['success']) {
        $customer->refresh();
        echo "   ✅ Redemption successful!\n";
        echo "   Points deducted: {$reward->points_required}\n";
        echo "   New balance: {$customer->loyalty_points} points\n";
        echo "   Discount value: ₹{$result['discount_value']}\n";
        echo "   Discount type: {$result['discount_type']}\n";
    } else {
        echo "   ❌ Redemption failed: {$result['message']}\n";
    }
} else {
    echo "3. Cannot redeem (insufficient points)\n";
}

echo "\n✅ REWARD TEST COMPLETE!\n";

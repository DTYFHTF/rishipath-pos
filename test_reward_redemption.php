<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Customer;
use App\Models\Reward;
use App\Models\LoyaltyTier;
use App\Models\Organization;

echo "=== REWARD REDEMPTION TEST ===" . PHP_EOL . PHP_EOL;

// Get organization
$org = Organization::first();
if (!$org) {
    echo "❌ No organization found" . PHP_EOL;
    exit(1);
}

echo "Organization: {$org->name}" . PHP_EOL . PHP_EOL;

// Find our test customer
$customer = Customer::where('name', 'Demo Loyalty Customer')
    ->where('organization_id', $org->id)
    ->first();

if (!$customer) {
    echo "❌ Test customer not found. Run test_loyalty_and_batches.php first." . PHP_EOL;
    exit(1);
}

$customer->refresh();
echo "✅ Customer: {$customer->name}" . PHP_EOL;
echo "   Points: {$customer->loyalty_points}" . PHP_EOL;
echo "   Tier: {$customer->loyaltyTier->name}" . PHP_EOL . PHP_EOL;

// Create test rewards
echo "Creating test rewards..." . PHP_EOL;

$rewards = [
    [
        'name' => '₹50 Off Purchase',
        'description' => 'Get ₹50 off your next purchase',
        'type' => 'discount_fixed',
        'points_required' => 500,
        'discount_value' => 50,
    ],
    [
        'name' => '10% Off Purchase',
        'description' => 'Get 10% off your entire purchase',
        'type' => 'discount_percentage',
        'points_required' => 300,
        'discount_value' => 10,
    ],
    [
        'name' => '₹100 Off Purchase',
        'description' => 'Get ₹100 off your next purchase',
        'type' => 'discount_fixed',
        'points_required' => 1000,
        'discount_value' => 100,
    ],
    [
        'name' => '20% Off Purchase',
        'description' => 'Get 20% off your entire purchase',
        'type' => 'discount_percentage',
        'points_required' => 800,
        'discount_value' => 20,
    ],
];

foreach ($rewards as $rewardData) {
    // Check if already exists
    $existing = Reward::where('organization_id', $org->id)
        ->where('name', $rewardData['name'])
        ->first();
    
    if ($existing) {
        echo "  • {$rewardData['name']} - Already exists (ID: {$existing->id})" . PHP_EOL;
        continue;
    }
    
    $reward = Reward::create([
        'organization_id' => $org->id,
        'name' => $rewardData['name'],
        'description' => $rewardData['description'],
        'type' => $rewardData['type'],
        'points_required' => $rewardData['points_required'],
        'discount_value' => $rewardData['discount_value'],
        'quantity' => 1,
        'valid_from' => now(),
        'valid_until' => now()->addYear(),
        'max_redemptions_per_customer' => 5,
        'total_redemptions' => 0,
        'active' => true,
    ]);
    
    echo "  ✅ {$reward->name} - {$reward->points_required} pts (ID: {$reward->id})" . PHP_EOL;
}

echo PHP_EOL;

// Show available rewards for customer
echo "=== AVAILABLE REWARDS FOR CUSTOMER ===" . PHP_EOL;
$availableRewards = Reward::where('organization_id', $org->id)
    ->where('active', true)
    ->where('points_required', '<=', $customer->loyalty_points)
    ->orderBy('points_required')
    ->get();

if ($availableRewards->isEmpty()) {
    echo "⚠️  No rewards available yet. Customer needs more points." . PHP_EOL;
} else {
    foreach ($availableRewards as $reward) {
        $canRedeem = $reward->canBeRedeemedBy($customer) ? '✅' : '❌';
        $type = $reward->type === 'discount_percentage' ? "{$reward->discount_value}%" : "₹{$reward->discount_value}";
        echo "  {$canRedeem} {$reward->name} - {$reward->points_required} pts ({$type} off)" . PHP_EOL;
    }
}

echo PHP_EOL;
echo "=== SETUP COMPLETE ===" . PHP_EOL;
echo "🎉 Reward system is ready for testing!" . PHP_EOL . PHP_EOL;
echo "📋 Next steps:" . PHP_EOL;
echo "  1. Open POS: http://localhost/admin/enhanced-p-o-s" . PHP_EOL;
echo "  2. Select customer: {$customer->name}" . PHP_EOL;
echo "  3. Click 'Redeem Rewards' button" . PHP_EOL;
echo "  4. Apply a reward and complete purchase" . PHP_EOL;
echo "  5. Verify points deducted and discount applied" . PHP_EOL;
echo PHP_EOL;

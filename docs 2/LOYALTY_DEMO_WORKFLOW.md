# Loyalty System Demo - Complete Workflow

## Overview
The loyalty system awards points to customers based on their purchases and tier multipliers. Customers earn rewards and can be automatically promoted through tiers.

## Architecture

### Models:
- `LoyaltyTier`: Defines tiers (Bronze, Silver, Gold, Platinum) with thresholds and multipliers
- `Customer`: Has `loyalty_points`, `loyalty_tier_id`, and `loyalty_enrolled_at`
- `Sale`: Awards points when customer makes purchases

### Key Components:
- Points calculation: `amount_paid * tier_multiplier`
- Auto-promotion: When points cross tier threshold
- Org-specific: Tiers are per-organization (slug: `{org-slug}-{tier}`)

---

## Demo Workflow (via Tinker)

### Step 1: Check Loyalty Tiers

```bash
php artisan tinker --execute="
\$org = App\Models\Organization::where('slug', 'rishipath')->first();
\$tiers = App\Models\LoyaltyTier::where('organization_id', \$org->id)
    ->orderBy('minimum_points', 'asc')
    ->get(['name', 'slug', 'minimum_points', 'points_multiplier']);
foreach(\$tiers as \$tier) {
    echo sprintf('%s (%s): %d points, %.2fx multiplier', 
        \$tier->name, \$tier->slug, \$tier->minimum_points, \$tier->points_multiplier) . PHP_EOL;
}
"
```

**Expected Output:**
```
Bronze (rishipath-bronze): 0 points, 1.00x multiplier
Silver (rishipath-silver): 1000 points, 1.20x multiplier
Gold (rishipath-gold): 5000 points, 1.50x multiplier
Platinum (rishipath-platinum): 15000 points, 2.00x multiplier
```

---

### Step 2: Enroll a Customer in Loyalty Program

```bash
php artisan tinker --execute="
\$customer = App\Models\Customer::where('email', 'customer@example.com')->first();
if (!\$customer) {
    \$org = App\Models\Organization::where('slug', 'rishipath')->first();
    \$customer = App\Models\Customer::create([
        'organization_id' => \$org->id,
        'customer_code' => 'CUST-' . rand(1000, 9999),
        'name' => 'Demo Customer',
        'email' => 'demo@customer.com',
        'phone' => '9876543210',
        'customer_type' => 'retail',
        'active' => true,
        'loyalty_enrolled_at' => now(),
        'loyalty_points' => 0,
        'loyalty_tier_id' => App\Models\LoyaltyTier::where('organization_id', \$org->id)->where('slug', 'rishipath-bronze')->first()->id,
    ]);
    echo 'Created new customer: ' . \$customer->name . PHP_EOL;
} else {
    echo 'Using existing customer: ' . \$customer->name . PHP_EOL;
}
echo 'Email: ' . \$customer->email . PHP_EOL;
echo 'Current Points: ' . \$customer->loyalty_points . PHP_EOL;
echo 'Tier: ' . \$customer->loyaltyTier->name . PHP_EOL;
"
```

---

### Step 3: Create a Sale and Award Points

```bash
php artisan tinker --execute="
\$customer = App\Models\Customer::where('email', 'demo@customer.com')->first();
\$org = \$customer->organization;
\$store = App\Models\Store::where('organization_id', \$org->id)->first();
\$terminal = App\Models\Terminal::where('store_id', \$store->id)->first();
\$cashier = App\Models\User::where('organization_id', \$org->id)->where('role_id', '!=', 1)->first();

// Get a product variant with stock
\$variant = App\Models\ProductVariant::whereHas('stockLevels', function(\$q) use (\$store) {
    \$q->where('store_id', \$store->id)->where('quantity', '>', 0);
})->first();

if (!\$variant) {
    echo 'No products with stock found!' . PHP_EOL;
    exit;
}

\$saleAmount = 500.00;

\$sale = App\Models\Sale::create([
    'organization_id' => \$org->id,
    'store_id' => \$store->id,
    'terminal_id' => \$terminal->id,
    'customer_id' => \$customer->id,
    'cashier_id' => \$cashier->id,
    'sale_date' => now(),
    'receipt_number' => 'DEMO-' . now()->format('YmdHis'),
    'invoice_number' => 'INV-' . now()->format('YmdHis'),
    'subtotal' => \$saleAmount,
    'discount_amount' => 0,
    'tax_amount' => \$saleAmount * 0.05,
    'total_amount' => \$saleAmount * 1.05,
    'amount_paid' => \$saleAmount * 1.05,
    'amount_change' => 0,
    'payment_method' => 'cash',
    'status' => 'completed',
]);

echo PHP_EOL . '=== SALE CREATED ===' . PHP_EOL;
echo 'Receipt: ' . \$sale->receipt_number . PHP_EOL;
echo 'Amount: ₹' . number_format(\$sale->total_amount, 2) . PHP_EOL;
echo 'Customer: ' . \$customer->name . PHP_EOL;
echo 'Previous Points: ' . \$customer->loyalty_points . PHP_EOL;

// Award loyalty points (normally done by observer/event)
\$tier = \$customer->loyaltyTier;
\$pointsEarned = floor(\$sale->amount_paid * \$tier->points_multiplier);
\$customer->loyalty_points += \$pointsEarned;

// Check for tier promotion
\$nextTier = App\Models\LoyaltyTier::where('organization_id', \$org->id)
    ->where('minimum_points', '<=', \$customer->loyalty_points)
    ->orderBy('minimum_points', 'desc')
    ->first();

if (\$nextTier && \$nextTier->id != \$customer->loyalty_tier_id) {
    \$customer->loyalty_tier_id = \$nextTier->id;
    echo 'PROMOTED to tier: ' . \$nextTier->name . PHP_EOL;
}

\$customer->save();

echo 'Points Earned: +' . \$pointsEarned . PHP_EOL;
echo 'New Total Points: ' . \$customer->loyalty_points . PHP_EOL;
echo 'Current Tier: ' . \$customer->loyaltyTier->name . PHP_EOL;
"
```

---

### Step 4: Simulate Multiple Purchases to Reach Next Tier

```bash
php artisan tinker --execute="
\$customer = App\Models\Customer::where('email', 'demo@customer.com')->first();
\$org = \$customer->organization;
\$store = App\Models\Store::where('organization_id', \$org->id)->first();
\$terminal = App\Models\Terminal::where('store_id', \$store->id)->first();
\$cashier = App\Models\User::where('organization_id', \$org->id)->where('role_id', '!=', 1)->first();

echo 'Creating multiple sales to earn Silver tier (1000 points)...' . PHP_EOL . PHP_EOL;

for (\$i = 1; \$i <= 3; \$i++) {
    \$saleAmount = 400.00;
    
    \$sale = App\Models\Sale::create([
        'organization_id' => \$org->id,
        'store_id' => \$store->id,
        'terminal_id' => \$terminal->id,
        'customer_id' => \$customer->id,
        'cashier_id' => \$cashier->id,
        'sale_date' => now(),
        'receipt_number' => 'DEMO-' . now()->format('YmdHis') . '-' . \$i,
        'invoice_number' => 'INV-' . now()->format('YmdHis') . '-' . \$i,
        'subtotal' => \$saleAmount,
        'discount_amount' => 0,
        'tax_amount' => \$saleAmount * 0.05,
        'total_amount' => \$saleAmount * 1.05,
        'amount_paid' => \$saleAmount * 1.05,
        'amount_change' => 0,
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);
    
    \$tier = \$customer->loyaltyTier;
    \$pointsEarned = floor(\$sale->amount_paid * \$tier->points_multiplier);
    \$customer->loyalty_points += \$pointsEarned;
    
    // Check for tier promotion
    \$nextTier = App\Models\LoyaltyTier::where('organization_id', \$org->id)
        ->where('minimum_points', '<=', \$customer->loyalty_points)
        ->orderBy('minimum_points', 'desc')
        ->first();
    
    \$promoted = false;
    if (\$nextTier && \$nextTier->id != \$customer->loyalty_tier_id) {
        \$customer->loyalty_tier_id = \$nextTier->id;
        \$promoted = true;
    }
    
    \$customer->save();
    
    echo 'Sale #' . \$i . ': ₹' . number_format(\$sale->total_amount, 2) . 
         ' | +' . \$pointsEarned . ' points | Total: ' . \$customer->loyalty_points . 
         ' | Tier: ' . \$customer->loyaltyTier->name;
    if (\$promoted) {
        echo ' 🎉 PROMOTED!';
    }
    echo PHP_EOL;
}

echo PHP_EOL . '=== SUMMARY ===' . PHP_EOL;
echo 'Customer: ' . \$customer->name . PHP_EOL;
echo 'Total Points: ' . \$customer->loyalty_points . PHP_EOL;
echo 'Current Tier: ' . \$customer->loyaltyTier->name . PHP_EOL;
echo 'Points Multiplier: ' . \$customer->loyaltyTier->points_multiplier . 'x' . PHP_EOL;
"
```

---

### Step 5: View Customer's Purchase History with Points

```bash
php artisan tinker --execute="
\$customer = App\Models\Customer::where('email', 'demo@customer.com')->first();
\$sales = App\Models\Sale::where('customer_id', \$customer->id)
    ->orderBy('sale_date', 'desc')
    ->get();

echo '=== CUSTOMER PURCHASE HISTORY ===' . PHP_EOL;
echo 'Customer: ' . \$customer->name . PHP_EOL;
echo 'Loyalty Points: ' . \$customer->loyalty_points . PHP_EOL;
echo 'Tier: ' . \$customer->loyaltyTier->name . PHP_EOL;
echo PHP_EOL . 'Purchases:' . PHP_EOL;

\$totalSpent = 0;
foreach(\$sales as \$sale) {
    echo sprintf('  %s | ₹%s | %s', 
        \$sale->sale_date->format('Y-m-d H:i'),
        number_format(\$sale->total_amount, 2),
        \$sale->receipt_number
    ) . PHP_EOL;
    \$totalSpent += \$sale->total_amount;
}

echo PHP_EOL . 'Total Spent: ₹' . number_format(\$totalSpent, 2) . PHP_EOL;
"
```

---

## Loyalty System Rules

### Points Calculation:
```
points_earned = floor(amount_paid * tier_multiplier)
```

### Tier Thresholds (Rishipath):
- **Bronze**: 0 points (1.0x multiplier) - entry tier
- **Silver**: 1,000 points (1.2x multiplier)
- **Gold**: 5,000 points (1.5x multiplier)
- **Platinum**: 15,000 points (2.0x multiplier)

### Auto-Promotion Logic:
When a sale is completed, the system:
1. Calculates points based on current tier multiplier
2. Adds points to customer's total
3. Checks if customer qualifies for higher tier
4. Automatically promotes if threshold reached
5. Future purchases use new multiplier

---

## Integration Points in Code

### Sale Observer:
- **Location**: `app/Observers/SaleObserver.php` (or sale creation logic)
- **Action**: After sale completed, award points and check promotion

### Customer Model:
- **Fields**: `loyalty_points`, `loyalty_tier_id`, `loyalty_enrolled_at`
- **Methods**: `awardPoints()`, `checkTierPromotion()`

### Loyalty Tier Model:
- **Fields**: `minimum_points`, `points_multiplier`
- **Scoping**: Per organization via slug

---

## API/Service Layer (Optional)

If you want to create a service for loyalty management:

```php
// app/Services/LoyaltyService.php
class LoyaltyService
{
    public function awardPoints(Sale $sale): void
    {
        if (!$sale->customer || !$sale->customer->loyalty_enrolled_at) {
            return;
        }
        
        $tier = $sale->customer->loyaltyTier;
        $pointsEarned = floor($sale->amount_paid * $tier->points_multiplier);
        
        $sale->customer->loyalty_points += $pointsEarned;
        $this->checkPromotion($sale->customer);
        $sale->customer->save();
    }
    
    protected function checkPromotion(Customer $customer): void
    {
        $nextTier = LoyaltyTier::where('organization_id', $customer->organization_id)
            ->where('minimum_points', '<=', $customer->loyalty_points)
            ->orderBy('minimum_points', 'desc')
            ->first();
            
        if ($nextTier && $nextTier->id != $customer->loyalty_tier_id) {
            $customer->loyalty_tier_id = $nextTier->id;
            // Optionally send notification
        }
    }
}
```

---

## Testing Checklist

- [ ] Customer can be enrolled in loyalty program
- [ ] Points are awarded correctly based on tier multiplier
- [ ] Customer is auto-promoted when crossing threshold
- [ ] Points are organization-specific (not shared)
- [ ] Tier names and multipliers are configurable
- [ ] Points history is tracked in sales records

---

## CLI Test Command

Run this complete demo in one go:

```bash
php artisan tinker < docs/LOYALTY_DEMO_WORKFLOW.md
```

Or use individual commands from Steps 1-5 above.

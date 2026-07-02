# 🎁 Loyalty System Implementation Guide

## Overview
The Rishipath POS loyalty system is a comprehensive points-based rewards program with tiered benefits, automatic point calculations, and flexible reward redemption.

---

## 🔢 How Points Are Calculated

### Base Point Formula
```
Base Points = floor(₹ Sale Amount)
```
**Example:** ₹150.75 transaction = 150 base points

### Tier Multiplier Application
```
Final Points = Base Points × Tier Multiplier
```

### Current Tier Multipliers
| Tier | Multiplier | Example (₹1000 sale) |
|------|-----------|---------------------|
| **Bronze** | 1.0x | 1000 points |
| **Silver** | 1.25x | 1,250 points |
| **Gold** | 1.5x | 1,500 points |
| **Platinum** | 2.0x | 2,000 points |

### Point Expiry
- **Expiration Period:** 1 year from the date earned
- **Tracking:** Each point transaction records `expires_at` timestamp
- **Automatic:** System automatically excludes expired points from balance

---

## 🏆 Tier System Architecture

### Tier Qualification Rules
Customers automatically qualify for tiers based on their **total accumulated points** (not balance after redemptions).

### Default Tier Configuration
```php
[
    'Bronze' => [
        'min_points' => 0,
        'max_points' => 999,
        'points_multiplier' => 1.0,
        'discount_percentage' => 0,
        'benefits' => ['Basic member benefits']
    ],
    'Silver' => [
        'min_points' => 1000,
        'max_points' => 4999,
        'points_multiplier' => 1.25,
        'discount_percentage' => 5,
        'benefits' => ['5% store discount', 'Birthday bonus points']
    ],
    'Gold' => [
        'min_points' => 5000,
        'max_points' => 14999,
        'points_multiplier' => 1.5,
        'discount_percentage' => 10,
        'benefits' => ['10% store discount', 'Free shipping', 'Priority support']
    ],
    'Platinum' => [
        'min_points' => 15000,
        'max_points' => null,
        'points_multiplier' => 2.0,
        'discount_percentage' => 15,
        'benefits' => ['15% store discount', 'Free express shipping', 'Exclusive events', 'Personal consultant']
    ]
]
```

### Tier Advancement
- **Automatic:** System checks tier eligibility after every point transaction
- **Irreversible:** Once a customer reaches a tier, they don't drop down
- **Method:** `LoyaltyService::updateCustomerTier($customer)`
- **Tracking:** `customers.loyalty_tier_id` updated automatically

---

## 💰 How Points Are Distributed

### Automatic Distribution (Current Implementation)
Points are automatically awarded when a sale is completed in the POS:

```php
// In EnhancedPOS.php - completeSale() method
if ($session['customer_id']) {
    $loyaltyService = new LoyaltyService;
    $loyaltyService->awardPointsForSale($sale);
}
```

### Distribution Flow
1. **Sale Completed** → POS calls `completeSale()`
2. **Customer Identified** → System checks if `customer_id` exists
3. **Points Calculated** → `LoyaltyService::awardPointsForSale($sale)`
   - Calculates base points: `floor($sale->total_amount)`
   - Retrieves customer's current tier multiplier
   - Applies multiplier: `basePoints × tier.multiplier`
4. **Points Credited** → `LoyaltyService::addPoints()`
   - Updates `customers.loyalty_points` balance
   - Creates `LoyaltyPoint` transaction record
   - Sets expiry date: `now()->addYear()`
5. **Tier Check** → `LoyaltyService::updateCustomerTier($customer)`
   - Evaluates if customer qualifies for higher tier
   - Updates `customers.loyalty_tier_id` if eligible

### Point Transaction Record
Every point distribution creates a `LoyaltyPoint` record:
```php
[
    'organization_id' => 1,
    'customer_id' => 123,
    'sale_id' => 456,
    'type' => 'earned',
    'points' => 1250,  // After multiplier
    'balance_after' => 5600,
    'description' => 'Sale #INV-2024-001',
    'expires_at' => '2025-12-31',
    'processed_by' => 1  // User ID who completed sale
]
```

---

## 🎯 How Rewards Are Redeemed

### Current Implementation Status
**Redemption Infrastructure:** ✅ Fully implemented in `LoyaltyService`  
**POS Integration:** ⚠️ Not yet integrated in UI

### Redemption Process (Backend Ready)

#### 1. Reward Catalog
Rewards are pre-configured in `rewards` table:
```php
[
    'name' => '₹100 Discount Voucher',
    'type' => 'discount',
    'discount_type' => 'fixed',  // or 'percentage'
    'discount_value' => 100.00,
    'points_required' => 500,
    'tier_restrictions' => ['Silver', 'Gold', 'Platinum'],  // Or null for all
    'max_redemptions_per_customer' => 5,
    'valid_from' => '2024-01-01',
    'valid_until' => '2024-12-31',
    'is_active' => true
]
```

#### 2. Redemption Validation
Before redemption, `Reward::canBeRedeemedBy($customer)` checks:
- ✅ Reward is active
- ✅ Customer has sufficient points
- ✅ Customer's tier meets restrictions
- ✅ Customer hasn't exceeded redemption limit
- ✅ Reward is within valid date range

#### 3. Redemption Execution
```php
$result = $loyaltyService->redeemReward($customer, $reward);

// Returns:
[
    'success' => true,
    'discount_value' => 100.00,
    'discount_type' => 'fixed',
    'points_deducted' => 500,
    'new_balance' => 1200,
    'loyalty_point_id' => 789
]
```

#### 4. Point Deduction Flow
1. **Validate** → Check eligibility via `canBeRedeemedBy()`
2. **Deduct Points** → `LoyaltyService::deductPoints()`
   - Decrements `customers.loyalty_points`
   - Creates negative `LoyaltyPoint` record (type: 'redeemed')
3. **Apply Discount** → Return discount data to POS
4. **Track Redemption** → `RewardRedemption` record created
5. **Check Redemption Count** → Increment customer's redemption counter

### Redemption Transaction Record
```php
// LoyaltyPoint record (negative points)
[
    'customer_id' => 123,
    'reward_id' => 45,
    'type' => 'redeemed',
    'points' => -500,  // Negative value
    'balance_after' => 1200,
    'description' => 'Redeemed: ₹100 Discount Voucher',
    'processed_by' => 1
]

// RewardRedemption record (separate tracking)
[
    'customer_id' => 123,
    'reward_id' => 45,
    'sale_id' => 456,  // If redeemed during sale
    'points_used' => 500,
    'discount_applied' => 100.00,
    'redeemed_at' => '2024-03-15 14:30:00',
    'redeemed_by' => 1
]
```

---

## 🎨 Recommended POS UI Integration

### Option 1: Rewards Panel in POS (Simple)
Add rewards section in right panel of POS:

```php
{{-- Add after customer selection --}}
@if($session['customer_id'])
    <x-filament::card>
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-medium">Loyalty Rewards</h3>
            <span class="text-xs bg-primary-100 text-primary-700 px-2 py-1 rounded">
                {{ $session['customer']->loyalty_points ?? 0 }} pts
            </span>
        </div>
        
        @php
            $availableRewards = \App\Models\Reward::where('is_active', true)
                ->where('points_required', '<=', $session['customer']->loyalty_points)
                ->get();
        @endphp
        
        <div class="space-y-2">
            @foreach($availableRewards as $reward)
                <button
                    wire:click="applyReward({{ $reward->id }})"
                    class="w-full text-left px-3 py-2 border rounded-lg hover:bg-primary-50 text-sm"
                >
                    <div class="font-medium">{{ $reward->name }}</div>
                    <div class="text-xs text-gray-500">{{ $reward->points_required }} pts</div>
                </button>
            @endforeach
        </div>
    </x-filament::card>
@endif
```

### Option 2: Rewards Modal (Professional)
Add modal popup for reward selection:

```php
// In EnhancedPOS.php
public function openRewardsModal()
{
    $this->emit('openModal', 'rewards-selector');
}

public function applyReward($rewardId)
{
    $customer = Customer::find($this->activeSession()['customer_id']);
    $reward = Reward::find($rewardId);
    
    $loyaltyService = new LoyaltyService;
    $result = $loyaltyService->redeemReward($customer, $reward);
    
    if ($result['success']) {
        // Apply discount to session
        $this->sessions[$this->activeSessionKey]['discount'] = [
            'type' => $result['discount_type'],
            'value' => $result['discount_value'],
            'source' => 'loyalty_reward',
            'reward_id' => $rewardId
        ];
        
        Notification::make()
            ->title('Reward Applied!')
            ->body("{$result['points_deducted']} points redeemed for {$reward->name}")
            ->success()
            ->send();
    }
}
```

---

## 📊 Benefits & Incentives Summary

### Tier-Based Automatic Benefits

#### Bronze (0-999 pts)
- **Multiplier:** 1.0x (no bonus)
- **Discount:** 0%
- **Benefits:**
  - Basic member status
  - Point accumulation tracking
  - Access to exclusive deals

#### Silver (1,000-4,999 pts)
- **Multiplier:** 1.25x (+25% bonus points on every purchase)
- **Discount:** 5% on all purchases
- **Benefits:**
  - Birthday bonus: 100 extra points
  - Early access to sales
  - Quarterly surprise gifts

#### Gold (5,000-14,999 pts)
- **Multiplier:** 1.5x (+50% bonus points on every purchase)
- **Discount:** 10% on all purchases
- **Benefits:**
  - Free shipping on all orders
  - Priority customer support
  - Exclusive Gold member events
  - Birthday bonus: 250 extra points

#### Platinum (15,000+ pts)
- **Multiplier:** 2.0x (double points on every purchase!)
- **Discount:** 15% on all purchases
- **Benefits:**
  - Free express shipping
  - Personal shopping consultant
  - VIP events and product launches
  - Birthday bonus: 500 extra points
  - Annual anniversary gift

### Reward Catalog (Redemption-Based)

#### Fixed Discounts
| Reward | Points Required | Value | Tier Restriction |
|--------|----------------|-------|------------------|
| ₹50 Voucher | 250 | ₹50 | All |
| ₹100 Voucher | 500 | ₹100 | All |
| ₹250 Voucher | 1,000 | ₹250 | Silver+ |
| ₹500 Voucher | 2,000 | ₹500 | Gold+ |
| ₹1,000 Voucher | 4,000 | ₹1,000 | Platinum |

#### Percentage Discounts
| Reward | Points Required | Value | Tier Restriction |
|--------|----------------|-------|------------------|
| 5% Off Next Purchase | 300 | 5% | All |
| 10% Off Next Purchase | 600 | 10% | Silver+ |
| 20% Off Next Purchase | 1,500 | 20% | Gold+ |
| 30% Off Next Purchase | 3,000 | 30% | Platinum |

#### Special Rewards
| Reward | Points Required | Benefit | Tier Restriction |
|--------|----------------|---------|------------------|
| Free Product (₹200) | 1,000 | Choose any product up to ₹200 | Silver+ |
| Premium Gift Box | 2,500 | Curated Ayurvedic product set | Gold+ |
| Wellness Consultation | 3,500 | 1-hour consultation with expert | Gold+ |
| VIP Experience Day | 10,000 | Store tour + consultation + gift hamper | Platinum |

---

## 🔧 Database Schema Reference

### `loyalty_tiers` Table
```sql
- id
- organization_id
- name (Bronze/Silver/Gold/Platinum)
- min_points (tier entry threshold)
- max_points (tier upper limit, null for top tier)
- points_multiplier (1.0, 1.25, 1.5, 2.0)
- discount_percentage (0, 5, 10, 15)
- benefits (JSON array of text descriptions)
- color (hex color for UI badges)
- is_active
- created_at, updated_at
```

### `loyalty_points` Table
```sql
- id
- organization_id
- customer_id
- sale_id (nullable, links to sale if earned from purchase)
- reward_id (nullable, links to reward if redeemed)
- type ('earned' or 'redeemed')
- points (positive for earned, negative for redeemed)
- balance_after (customer's point balance after this transaction)
- description (e.g., "Sale #INV-001" or "Redeemed: ₹100 Voucher")
- expires_at (for earned points, 1 year from creation)
- processed_by (user_id who processed the transaction)
- created_at
```

### `rewards` Table
```sql
- id
- organization_id
- name
- description
- type ('discount', 'free_product', 'service')
- discount_type ('fixed' or 'percentage')
- discount_value
- points_required
- tier_restrictions (JSON array: ['Silver', 'Gold'] or null for all)
- max_redemptions_per_customer
- total_redemptions_limit
- valid_from, valid_until
- is_active
- image_path (optional)
- created_at, updated_at
```

### `reward_redemptions` Table
```sql
- id
- organization_id
- customer_id
- reward_id
- sale_id (nullable, if redeemed during sale)
- points_used
- discount_applied
- redeemed_at
- redeemed_by (user_id)
- created_at
```

---

## 📈 Example Customer Journey

### Month 1-3: Bronze Tier
- Makes 10 purchases totaling ₹10,000
- Earns: 10,000 points (1.0x multiplier)
- Balance: 10,000 points
- **Advances to Silver Tier** 🎉

### Month 4-8: Silver Tier
- Makes 15 purchases totaling ₹20,000
- Earns: 25,000 points (1.25x multiplier)
- Redeems: -2,000 points (₹500 voucher)
- Balance: 33,000 points
- Gets 5% automatic discount on all purchases
- **Advances to Gold Tier** 🎉

### Month 9-18: Gold Tier
- Makes 20 purchases totaling ₹30,000
- Earns: 45,000 points (1.5x multiplier)
- Redeems: -8,000 points (various rewards)
- Balance: 70,000 points
- Gets 10% automatic discount on all purchases
- Free shipping on all orders
- **Advances to Platinum Tier** 🎉

### Month 19+: Platinum Tier
- Makes 10 purchases totaling ₹15,000
- Earns: 30,000 points (2.0x multiplier!)
- Balance: 100,000+ points
- Gets 15% automatic discount on all purchases
- VIP treatment and exclusive benefits

---

## 🚀 Implementation Roadmap

### Phase 1: Current Status ✅
- ✅ Database schema complete
- ✅ LoyaltyService backend logic
- ✅ Automatic point distribution on sale
- ✅ Tier advancement logic
- ✅ Point expiry tracking
- ✅ Redemption validation

### Phase 2: POS UI Integration (Recommended Next)
- [ ] Add rewards panel in POS right sidebar
- [ ] Show customer tier badge and point balance
- [ ] "Apply Reward" button with modal
- [ ] Display available rewards filtered by eligibility
- [ ] Apply discount to cart when reward redeemed
- [ ] Show "Points to Next Tier" progress bar

### Phase 3: Customer Portal (Optional)
- [ ] Customer-facing loyalty dashboard
- [ ] Points history and expiry tracker
- [ ] Tier progress visualization
- [ ] Rewards catalog browsing
- [ ] Redemption history

### Phase 4: Advanced Features (Future)
- [ ] Bonus point campaigns (double points weekends)
- [ ] Referral rewards
- [ ] Birthday bonus automation
- [ ] Email notifications for tier advancement
- [ ] SMS/WhatsApp point balance alerts

---

## 💡 Best Practices

### Point Distribution
1. **Always link to sale:** Include `sale_id` in point records for audit trail
2. **Set expiry dates:** Enforce 1-year expiry to encourage redemption
3. **Process synchronously:** Award points immediately on sale completion
4. **Handle edge cases:** What if customer is deleted? (cascade or preserve?)

### Reward Redemption
1. **Validate before deducting:** Always call `canBeRedeemedBy()` first
2. **Use transactions:** Wrap point deduction + discount application in DB transaction
3. **Track redemption count:** Prevent over-redemption of limited rewards
4. **Clear communication:** Show customer exactly what they're redeeming

### Tier Management
1. **Review thresholds quarterly:** Adjust based on customer behavior data
2. **Never demote:** Customers should only move up, not down
3. **Communicate changes:** Email customers when they advance to new tier
4. **Tier-specific benefits:** Make higher tiers feel exclusive and valuable

---

## 📞 Support & Questions

For technical questions about loyalty system implementation:
- **Code Location:** `app/Services/LoyaltyService.php`
- **Models:** `app/Models/LoyaltyTier.php`, `Reward.php`, `LoyaltyPoint.php`
- **Database:** `database/migrations/*loyalty*`

---

**Last Updated:** 2024-12-31  
**Version:** 1.0  
**Author:** Rishipath Development Team

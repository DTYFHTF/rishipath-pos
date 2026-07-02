# Customer Loyalty Program - Complete Guide

## Overview

The Rishipath POS Loyalty Program is a comprehensive customer retention system that rewards repeat purchases with points, tiers, and exclusive benefits.

## Features

### 🎯 Points System
- **Earn Rate**: ₹1 = 1 point (base rate)
- **Tier Multipliers**: Bronze (1x), Silver (1.25x), Gold (1.5x), Platinum (2x)
- **Point Expiry**: Points expire after 1 year
- **Welcome Bonus**: 50 points on enrollment
- **Birthday Bonus**: 100 points on birthday (increases with tier)

### 🏆 Tier Levels

#### Bronze (0-999 points)
- 1x points multiplier
- Birthday bonus
- Access to seasonal offers

#### Silver (1,000-4,999 points)
- 1.25x points multiplier
- 2.5% discount on all purchases
- Priority customer support
- Double birthday bonus (200 points)
- Early access to new products

#### Gold (5,000-14,999 points)
- 1.5x points multiplier
- 5% discount on all purchases
- Free home delivery
- Triple birthday bonus (300 points)
- Exclusive gold member events
- Extended return policy (30 days)

#### Platinum (15,000+ points)
- 2x points multiplier
- 10% discount on all purchases
- Free express delivery
- 5x birthday bonus (500 points)
- VIP customer support 24/7
- Exclusive platinum member gifts
- Personalized product recommendations
- Extended return policy (60 days)

### 🎁 Rewards Catalog

Create rewards that customers can redeem using their points:

**Reward Types:**
1. **Percentage Discount**: e.g., 10% off entire purchase (500 points)
2. **Fixed Discount**: e.g., ₹100 off (300 points)
3. **Free Product**: e.g., Free Assam Tea 100g pack (250 points)
4. **Cashback**: e.g., ₹50 cashback for next purchase (200 points)

**Reward Configuration:**
- Set point requirements
- Define validity periods
- Limit redemptions per customer
- Restrict to specific tiers

## Database Schema

### loyalty_tiers
- Tier configuration (name, points range, multipliers, benefits)
- Badge colors and icons
- Discount percentages

### loyalty_points
- Transaction log of all points earned/redeemed
- Links to sales and rewards
- Expiry dates
- Balance tracking

### rewards
- Reward definitions
- Point costs
- Validity periods
- Redemption limits

### customers (extended)
- `loyalty_points`: Current points balance
- `loyalty_tier_id`: Current tier
- `birthday`: For birthday bonuses
- `last_birthday_bonus_at`: Track bonus awarding
- `loyalty_enrolled_at`: Enrollment timestamp

## Usage Guide

### For Staff

#### Enrolling Customers
1. Customer information is captured during first purchase
2. System automatically enrolls them in loyalty program
3. Welcome bonus (50 points) is awarded immediately
4. Customer starts at Bronze tier

#### At Checkout (POS)
1. Select customer or enter phone number
2. System displays:
   - Current points balance
   - Current tier and benefits
   - Available rewards
3. Customer can choose to:
   - Earn points (automatic with purchase)
   - Redeem reward before payment
4. Points are awarded after successful payment

#### Viewing Customer Loyalty
Navigate to **Customers** → Select customer → View loyalty details:
- Points balance
- Tier status
- Points history
- Redemption history
- Birthday information

### For Administrators

#### Managing Tiers
**Navigation**: Loyalty → Loyalty Tiers

**Actions:**
- View all tiers
- Edit tier benefits and multipliers
- Adjust point thresholds
- Activate/deactivate tiers

#### Managing Rewards
**Navigation**: Loyalty → Rewards

**Creating a Reward:**
1. Click "New Reward"
2. Enter name and description
3. Select reward type
4. Set point requirement
5. Configure options:
   - Validity dates
   - Maximum redemptions
   - Tier restrictions
6. Save

#### Loyalty Overview Dashboard
**Navigation**: Loyalty → Loyalty Overview

**Displays:**
- Total loyalty members
- Active members (90 days)
- Points issued and redeemed
- Tier distribution
- Top 10 members
- Recent points activity

#### Dashboard Widgets
Main dashboard shows:
- Loyalty member count
- Points earned this month
- Points redeemed this month

## Automated Features

### Birthday Bonuses
**Schedule**: Daily at 12:01 AM
**Command**: `php artisan loyalty:birthday-bonuses`

**Process:**
- Checks for customers with today's birthday
- Awards bonus points based on tier
- Sends notification (if configured)
- Prevents duplicate bonuses in same year

**Manual Execution:**
```bash
php artisan loyalty:birthday-bonuses
```

### Point Expiry (Future)
**Planned Feature**: Automatically expire points after 1 year

**Command**:
```bash
php artisan loyalty:expire-points
```

## API Reference

### LoyaltyService Methods

#### `awardPointsForSale(Sale $sale)`
Awards points for a completed sale
- Calculates base points (₹1 = 1 point)
- Applies tier multiplier
- Creates loyalty point record
- Updates customer balance

#### `redeemReward(Customer $customer, Reward $reward, ?int $processedBy = null)`
Redeems a reward for a customer
- Validates eligibility
- Deducts points
- Returns discount details
- Updates redemption count

#### `addPoints(Customer $customer, int $points, string $type, ...)`
Manually add/deduct points
- Types: earned, redeemed, expired, adjusted, bonus
- Updates balance
- Triggers tier recalculation

#### `awardBirthdayBonus(Customer $customer, int $bonusPoints = 100)`
Awards birthday bonus
- Checks if bonus is due
- Awards points
- Marks as awarded for current year

#### `enrollCustomer(Customer $customer, int $welcomeBonus = 50)`
Enrolls customer in loyalty program
- Assigns starter tier
- Awards welcome bonus
- Sets enrollment date

#### `updateCustomerTier(Customer $customer)`
Recalculates and updates customer tier based on points

#### `getCustomerSummary(Customer $customer)`
Returns comprehensive loyalty summary
- Points balance
- Current tier info
- Next tier progress
- Lifetime statistics

#### `getAvailableRewards(Customer $customer)`
Returns rewards customer can currently redeem

## Business Intelligence

### Key Metrics to Track

1. **Enrollment Rate**: % of customers enrolled
2. **Active Members**: Members with purchases in last 90 days
3. **Redemption Rate**: Points redeemed / Points issued
4. **Average Points Balance**: Total outstanding points / Members
5. **Tier Distribution**: Members in each tier
6. **Top Members**: Highest point earners

### Reports Available

1. **Loyalty Overview**: General program health
2. **Customer Analytics Report**: Includes RFM + Loyalty data
3. **Points Activity Log**: All transactions
4. **Reward Popularity**: Most redeemed rewards

## Configuration

### Adjusting Point Values
Edit `LoyaltyService.php`:
```php
// Change base earn rate (currently ₹1 = 1 point)
$basePoints = (int) floor($sale->total_amount * 2); // Now ₹1 = 2 points
```

### Adjusting Bonuses
Edit command or service:
```php
// Change welcome bonus (currently 50 points)
$loyaltyService->enrollCustomer($customer, 100); // Now 100 points

// Change birthday bonus (currently 100 points)
$loyaltyService->awardBirthdayBonus($customer, 200); // Now 200 points
```

### Adding Custom Tiers
1. Navigate to Loyalty Tiers
2. Create new tier
3. Set appropriate point ranges
4. Configure multipliers and benefits

## Best Practices

### For Store Managers

1. **Promote Enrollment**: Encourage all customers to join
2. **Highlight Benefits**: Display tier benefits at POS
3. **Monitor Redemptions**: Track popular rewards
4. **Seasonal Campaigns**: Create limited-time rewards
5. **Birthday Reminders**: Use birthday list for targeted marketing

### For System Administrators

1. **Regular Audits**: Review point balances monthly
2. **Tier Adjustment**: Adjust thresholds based on spending patterns
3. **Reward Refresh**: Update reward catalog quarterly
4. **Point Expiry**: Monitor and communicate expiring points
5. **Data Backup**: Regularly backup loyalty data

## Troubleshooting

### Points Not Awarded After Sale
**Check:**
1. Customer was selected at checkout
2. Sale completed successfully
3. Customer is enrolled in loyalty program
4. No errors in logs

**Fix:**
```bash
# Manually award points
php artisan tinker
$sale = \App\Models\Sale::find(123);
$loyaltyService = new \App\Services\LoyaltyService();
$loyaltyService->awardPointsForSale($sale);
```

### Birthday Bonus Not Awarded
**Check:**
1. Customer has birthday set
2. Today matches birthday month-day
3. Bonus not already given this year
4. Cron job is running

**Manual Award:**
```bash
php artisan loyalty:birthday-bonuses
```

### Tier Not Updating
**Trigger Manual Update:**
```bash
php artisan tinker
$customer = \App\Models\Customer::find(123);
$loyaltyService = new \App\Services\LoyaltyService();
$loyaltyService->updateCustomerTier($customer);
```

## Future Enhancements

### Planned Features

1. **SMS/Email Notifications**
   - Point earning notifications
   - Birthday greetings with bonus
   - Tier upgrade celebrations
   - Reward redemption confirmations

2. **Mobile App Integration**
   - Digital loyalty card
   - Push notifications
   - In-app reward browsing
   - QR code for redemption

3. **Advanced Rewards**
   - Combo deals (Product bundles)
   - Time-limited flash rewards
   - Referral bonuses
   - Social media engagement rewards

4. **Gamification**
   - Badges and achievements
   - Streak bonuses (consecutive visits)
   - Challenges and missions
   - Leaderboards

5. **Analytics Dashboard**
   - Member lifetime value
   - Cohort analysis
   - Redemption patterns
   - ROI calculations

## Support

For technical issues or questions:
- Review logs: `storage/logs/laravel.log`
- Check database: `loyalty_points`, `loyalty_tiers`, `rewards` tables
- Run diagnostics: `php artisan about`

---

**Version**: 1.0  
**Last Updated**: December 31, 2025  
**Documentation**: Phase 6 - Customer Loyalty Program

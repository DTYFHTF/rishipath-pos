# RishiPath POS - A Day in the Life: Operations Walkthrough

> **Follow a store through a complete business day to see every feature in action**

---

## Meet "Himalayan Ayurveda" — A Typical RishiPath Store

- **Location**: Kathmandu, Nepal
- **Owner**: Ram Bahadur Sharma
- **Staff**: 1 Manager (Sita), 2 Cashiers (Anita, Bikash), 1 Inventory Clerk (Deepak)
- **Products**: 200+ Ayurvedic products in 8 categories
- **Daily Sales**: ~NPR 80,000 (~$600)
- **Customers**: 40-60 per day

---

## 6:30 AM — Opening the Store

### Deepak (Inventory Clerk) Starts His Day

**Feature Used: Dashboard & Low Stock Alerts**

Deepak logs in with his PIN and sees the dashboard:

```
┌─────────────────────────────────────────────┐
│  📊 Dashboard - Himalayan Ayurveda          │
│                                             │
│  Yesterday's Sales:  NPR 78,450            │
│  This Month:         NPR 1,245,000         │
│  Active Customers:   342                    │
│  Products in Stock:  1,847 units            │
│                                             │
│  ⚠️ LOW STOCK ALERTS (5 items)              │
│  • Amla Hair Oil 100ml — 4 units left       │
│  • Triphala Powder 200g — 2 units left      │
│  • Ashwagandha Caps 60ct — 0 units ⛔       │
│  • Brahmi Ghritam 100ml — 3 units left      │
│  • Neem Soap — 8 units left                 │
│                                             │
│  🔴 EXPIRY WARNING (2 batches)              │
│  • Chyawanprash 500g Batch#2024-12          │
│    → Expires in 15 days!                    │
│  • Tulsi Tea Box Batch#2025-01              │
│    → Expires in 22 days                     │
└─────────────────────────────────────────────┘
```

**Action**: Deepak creates a purchase order for the low-stock items.

---

## 7:00 AM — Creating a Purchase Order

### Deepak Creates an Order for Their Main Supplier

**Feature Used: Purchase Order Management**

```
Step 1: Click "New Purchase Order"
Step 2: Select Supplier → "Patanjali Distributor Nepal"
Step 3: Select Store → "Himalayan Ayurveda - Kathmandu"
Step 4: Add items:

┌──────────────────────────────────────────────────────┐
│  Purchase Order: PUR-20260419-0003                   │
│  Supplier: Patanjali Distributor Nepal               │
│                                                      │
│  Product                 Qty    Cost     Total       │
│  ─────────────────────────────────────────────       │
│  Amla Oil 100ml          50    NPR 120   NPR 6,000  │
│  Triphala Powder 200g    30    NPR 180   NPR 5,400  │
│  Ashwagandha Caps 60ct   40    NPR 350   NPR 14,000 │
│  Brahmi Ghritam 100ml    25    NPR 280   NPR 7,000  │
│  Neem Soap               100   NPR 45    NPR 4,500  │
│  ─────────────────────────────────────────────       │
│  Subtotal:                              NPR 36,900  │
│  VAT (13%):                             NPR 4,797   │
│  Total:                                 NPR 41,697  │
└──────────────────────────────────────────────────────┘

Step 5: Save as "Ordered" → Supplier notified
```

---

## 7:30 AM — Handling the Expiring Stock

### Deepak Puts Expiring Items on Priority Display

**Feature Used: Batch & Expiry Tracking, Stock Adjustment**

Deepak checks the Chyawanprash batch expiring in 15 days:

```
Batch: #2024-12
Product: Chyawanprash 500g
Manufactured: December 2024
Expires: May 2026 (15 days away!)
Remaining: 8 units
Status: 🟡 EXPIRING SOON
```

**Action**: He tells Sita (Manager) to put these 8 units on a promotional display with a discount. Sita creates a store-specific pricing override:
- Regular price: NPR 650
- Promotional price: NPR 450 (30% off)

For the Tulsi Tea (22 days to expiry, 5 units), he marks them for priority selling — cashiers will suggest these to customers.

---

## 9:00 AM — Store Opens, First Customers Arrive

### Anita (Cashier) at Register 1

**Feature Used: Point of Sale (POS)**

**Customer 1: Priya Thapa (Loyal Customer, Silver Tier)**

Priya is a regular. She walks in and Anita starts a sale:

```
Step 1: Scan barcode of Amla Oil 200ml → Product appears instantly
        "Amla Hair Oil 200ml — NPR 375 — Stock: 18 units ✅"

Step 2: Customer asks for "that digestive powder — I forget the name"
        Anita types "digestive" → Multiple results appear
        Triphala Powder shows up → Scan or click to add

Step 3: Customer remembers she needs Tulsi Tea
        "We have Tulsi Tea on special — NPR 180 instead of NPR 250!"
        Customer: "Great, add 2 boxes"

Step 4: Select Customer → Search "Priya" → Priya Thapa appears
        System shows: "Silver Tier | 1,847 Points | 5% Member Discount"

Step 5: Apply loyalty tier discount automatically

┌──────────────────────────────────────────────────────┐
│  SALE                                                │
│                                                      │
│  Customer: Priya Thapa (Silver Tier 🥈)              │
│                                                      │
│  Item                    Qty  Price    Total          │
│  ─────────────────────────────────────────           │
│  Amla Hair Oil 200ml      1   NPR 375  NPR 375      │
│  Triphala Powder 200g     1   NPR 240  NPR 240      │
│  Tulsi Tea Box            2   NPR 180  NPR 360      │
│  ─────────────────────────────────────────           │
│  Subtotal:                             NPR 975      │
│  Loyalty Discount (5%):               -NPR 49       │
│  VAT (13%):                            NPR 120      │
│  ─────────────────────────────────────────           │
│  TOTAL:                                NPR 1,046    │
│                                                      │
│  Points Earned: 1,046 × 1.25 = 1,308 points         │
│  New Balance: 3,155 points                           │
│  Progress: 1,845 points to Gold Tier! 🎯             │
└──────────────────────────────────────────────────────┘

Step 6: Payment → Priya pays NPR 1,046 via eSewa

Step 7: Receipt sent via WhatsApp ✅
        Priya receives the receipt on her phone instantly
```

**Behind the Scenes (Automatic):**
- Stock decreased: Amla Oil -1 (FIFO from oldest batch), Triphala -1, Tulsi Tea -2
- Sale recorded with receipt number: RSH-20260419-001
- Priya's loyalty points updated: 1,847 → 3,155
- Priya's total spent updated
- Inventory movements logged for all 3 products

---

## 9:30 AM — Handling a Busy Rush

### Bikash (Cashier) at Register 2 — Multi-Session Magic

**Feature Used: Session Management (Park & Resume)**

**Customer 2: Walk-in customer** ordering multiple items. While Bikash is building the cart...

**Customer 3: Rushed customer** with just one item — "I'm in a hurry, I just need one Chyawanprash!"

```
Step 1: Bikash PARKS Customer 2's sale → "Parked: Walk-in Large Order"
        (All items saved — nothing lost)

Step 2: Quick sale for Customer 3:
        Scan Chyawanprash 500g → NPR 450 (promotional!)
        Payment: Cash NPR 500 → Change: NPR 50
        Receipt printed → Done in 30 seconds

Step 3: RESUME Customer 2's parked sale → Everything is back
        Continue adding items and complete the sale
```

---

## 10:00 AM — Split Payment

### Anita Handles a Split Payment

**Feature Used: Split Payments**

**Customer 4: Ram Kumar** wants to buy NPR 2,500 worth of products but doesn't have enough cash.

```
Total: NPR 2,500

Ram: "I have NPR 1,500 cash, can I pay the rest with Khalti?"

┌──────────────────────────────────────┐
│  SPLIT PAYMENT                       │
│                                      │
│  Total Due:          NPR 2,500       │
│                                      │
│  Payment 1: Cash     NPR 1,500  ✅   │
│  Payment 2: Khalti   NPR 1,000  ✅   │
│                                      │
│  Remaining:          NPR 0          │
│  Status: PAID IN FULL ✅             │
└──────────────────────────────────────┘
```

Receipt shows both payment methods clearly.

---

## 11:00 AM — Supplier Delivery Arrives

### Deepak Receives a Previous Purchase Order

**Feature Used: Purchase Receiving, Batch Creation**

The supplier arrives with stock from a purchase order placed 3 days ago.

```
Step 1: Open Purchase Order PUR-20260416-0001
Step 2: Verify delivered items against order
Step 3: Mark as "Received"

🔄 AUTOMATIC ACTIONS:
━━━━━━━━━━━━━━━━━━━━

✅ Batch Created: BATCH-2026-04-019
   Product: Amla Hair Oil 100ml
   Manufactured: March 2026
   Expires: March 2028
   Quantity: 100 units
   Cost: NPR 120/unit

✅ Batch Created: BATCH-2026-04-020
   Product: Brahmi Oil 200ml
   Manufactured: February 2026
   Expires: February 2028
   Quantity: 50 units
   Cost: NPR 280/unit

✅ Stock Levels Updated:
   Amla Oil 100ml: 4 → 104 units
   Brahmi Oil 200ml: 12 → 62 units

✅ Inventory Movements Logged:
   Type: Purchase
   User: Deepak
   Reference: PUR-20260416-0001

✅ Supplier Ledger Updated:
   Patanjali Distributor: +NPR 68,000 owed
   Running Balance: NPR 1,45,000 outstanding
```

---

## 12:00 PM — Manager Reviews

### Sita (Manager) Checks Mid-Day Performance

**Feature Used: Dashboard Widgets, Sales Report**

```
┌─────────────────────────────────────────────┐
│  📊 Mid-Day Report                          │
│                                             │
│  Sales Today (so far):   NPR 42,350        │
│  Transactions:           23                 │
│  Average Transaction:    NPR 1,841         │
│                                             │
│  Payment Breakdown:                         │
│  💵 Cash:    NPR 24,500 (58%)              │
│  📱 eSewa:   NPR 12,350 (29%)             │
│  📱 Khalti:  NPR 3,500 (8%)               │
│  💳 Card:    NPR 2,000 (5%)               │
│                                             │
│  Top Seller Today:                          │
│  1. Amla Hair Oil 200ml (8 units)           │
│  2. Chyawanprash 500g (6 units - promo!)    │
│  3. Triphala Powder 200g (5 units)          │
└─────────────────────────────────────────────┘
```

Sita is happy — the Chyawanprash promotion is working! 6 of 8 expiring units sold already.

---

## 1:00 PM — Credit Sale & Customer Ledger

### Anita Processes a Credit Sale

**Feature Used: Customer Ledger, Payment Tracking**

**Customer 5: Dr. Ganesh Shrestha** — a regular customer who buys in bulk for his clinic and pays monthly.

```
Dr. Ganesh buys NPR 15,000 worth of products.
He'll pay at the end of the month.

Step 1: Build cart with all items
Step 2: Select Customer → Dr. Ganesh Shrestha
Step 3: Complete sale (payment status: pending)

┌──────────────────────────────────────────────────────┐
│  CUSTOMER LEDGER: Dr. Ganesh Shrestha                │
│                                                      │
│  Date        Type       Debit     Credit   Balance   │
│  ──────────────────────────────────────────────      │
│  2026-03-15  Sale       12,000    -        12,000    │
│  2026-03-31  Payment    -         12,000   0         │
│  2026-04-05  Sale       8,500     -        8,500     │
│  2026-04-19  Sale       15,000    -        23,500    │
│  ──────────────────────────────────────────────      │
│  Outstanding Balance:                      NPR 23,500│
└──────────────────────────────────────────────────────┘
```

---

## 2:00 PM — New Customer Joins Loyalty

### Bikash Enrolls a New Customer in the Loyalty Program

**Feature Used: Customer Management, Loyalty Enrollment**

**Customer 6: Maya Devi** — first time in the store, impressed by the products.

```
Step 1: Quick create customer at POS
        Name: Maya Devi
        Phone: +977-9841234567

Step 2: "Would you like to join our loyalty program? It's free!"
        Maya: "Sure!"

Step 3: Enroll → 
        ✅ Customer created: CUST-20260419-0047
        ✅ Loyalty enrolled
        ✅ Welcome bonus: 50 points!
        ✅ Tier: Bronze 🥉

Step 4: Complete her purchase (NPR 1,200)
        Points earned: 1,200 × 1.0 (Bronze multiplier) = 1,200 points
        New balance: 1,250 points

        "Congratulations Maya ji! You already have 1,250 points.
         At 1,000 points you'll reach Silver tier with a 5% discount
         on every purchase!"

        Maya: "Oh, I'm already Silver?"
        
        "Almost — you need to accumulate 1,000 points total.
         You've earned 1,250 today, so yes — you're now Silver! 🥈"

        ✅ Auto-upgraded to Silver Tier!
```

---

## 3:00 PM — Supplier Payment

### Sita (Manager) Makes a Supplier Payment

**Feature Used: Supplier Ledger, Payment Recording**

```
┌──────────────────────────────────────────────────────┐
│  SUPPLIER: Patanjali Distributor Nepal                │
│                                                      │
│  Outstanding Balance: NPR 1,45,000                   │
│                                                      │
│  Breakdown:                                          │
│  PUR-20260401-0001: NPR 52,000 (18 days old)        │
│  PUR-20260410-0002: NPR 25,000 (9 days old)         │
│  PUR-20260416-0001: NPR 68,000 (3 days old)         │
└──────────────────────────────────────────────────────┘

Step 1: Record Payment
        Amount: NPR 52,000
        Method: Bank Transfer
        Reference: TXN-2026041900045

Step 2: Payment allocated to oldest purchase first (FIFO)
        PUR-20260401-0001: PAID IN FULL ✅

Step 3: Updated Balance: NPR 93,000
```

---

## 4:00 PM — Internet Goes Down!

### The POS Keeps Working

**Feature Used: Offline-First Architecture**

```
⚠️ Internet disconnected at 4:02 PM

What STILL works:
✅ POS billing — all sales continue normally
✅ Inventory tracking — stock levels update locally
✅ Customer search — full customer database available
✅ Loyalty points — calculated and stored locally
✅ Receipt printing — works without internet
✅ Barcode scanning — instant product lookup

What's paused (resumes when online):
⏸️ WhatsApp receipt delivery (queued)
⏸️ Cloud sync (data stored locally)

4:45 PM — Internet restored
✅ 7 sales during outage synced to cloud
✅ Queued WhatsApp receipts sent
✅ Dashboard updated with offline transactions
```

**Zero sales lost. Zero data lost.**

---

## 5:00 PM — Loyalty Reward Redemption

### Anita Helps a Customer Redeem Points

**Feature Used: Reward Catalog, Point Redemption**

**Customer 7: Hari Prasad** (Gold Tier, 8,200 points)

```
Hari: "I've been collecting points. What can I get?"

Anita opens the Rewards screen:

┌──────────────────────────────────────────────────────┐
│  AVAILABLE REWARDS for Hari Prasad (Gold 🥇)         │
│                                                      │
│  🎁 NPR 200 Off Next Purchase     — 800 points      │
│  🎁 Free Amla Oil 100ml           — 600 points      │
│  🎁 NPR 500 Off Purchase          — 2,000 points    │
│  🎁 Free Chyawanprash 500g        — 3,000 points    │
│  💎 Gold Exclusive: 20% Off Any    — 4,000 points    │
│                                                      │
│  Your Balance: 8,200 points                          │
└──────────────────────────────────────────────────────┘

Hari: "I'll take the Gold Exclusive 20% off!"

Step 1: Select reward → 4,000 points deducted
Step 2: Build cart with Hari's purchases (NPR 3,500)
Step 3: 20% discount applied → -NPR 700
Step 4: Total: NPR 2,800 + VAT
Step 5: Complete sale

Hari's updated balance: 8,200 - 4,000 + 2,800 new = 7,000 points
```

---

## 6:30 PM — End of Day

### Sita (Manager) Reviews the Day

**Feature Used: Sales Report, Profit Report, Cashier Performance**

```
┌──────────────────────────────────────────────────────┐
│  📊 END OF DAY REPORT — April 19, 2026               │
│                                                      │
│  SALES SUMMARY                                       │
│  Total Sales:          NPR 82,450                    │
│  Transactions:         52                            │
│  Average Transaction:  NPR 1,586                     │
│                                                      │
│  PAYMENT METHODS                                     │
│  Cash:    NPR 45,200 (55%)                          │
│  eSewa:   NPR 22,750 (28%)                          │
│  Khalti:  NPR 8,500 (10%)                           │
│  Card:    NPR 6,000 (7%)                            │
│                                                      │
│  PROFIT                                              │
│  Revenue:  NPR 82,450                               │
│  Cost:     NPR 49,470                               │
│  Profit:   NPR 32,980                               │
│  Margin:   40.0% 🟢                                  │
│                                                      │
│  CASHIER PERFORMANCE                                 │
│  Anita:  28 transactions, NPR 45,200, 87% efficiency │
│  Bikash: 24 transactions, NPR 37,250, 82% efficiency │
│                                                      │
│  INVENTORY HIGHLIGHTS                                │
│  Items Received: 150 units (1 purchase order)        │
│  Items Sold: 98 units                                │
│  Low Stock Items: 3 (down from 5 this morning)       │
│  Expiring Products: Chyawanprash promo worked!       │
│    → 6 of 8 units sold, 2 remaining                  │
│                                                      │
│  LOYALTY PROGRAM                                     │
│  New Enrollments: 3                                  │
│  Points Issued: 48,500                              │
│  Points Redeemed: 4,800                             │
│  Active Members: 345                                 │
│                                                      │
│  CUSTOMER LEDGER                                     │
│  New Credit Sales: NPR 15,000                       │
│  Payments Received: NPR 12,000                      │
│  Outstanding: NPR 1,23,500                          │
│                                                      │
│  SUPPLIER LEDGER                                     │
│  New Purchases: NPR 68,000                          │
│  Payments Made: NPR 52,000                          │
│  Outstanding: NPR 93,000                            │
└──────────────────────────────────────────────────────┘
```

---

## 7:00 PM — Automated Reports Go Out

**Feature Used: Scheduled Reports, Automated Alerts**

```
📧 Email to Ram (Owner):
Subject: Daily Sales Report — Himalayan Ayurveda — April 19, 2026
Attachment: Sales_Report_2026-04-19.xlsx

📧 Email to Sita (Manager):
Subject: Inventory Alert — 3 items below reorder level
Body: Ashwagandha Capsules still at 0. Neem Soap at 8. 
      Purchase orders recommended.

📧 Email to Ram (Owner):
Subject: Weekly Profit Summary (Week 16)
Attachment: Profit_Report_Week16.xlsx
```

---

## Weekly: Customer Analytics Review

### Sita Reviews Customer Segments

**Feature Used: Customer Analytics, RFM Segmentation**

```
┌──────────────────────────────────────────────────────┐
│  CUSTOMER SEGMENTS — April 2026                      │
│                                                      │
│  🏆 Champions (frequent, recent, high value): 28     │
│     → Keep rewarding them, ask for referrals         │
│                                                      │
│  💙 Loyal Customers (frequent buyers): 45            │
│     → Upsell premium products                        │
│                                                      │
│  🌱 New Customers (recent first-timers): 32          │
│     → Welcome offers, loyalty enrollment             │
│                                                      │
│  ⚠️ At Risk (were loyal, going inactive): 18         │
│     → Personal outreach, special offers              │
│                                                      │
│  😟 Lost (haven't visited in 3+ months): 24          │
│     → Heavy discounts or accept churn                │
└──────────────────────────────────────────────────────┘
```

**Action**: Sita sends WhatsApp messages to the 18 "At Risk" customers with a special 15% discount code.

---

## Monthly: Stock Valuation & ABC Analysis

### Ram (Owner) Reviews Inventory Investment

**Feature Used: Stock Valuation Report, Inventory Turnover Report**

```
┌──────────────────────────────────────────────────────┐
│  STOCK VALUATION — April 2026                        │
│                                                      │
│  Total Inventory Value:  NPR 8,45,000               │
│                                                      │
│  By Category:                                        │
│  Hair Oils:     NPR 2,80,000 (33%)                  │
│  Powders:       NPR 1,90,000 (22%)                  │
│  Capsules:      NPR 1,45,000 (17%)                  │
│  Special Items: NPR 1,20,000 (14%)                  │
│  Other:         NPR 1,10,000 (13%)                  │
│                                                      │
│  ABC ANALYSIS:                                       │
│  A Items (top revenue): 38 products (20%)            │
│    → Generate 80% revenue — NEVER let these run out  │
│  B Items (moderate):    54 products (28%)            │
│    → Generate 15% revenue — keep moderate stock      │
│  C Items (low movers):  108 products (52%)           │
│    → Generate 5% revenue — reduce orders, consider   │
│      discontinuing slowest 20                        │
└──────────────────────────────────────────────────────┘
```

**Action**: Ram decides to discontinue 10 slow-moving C items and reinvest that capital in A items.

---

## The Result: A Transformed Business

### Before RishiPath (6 months ago)
- Monthly revenue: NPR 18,00,000
- Inventory shrinkage: ~20%
- Customer retention: ~35%
- Expired products wasted: ~6%
- Time to close books: 3 days
- Business decisions: "I think..."

### After RishiPath (Today)
- Monthly revenue: NPR 24,50,000 (+36%)
- Inventory shrinkage: 4% (80% reduction)
- Customer retention: 62% (+27 points)
- Expired products wasted: <1%
- Time to close books: 5 minutes (auto-generated)
- Business decisions: "The data shows..."

---

*This walkthrough demonstrates real features of the production RishiPath POS system.*

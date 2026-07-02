# RishiPath POS - Complete Feature Guide

> **Every feature explained in plain language for non-technical readers**

---

## How to Read This Guide

Each feature is explained with:
- **What it does** — simple explanation
- **Why it matters** — the business benefit
- **How it works** — step-by-step, no jargon

---

## Table of Contents

1. [Organization & Multi-Tenant Management](#1-organization--multi-tenant-management)
2. [Store Management](#2-store-management)
3. [User & Role Management](#3-user--role-management)
4. [Product Categories](#4-product-categories)
5. [Product Catalog](#5-product-catalog)
6. [Product Variants & Pricing](#6-product-variants--pricing)
7. [Barcode System](#7-barcode-system)
8. [Supplier Management](#8-supplier-management)
9. [Purchase Orders](#9-purchase-orders)
10. [Purchase Returns](#10-purchase-returns)
11. [Inventory Management](#11-inventory-management)
12. [Batch & Expiry Tracking](#12-batch--expiry-tracking)
13. [Stock Adjustments](#13-stock-adjustments)
14. [Stock Transfers](#14-stock-transfers)
15. [Point of Sale (POS)](#15-point-of-sale-pos)
16. [Payment Processing](#16-payment-processing)
17. [Split Payments](#17-split-payments)
18. [Session Management (Park & Resume)](#18-session-management-park--resume)
19. [Receipts & WhatsApp](#19-receipts--whatsapp)
20. [Customer Management](#20-customer-management)
21. [Customer Ledger](#21-customer-ledger)
22. [Loyalty Program](#22-loyalty-program)
23. [Loyalty Tiers](#23-loyalty-tiers)
24. [Rewards Catalog](#24-rewards-catalog)
25. [Supplier Ledger](#25-supplier-ledger)
26. [Sales Reports](#26-sales-reports)
27. [Profit Reports](#27-profit-reports)
28. [Inventory Reports](#28-inventory-reports)
29. [Customer Analytics](#29-customer-analytics)
30. [Cashier Performance Reports](#30-cashier-performance-reports)
31. [Stock Valuation Reports](#31-stock-valuation-reports)
32. [Automated Alerts](#32-automated-alerts)
33. [Scheduled Reports](#33-scheduled-reports)
34. [B2B Retail Partner Management](#34-b2b-retail-partner-management)
35. [Bulk Orders & Invoicing](#35-bulk-orders--invoicing)
36. [Field Visit Tracking](#36-field-visit-tracking)
37. [Invoicing System](#37-invoicing-system)
38. [Feedback & Issue Tracking](#38-feedback--issue-tracking)
39. [Dashboard & Widgets](#39-dashboard--widgets)
40. [Multi-Currency & Tax Support](#40-multi-currency--tax-support)
41. [Offline-First Architecture](#41-offline-first-architecture)
42. [Multi-Language Support](#42-multi-language-support)

---

## 1. Organization & Multi-Tenant Management

### What It Does
Think of an "Organization" as the top-level company or brand. RishiPath allows multiple organizations to run on the same system, each completely separate — like having different companies sharing the same building but with locked doors between them.

### Why It Matters
- **Franchise chains** can manage all stores under one umbrella
- **Ashram networks** can have each location operate independently but report centrally
- A single system can serve **multiple brands** without any data mixing

### How It Works
1. Create your Organization with your business name and legal details
2. Set your country (India or Nepal) — this automatically configures currency (₹ or रू), tax rules, and language
3. All your stores, products, customers, and data exist only within your organization
4. Super Admins can switch between organizations instantly

---

## 2. Store Management

### What It Does
Each physical shop or retail location is registered as a "Store" in the system. Every store gets its own inventory, POS terminals, and staff.

### Why It Matters
- Track **which store sold what** for accurate performance comparisons
- Each store maintains **its own stock levels** — no confusion about what's available where
- **Tax and license numbers** stored per location for legal compliance

### How It Works
1. Add a store with its full address, phone, email
2. Enter the store's tax registration and license numbers
3. Set up POS terminals (billing counters) at each store
4. Assign staff members to specific stores
5. The system automatically tracks everything per store

---

## 3. User & Role Management

### What It Does
Control who can access what in the system. Every person using the system gets a user account with a specific role that determines their permissions.

### Why It Matters
- **Cashiers** can only bill and view products — they can't change prices or view financial reports
- **Managers** can view reports and manage inventory but can't change system settings
- **Admins** have full control — prevents unauthorized changes
- Full **audit trail** of who did what

### How It Works

| Role | Can Do | Cannot Do |
|------|--------|-----------|
| **Super Admin** | Everything (70+ permissions) | Nothing restricted |
| **Manager** | Reports, inventory, customers, purchases (44 permissions) | System settings, role management |
| **Cashier** | POS billing, basic customer search (12 permissions) | Reports, inventory, pricing changes |
| **Inventory Clerk** | Stock management, purchases (19 permissions) | POS billing, financial reports |

Each user also gets:
- A **PIN code** for quick POS login
- Assignment to **specific stores**
- Activity tracking (last login time)

---

## 4. Product Categories

### What It Does
Organize your products into logical groups, like folders on a computer. Categories can be nested (a category inside a category).

### Why It Matters
- Makes it **easy to find products** during billing
- **Reports by category** show which product types are most profitable
- Customers browsing your system see products organized logically

### How It Works
1. Create main categories: "Hair Oils", "Powders", "Capsules", "Special Products"
2. Create sub-categories if needed: "Hair Oils → Amla Oils", "Hair Oils → Coconut Oils"
3. Names available in **English, Hindi, and Nepali**
4. Each category can specify a default product type (Oil, Powder, etc.)

---

## 5. Product Catalog

### What It Does
The heart of your inventory — every product your store sells is registered here with complete details.

### Why It Matters
- **Standardized product information** prevents mistakes during billing
- **Multi-language names** help staff and customers who speak different languages
- **Tracking requirements** (batch, expiry) ensure medicine safety compliance
- **Complete product profiles** with images, ingredients, and usage instructions

### How It Works

For each product you enter:

| Field | Example | Why |
|-------|---------|-----|
| Product Name | Amla Hair Oil | Main display name |
| Sanskrit Name | आमलकी तैलम् | Traditional reference |
| Hindi Name | आंवला तेल | Hindi-speaking staff |
| Nepali Name | अमला तेल | Nepal stores |
| Product Type | Tailam (Oil) | Categorization |
| Unit Type | Volume (ml) | How it's measured |
| Tax Category | Standard (12% GST) | Tax calculation |
| Requires Batch | Yes | Medicine tracking |
| Requires Expiry | Yes | Safety compliance |
| Shelf Life | 24 months | Expiry calculation |
| Ingredients | Amla, Coconut Oil... | Customer information |
| Images | Up to 3 photos | Visual identification |

The system generates a **smart SKU** automatically: `AYU-OIL-AML` (Ayurvedic - Oil - Amla)

---

## 6. Product Variants & Pricing

### What It Does
One product can come in multiple sizes (variants). Each size has its own price, barcode, and stock level. Pricing automatically adapts based on whether you're in India or Nepal.

### Why It Matters
- **Amla Oil 100ml and Amla Oil 500ml** are tracked as separate items with separate stock
- **India prices in ₹** and **Nepal prices in रू** are set once and the system uses the right one automatically
- Individual stores can **override prices** for local promotions or market conditions

### How It Works

| Variant | India MRP | Nepal Price | Cost Price | Barcode |
|---------|-----------|-------------|------------|---------|
| Amla Oil 100ml | ₹150 | रू200 | ₹90 | RSH-42-A7B3 |
| Amla Oil 200ml | ₹280 | रू375 | ₹170 | RSH-42-C4D5 |
| Amla Oil 500ml | ₹650 | रू870 | ₹400 | RSH-42-E6F7 |

**Store-Specific Pricing**: Your Kathmandu store can sell the 100ml at रू180 while your Pokhara store sells at रू210.

---

## 7. Barcode System

### What It Does
Generate and print barcodes for every product variant. Scan barcodes at the POS for instant product identification.

### Why It Matters
- **Speed at checkout** — scan instead of searching by name
- **Eliminate billing errors** — the right product, right price, every time
- **Professional appearance** — printed barcode labels for all products

### How It Works
1. System automatically generates unique barcodes for each variant
2. Print barcode labels in bulk for new stock
3. Supported formats: Code128, EAN13, Code39, QR Code
4. At POS: scan barcode → product appears instantly with correct price

---

## 8. Supplier Management

### What It Does
Keep a complete directory of all your suppliers with contact information, tax details, and purchase history.

### Why It Matters
- **Track who supplies what** — never lose supplier contact info
- **Monitor payment obligations** — see how much you owe each supplier at a glance
- **Purchase history** — know your buying patterns and negotiate better terms

### How It Works
1. Register supplier with name, contact person, phone, email, address
2. Add tax registration number for invoice compliance
3. System auto-generates a supplier code: `SUP-20260419-0001`
4. The supplier's **running balance** updates automatically with every purchase and payment
5. View complete ledger: what you bought, what you paid, what you owe

---

## 9. Purchase Orders

### What It Does
Create formal orders for stock from your suppliers. Track the entire lifecycle from ordering to receiving to paying.

### Why It Matters
- **Know exactly what you ordered** — no more verbal orders with no record
- **Track deliveries** — see what's been received vs. what's still pending
- **Financial clarity** — know your exact cost for every item on your shelf
- **Automatic stock updates** — receiving a purchase immediately updates your inventory

### How It Works

**The Purchase Lifecycle:**

```
📋 Draft  →  📦 Ordered  →  ✅ Received  →  💰 Paid
```

1. **Create Order**: Select supplier, add products with quantities and prices
2. **Submit**: Send order to supplier (status: Ordered)
3. **Receive**: When goods arrive, mark as received
4. **Automatic Actions on Receiving**:
   - Product batches created (with batch number, expiry date)
   - Stock levels increase at the receiving store
   - Inventory movement logged for audit trail
   - Supplier ledger updated (you now owe them money)
5. **Pay**: Record partial or full payment to supplier

---

## 10. Purchase Returns

### What It Does
Return damaged, expired, or incorrect products back to suppliers with full documentation.

### Why It Matters
- **Recover money** for defective products
- **Legal trail** — documented returns protect your business
- **Stock accuracy** — returned items are automatically removed from your inventory

### How It Works
1. Select the original purchase and the specific batch
2. Enter quantity returned and reason (damaged, wrong product, expired, etc.)
3. System auto-generates a return number: `RET-20260419-0001`
4. Stock levels decrease, supplier balance adjusts accordingly
5. Return requires approval from authorized staff

---

## 11. Inventory Management

### What It Does
Real-time tracking of every product's stock level across all your stores. Know exactly how much of everything you have, where it is, and when to reorder.

### Why It Matters
- **Never run out of bestsellers** — automatic low-stock alerts
- **Reduce dead stock** — identify slow-moving items before they expire
- **Prevent theft and shrinkage** — complete audit trail of every stock movement
- **Make smarter purchases** — buy what you need, not what you guess

### How It Works
Every product variant at every store has a stock level that updates automatically:

| Event | Stock Change | Tracked By |
|-------|-------------|------------|
| Purchase received | +50 units | Purchase Order |
| Customer sale | -3 units | POS Transaction |
| Customer return | +1 unit | Return Process |
| Damaged item | -1 unit | Stock Adjustment |
| Transfer to another store | -20 units | Stock Transfer |
| Transfer from another store | +20 units | Stock Transfer |
| Physical recount correction | ±X units | Stock Adjustment |

**Every single movement is logged** with who did it, when, why, and the before/after quantities.

---

## 12. Batch & Expiry Tracking

### What It Does
For medicines and health products, every shipment received is tracked as a separate "batch" with its own manufacture date, expiry date, and remaining quantity. When you sell products, the system automatically sells the **oldest batch first** (FIFO — First In, First Out).

### Why It Matters
- **Patient safety** — expired medicines never reach customers
- **Legal compliance** — drug regulations require batch traceability
- **Zero waste** — FIFO ensures oldest stock sells before it expires
- **Recall capability** — if a batch has a problem, trace exactly who bought it

### How It Works
1. When a purchase is received, a batch is created:
   - Batch Number: `BATCH-2026-04-001`
   - Manufactured: January 2026
   - Expires: January 2028
   - Quantity: 500 units
2. The system color-codes batches:
   - 🟢 **Green**: More than 30 days until expiry — safe
   - 🟡 **Yellow**: Less than 30 days — sell quickly
   - 🔴 **Red**: Expired — do not sell
3. At POS, the system **automatically picks the oldest batch** — staff don't need to think about it
4. If a batch is recalled, you can see exactly how many units were sold and to which customers

---

## 13. Stock Adjustments

### What It Does
Manually increase or decrease stock levels with a documented reason. Used for physical inventory counts, damage, theft, or corrections.

### Why It Matters
- **Match digital records to reality** after physical stock counts
- **Document losses** from damage, theft, or expired products
- **Audit trail** — every adjustment is logged with who, when, and why

### How It Works
1. Select product and store
2. Choose: Increase or Decrease
3. Enter quantity and reason:
   - **Damage**: Products damaged in storage
   - **Theft**: Identified missing stock
   - **Recount**: Physical count differs from system
   - **Error Correction**: Previous entry was wrong
   - **Return to Stock**: Customer returned item
4. System updates stock level and logs the movement
5. View 30-day adjustment history

---

## 14. Stock Transfers

### What It Does
Move inventory from one store to another. If your Kathmandu store has excess Amla Oil but your Delhi store is running low, transfer stock between them.

### Why It Matters
- **Balance inventory** across locations without new purchases
- **Reduce expiry waste** — move slow sellers to faster-selling stores
- **Cost tracking** — transfers maintain the cost price for accurate profit calculations

### How It Works
1. Select source store and destination store
2. Choose products and quantities to transfer
3. Confirm transfer
4. Source store stock decreases, destination store stock increases
5. Both movements are logged in the audit trail

---

## 15. Point of Sale (POS)

### What It Does
The main billing screen where cashiers create sales. Search for products, build a cart, process payment, and generate receipts — all in one streamlined interface.

### Why It Matters
- **Fast checkout** — under 30 seconds for a typical transaction
- **Error-free billing** — system calculates prices, taxes, and change automatically
- **Customer experience** — professional receipts, loyalty points, WhatsApp delivery
- **Works offline** — never lose a sale due to internet issues

### Key Features
1. **Smart Search**: Find products by name, SKU, barcode, or even Sanskrit name
2. **Live Stock Display**: See how many units are available before adding to cart
3. **Multi-Session**: Serve multiple customers simultaneously (park and switch)
4. **Automatic Tax**: GST (India) or VAT (Nepal) calculated automatically
5. **Loyalty Integration**: See customer points, apply rewards at checkout
6. **Keyboard Shortcuts**: Fast operation for experienced cashiers

---

## 16. Payment Processing

### What It Does
Accept payments through multiple methods — cash, UPI, card, and digital wallets.

### Why It Matters
- **Never turn away a customer** — accept whatever payment method they prefer
- **Accurate cash management** — system calculates exact change
- **Digital payment tracking** — every UPI/card transaction has a reference number

### Supported Methods

| Method | Details | Markets |
|--------|---------|---------|
| 💵 **Cash** | Enter amount received, system calculates change | India & Nepal |
| 📱 **UPI** | Auto-fills amount, enter reference number | India |
| 💳 **Card** | Record card type and last 4 digits | India & Nepal |
| 📱 **eSewa** | Nepal's leading digital wallet | Nepal |
| 📱 **Khalti** | Nepal's popular payment app | Nepal |
| 📱 **Razorpay** | India's payment gateway (planned) | India |

---

## 17. Split Payments

### What It Does
A customer can pay using multiple methods in one transaction. For example: ₹500 cash + ₹200 UPI = ₹700 total.

### Why It Matters
- **Flexibility** — customers often want to split between cash and digital
- **Accurate tracking** — each payment method is recorded separately
- **No forced choices** — accept whatever combination the customer prefers

### How It Works
1. Total bill: ₹700
2. Customer pays ₹500 cash
3. Remaining ₹200 via UPI
4. Both payments recorded with their own reference numbers
5. Receipt shows the payment breakdown

---

## 18. Session Management (Park & Resume)

### What It Does
Pause a sale in progress and start a new one. Come back to the paused sale later — everything is exactly as you left it.

### Why It Matters
- **Real-world scenarios**: Customer forgot their wallet, wants to get one more item, or needs to check with someone
- **Busy checkout lines**: Start serving the next customer while the first one is still deciding
- **No lost work**: Parked carts are saved with all items, quantities, and customer info

### How It Works
1. You're billing Customer A — cart has 5 items
2. Customer A says "I'll be right back, let me get one more thing"
3. Click **Park Sale** — the cart is saved with a name
4. Start fresh for Customer B — bill them normally
5. Customer A returns — click **Resume** — their cart appears exactly as before
6. Complete Customer A's sale

---

## 19. Receipts & WhatsApp

### What It Does
Generate professional receipts and send them instantly via WhatsApp. Customers get a digital copy of their purchase on their phone.

### Why It Matters
- **Professional image** — branded receipts build trust
- **Environmental** — reduce paper usage with digital receipts
- **Customer convenience** — receipt always available on their phone
- **Marketing channel** — stay connected with customers via WhatsApp

### How It Works
1. Sale is completed
2. Receipt is auto-generated with unique number: `RSH-20260419-001`
3. Options:
   - 🖨️ **Print** — thermal printer receipt
   - 📱 **WhatsApp** — sent to customer's phone number instantly
   - Both — print AND WhatsApp
4. Receipt includes: store details, items purchased, quantities, prices, tax breakdown, total, payment method, loyalty points earned

---

## 20. Customer Management

### What It Does
Build a database of your customers with purchase history, preferences, and contact information.

### Why It Matters
- **Know your customers** — who buys what, how often, how much they spend
- **Personalized service** — greet returning customers by name, suggest their usual products
- **Marketing** — send birthday greetings, promotions, new product announcements
- **Credit management** — track who owes you money

### How It Works
1. Create customer (or quick-create at POS): Name + Phone
2. System auto-generates code: `CUST-20260419-0001`
3. With each purchase, the system automatically tracks:
   - Total number of purchases
   - Total amount spent
   - Loyalty points balance
   - Current loyalty tier
4. Search customers by name, phone, or customer code
5. View complete purchase history

---

## 21. Customer Ledger

### What It Does
Track outstanding payments from customers. When a customer buys on credit (pay later), the system maintains a running balance of what they owe.

### Why It Matters
- **No more guessing** who owes you how much
- **Professional statements** — generate customer account statements
- **Payment allocation** — when a customer pays, the oldest dues are settled first (FIFO)
- **Reduce bad debt** — clear visibility into aging receivables

### How It Works
1. Customer buys ₹5,000 worth on credit → Debit entry created
2. Customer pays ₹2,000 → Credit entry created
3. Balance: ₹3,000 outstanding
4. Next visit: customer buys ₹1,000 more on credit
5. Balance: ₹4,000 outstanding
6. Generate statement showing all transactions for any date range
7. Export to Excel for accounting

---

## 22. Loyalty Program

### What It Does
A points-based loyalty system that rewards customers for shopping with you. Customers earn points on every purchase and redeem them for discounts, free products, or cashback.

### Why It Matters
- **Increase repeat visits** — loyalty programs boost return rates by 25-40%
- **Higher spending** — customers spend more when earning points
- **Customer data** — enrolled customers provide valuable purchase insights
- **Competitive advantage** — most Ayurvedic stores have no loyalty program

### How It Works
1. Customer enrolls (free) → Receives **50 welcome points**
2. Every purchase earns points: **₹1 spent = 1 point** (multiplied by tier)
3. Points accumulate → Customer progresses through tiers
4. Points can be redeemed from the Rewards Catalog
5. Points expire after **1 year** (encourages regular use)
6. Birthday bonus points awarded automatically

---

## 23. Loyalty Tiers

### What It Does
Four progressive levels that give customers better rewards as they shop more. Like airline frequent flyer programs — the more you fly (shop), the better your perks.

### Why It Matters
- **Motivates spending** — customers actively try to reach the next tier
- **Perceived exclusivity** — Gold and Platinum tiers feel special
- **Automatic progression** — the system handles tier upgrades, no manual work

### The Four Tiers

| Tier | Points Needed | Multiplier | Discount | Birthday Bonus |
|------|--------------|------------|----------|----------------|
| 🥉 **Bronze** | 0 - 999 | 1.0× | 0% | 100 points |
| 🥈 **Silver** | 1,000 - 4,999 | 1.25× | 5% | 200 points |
| 🥇 **Gold** | 5,000 - 14,999 | 1.5× | 10% | 300 points |
| 💎 **Platinum** | 15,000+ | 2.0× | 15% | 500 points |

**Example**: A Platinum customer spends ₹1,000 → earns 2,000 points (₹1,000 × 2.0 multiplier) instead of the 1,000 a Bronze customer would earn.

---

## 24. Rewards Catalog

### What It Does
A menu of rewards that customers can redeem using their loyalty points. Create any combination of discounts, free products, or cashback offers.

### Why It Matters
- **Tangible motivation** — customers see real rewards they want
- **Flexible options** — offer what your customers actually value
- **Controlled costs** — you set the points required, so you control redemption rates
- **Tier restrictions** — exclusive rewards for top-tier customers create aspiration

### Types of Rewards

| Type | Example | Points Required |
|------|---------|----------------|
| **Discount** | ₹50 off next purchase | 200 points |
| **Free Product** | Free Amla Oil 100ml | 500 points |
| **Cashback** | ₹100 credited to account | 400 points |

---

## 25. Supplier Ledger

### What It Does
A complete financial record of your transactions with each supplier — what you purchased, what you returned, what you paid, and what you still owe.

### Why It Matters
- **Never overpay** a supplier — clear record of every transaction
- **Payment planning** — see all outstanding balances at a glance
- **Dispute resolution** — documented history resolves disagreements
- **Audit compliance** — complete paper trail for tax authorities

### How It Works
1. Purchase received → Debit: ₹50,000 (you owe supplier)
2. Purchase return → Credit: ₹5,000 (supplier owes you back)
3. Payment made → Credit: ₹20,000 (you paid them)
4. Current balance: ₹25,000 outstanding
5. View full ledger with date filters, export to Excel

---

## 26. Sales Reports

### What It Does
Analyze your sales performance over any time period. See how much you sold, through which payment methods, and which products are bestsellers.

### Why It Matters
- **Track growth** — compare this month to last month
- **Payment insights** — see if cash vs. digital payment mix is changing
- **Product decisions** — identify bestsellers and underperformers
- **Store comparisons** — which store is performing best

### What You See
- Total sales amount and transaction count
- Sales by payment method (Cash, UPI, Card, etc.)
- Daily sales breakdown
- Top-selling products
- Filter by date range and store
- Export everything to Excel

---

## 27. Profit Reports

### What It Does
Go beyond sales to see actual **profitability**. For every product and category, see the revenue, cost, profit, and margin percentage.

### Why It Matters
- **Revenue doesn't equal profit** — a high-selling product with thin margins may be less valuable than a lower-selling product with high margins
- **Category insights** — which product categories make you the most money
- **Pricing decisions** — identify where to increase prices or find cheaper suppliers
- **Color-coded margins**: 🟢 Green (30%+), 🟡 Yellow (15-29%), 🔴 Red (<15%)

### What You See
- Total Revenue, Total Cost, Total Profit, Overall Margin %
- Profit by category with visual margin indicators
- Top 10 most profitable products
- Bottom 10 least profitable products
- Daily profit trends
- Everything exportable to Excel

---

## 28. Inventory Reports

### What It Does
Advanced analysis of your inventory — which products move fast, which are sitting idle, and how to optimize your stock investment.

### Why It Matters
- **ABC Analysis** — categorize products by importance:
  - **A Items** (top 20%): Generate 80% of your revenue — never let these run out
  - **B Items** (middle 30%): Generate 15% of revenue — keep moderate stock
  - **C Items** (bottom 50%): Generate only 5% of revenue — reduce orders
- **Turnover Rate** — how fast each product sells through its stock
- **Dead stock detection** — products that haven't moved in months

### What You See
- Inventory turnover rate for each product
- Average days to sell through stock
- Fast-moving vs. slow-moving classification
- Stock value breakdown by category
- Reorder recommendations

---

## 29. Customer Analytics

### What It Does
Understand your customer base with data-driven segmentation. The system automatically classifies customers into 9 segments based on their shopping behavior.

### Why It Matters
- **RFM Analysis** (Recency, Frequency, Monetary) tells you:
  - How **recently** a customer shopped
  - How **frequently** they visit
  - How **much** they spend
- **9 Customer Segments** for targeted actions:

| Segment | Description | Action |
|---------|-------------|--------|
| **Champions** | Recent, frequent, high spenders | Reward them, ask for referrals |
| **Loyal Customers** | Frequent shoppers | Upsell premium products |
| **Potential Loyalists** | Recent, moderate frequency | Nurture with loyalty program |
| **New Customers** | Just started shopping | Welcome offers |
| **Promising** | Recent, not yet frequent | Encourage second visit |
| **Need Attention** | Were good, slowing down | Re-engagement offers |
| **About to Sleep** | Haven't visited in a while | Win-back campaign |
| **At Risk** | Were loyal, going inactive | Urgent personal outreach |
| **Lost** | Haven't shopped in months | Heavy discounts or write off |

---

## 30. Cashier Performance Reports

### What It Does
Measure how each cashier is performing — transaction count, revenue generated, efficiency, and variance analysis.

### Why It Matters
- **Identify top performers** for recognition and reward
- **Spot training needs** — slow cashiers or high-variance cashiers need attention
- **Scheduling optimization** — put your best cashiers during peak hours
- **Fraud prevention** — unusual variance patterns may indicate problems

### What You See
- Transactions per cashier
- Revenue per cashier
- Revenue per hour (efficiency metric)
- Efficiency score (0-100%)
- Hourly breakdown of activity
- Variance analysis (expected vs. actual)

---

## 31. Stock Valuation Reports

### What It Does
Calculate the total monetary value of all inventory in your stores.

### Why It Matters
- **Know your investment** — how much money is tied up in stock
- **Insurance** — accurate valuation for insurance claims
- **Financial reporting** — required for accounting and tax
- **Trend tracking** — is inventory value growing or shrinking?

### What You See
- Total stock value across all stores
- Value breakdown by category
- Value breakdown by supplier
- Batch-level valuation details
- Per-store inventory values

---

## 32. Automated Alerts

### What It Does
Set up rules that automatically notify you when important events happen. The system watches your business 24/7 and alerts you when action is needed.

### Why It Matters
- **Low stock alerts** — reorder before you run out
- **High-value sale alerts** — stay informed about large transactions
- **Cashier variance** — catch irregularities early
- **Inventory discrepancies** — know when counts don't match
- **Sales targets** — celebrate when goals are reached

### Alert Types

| Alert | Trigger | Who Gets It |
|-------|---------|-------------|
| **Low Stock** | Product below reorder level | Manager, Inventory Clerk |
| **High-Value Sale** | Sale above ₹X threshold | Owner, Manager |
| **Cashier Variance** | Cash drawer doesn't match | Manager, Admin |
| **Inventory Discrepancy** | System vs. physical mismatch | Inventory Clerk, Manager |
| **Sales Target** | Daily/monthly target reached | Team |

### How It Works
1. Create an alert rule (e.g., "Alert me when any product goes below 10 units")
2. Set frequency: Immediate, Hourly, or Daily summary
3. Choose recipients: specific users or roles
4. System automatically checks conditions and sends notifications

---

## 33. Scheduled Reports

### What It Does
Automatically generate and email reports on a schedule. No one needs to remember to pull reports — they arrive in your inbox.

### Why It Matters
- **Consistency** — same reports, same time, every day/week/month
- **Time savings** — no manual report generation
- **Team alignment** — everyone gets the same data simultaneously
- **Decision support** — regular data delivery enables proactive management

### How It Works
1. Choose a report type (Sales, Profit, Inventory, etc.)
2. Set schedule: Daily, Weekly, Monthly, or custom (cron)
3. Add recipient email addresses
4. Choose format (Excel)
5. Reports are automatically generated and emailed on schedule

---

## 34. B2B Retail Partner Management

### What It Does
If you also supply products to other retail stores (wholesale/distribution), this module tracks your retail partners with detailed profiles.

### Why It Matters
- **Organized partner database** — all retail store info in one place
- **Location tracking** — Google Maps integration for delivery planning
- **Visit history** — when did your field team last visit each store
- **Performance tracking** — which stores order the most

### How It Works
1. Register a retail partner: store name, contact person, address, photos
2. Add Google Maps location link for navigation
3. Track all interactions: visits, orders, inquiries
4. Assign field representatives to stores
5. Monitor store status (Active, Inactive, Prospect)

---

## 35. Bulk Orders & Invoicing

### What It Does
Handle large orders from retail partners and businesses. Minimum order quantity: 10 units per product.

### Why It Matters
- **B2B revenue stream** — serve wholesale customers alongside retail
- **Formal process** — quotations, approvals, invoicing
- **Order tracking** — from inquiry to delivery to payment

### How It Works
1. Retail partner submits inquiry (products, quantities, budget)
2. Admin reviews and creates quotation (Proforma Invoice)
3. Partner approves → Tax Invoice generated
4. Products shipped, payment tracked
5. If issues → Credit Note issued

---

## 36. Field Visit Tracking

### What It Does
When your team visits retail partners in the field, they can log detailed visit reports including store assessments, competitor intelligence, and next actions.

### Why It Matters
- **Accountability** — know where your field team is and what they're doing
- **Store intelligence** — track stock availability, display quality, competition
- **Action planning** — issues found during visits get tracked and resolved
- **Relationship management** — scheduled follow-up visits

### What Gets Recorded

| Assessment | Details |
|-----------|---------|
| Stock Available? | Yes/No |
| Good Display? | Yes/No |
| Store Clean? | Yes/No |
| Staff Trained? | Yes/No |
| Competition Present? | Yes/No + Details |
| Store Rating | 1-5 Stars |
| Footfall Rating | Low/Medium/High |
| Order Placed? | Yes/No + Amount |
| Issues Found | Free text + Photos |
| Next Visit Date | Scheduled follow-up |

---

## 37. Invoicing System

### What It Does
Generate professional invoices for any type of transaction — retail sales, wholesale orders, quotations, or credit notes.

### Why It Matters
- **Professional documents** — branded invoices build trust
- **Multiple types** — Invoice, Quotation, Proforma, Credit Note
- **Tax compliance** — GST/VAT details included automatically
- **Payment tracking** — amount paid, amount due, due dates

### Invoice Types

| Type | Used For |
|------|----------|
| **Invoice** | Standard tax invoice for completed sales |
| **Quotation** | Price quote for potential orders |
| **Proforma** | Pre-payment invoice for bulk orders |
| **Credit Note** | Returns or adjustments to issued invoices |

---

## 38. Feedback & Issue Tracking

### What It Does
A built-in system for tracking customer feedback, complaints, and internal issues.

### Why It Matters
- **Never lose feedback** — every issue is documented and assigned
- **Priority management** — urgent issues get attention first
- **Resolution tracking** — see how quickly issues are resolved
- **Customer satisfaction** — follow up on resolved issues

---

## 39. Dashboard & Widgets

### What It Does
Your home screen showing the most important business metrics at a glance. Real-time data, no manual refreshing needed.

### Why It Matters
- **Morning briefing** — see yesterday's and today's numbers in seconds
- **Quick alerts** — low stock warnings right on the dashboard
- **Trends** — visual charts showing sales and profit trends
- **No report-digging** — the most important metrics come to you

### Dashboard Widgets

| Widget | Shows |
|--------|-------|
| **POS Stats** | Today's sales, month's total, product count, customer count |
| **Sales Trend** | Daily sales chart for the last 30 days |
| **Profit Trend** | Daily profit chart |
| **Inventory Overview** | Total stock value, product count |
| **Low Stock Alerts** | Products below reorder level |
| **Loyalty Stats** | Active members, points issued, redemptions |
| **Category Distribution** | Sales split across categories (pie chart) |
| **Accounts Overview** | Total receivables and payables |

---

## 40. Multi-Currency & Tax Support

### What It Does
Automatic currency and tax handling based on your organization's country. No manual calculations needed.

### Why It Matters
- **India operations**: Prices in ₹ (INR), GST tax (5%, 12%, or 18%)
- **Nepal operations**: Prices in रू (NPR), VAT (13%)
- **Cross-border chains**: One product catalog with correct pricing for each country
- **Tax compliance**: Correct tax rates applied automatically on every sale

---

## 41. Offline-First Architecture

### What It Does
The entire POS system works without internet. Sales, inventory, and customer management continue normally even during internet outages. When connectivity returns, data syncs automatically.

### Why It Matters
- **Zero lost sales** — internet goes down? Business continues normally
- **Rural/tier-2 reliability** — perfect for areas with unstable connectivity
- **Speed** — local database is faster than cloud queries
- **Data safety** — local backup ensures no data loss

### How It Works
1. Each POS machine has a complete local database
2. All operations (sales, inventory, customers) work on local data
3. When internet is available, changes sync to the central cloud
4. Cloud dashboard shows aggregated data from all stores
5. Conflict resolution ensures data integrity

---

## 42. Multi-Language Support

### What It Does
Product names stored in four languages: English, Sanskrit, Hindi, and Nepali. Staff can search for products in any language.

### Why It Matters
- **Ayurvedic terminology** — Sanskrit names are the official nomenclature for many products
- **Regional staff** — Hindi-speaking staff in India, Nepali-speaking in Nepal
- **Customer communication** — use the language your customer understands
- **Professional labels** — barcode labels can include multiple language names

---

## Summary: Feature Count

| Category | Count |
|----------|-------|
| Core Modules | 12 |
| Admin Panels | 24 |
| Custom Pages | 16 |
| Dashboard Widgets | 8 |
| Business Reports | 8+ |
| Alert Types | 5 |
| Payment Methods | 6 |
| Loyalty Tiers | 4 |
| User Roles | 4 |
| Permissions | 70+ |
| Database Models | 35 |
| Languages Supported | 4 |
| Countries Supported | 2 (India, Nepal) |

---

*This guide covers every feature currently available in the RishiPath POS production system.*

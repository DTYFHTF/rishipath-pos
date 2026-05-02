# TC-06 — Customers & Loyalty Program

**Priority**: P1–P2  
**URL**: /admin/customers | /admin/loyalty-program | /admin/loyalty-tiers | /admin/rewards

---

## 1. Customer Management

### 1.1 Create Customer

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-06-01 | Create customer — required fields only | Logged in as Admin/Manager/Cashier | 1. Customers → New 2. Enter name "Priya Sharma", phone "9876543210" 3. Save | Customer created. Appears in list | P1 |
| TC-06-02 | Create customer — all fields | — | 1. Fill name, phone, email, DOB, address, city, pincode 2. Save | All data saved correctly | P2 |
| TC-06-03 | Create customer — duplicate phone | Customer with this phone exists | 1. Create another customer with same phone | Validation error: phone already exists | P1 |
| TC-06-04 | Create customer — blank name | — | 1. Leave name empty 2. Save | Validation error: name required | P1 |
| TC-06-05 | Create customer — invalid phone | — | 1. Enter "abc123" as phone 2. Save | Validation error: invalid phone number | P2 |
| TC-06-06 | Create customer — with email | — | 1. Enter valid email 2. Save | Email saved | P2 |

### 1.2 Edit Customer

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-06-07 | Edit customer name | Customer exists | 1. Edit → change name 2. Save | Name updated. Historical sales still linked | P2 |
| TC-06-08 | Edit customer phone to existing | Two customers exist | 1. Edit phone to match another customer | Validation error: duplicate phone | P2 |
| TC-06-09 | Edit customer address | Customer exists | 1. Update address 2. Save | Address updated | P3 |

### 1.3 Customer List & Search

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-06-10 | Search by name | Customers exist | 1. Type name in search | Matching customers shown | P2 |
| TC-06-11 | Search by phone | Customers exist | 1. Type phone number | Exact/partial match shown | P1 |
| TC-06-12 | View purchase history | Customer with sales | 1. Open customer 2. View purchases | All past sales listed with dates and amounts | P1 |
| TC-06-13 | View customer ledger | Customer with credit sales | 1. Open customer → Ledger tab | Debit/credit entries with running balance | P1 |
| TC-06-14 | View customer loyalty points | Loyalty customer | 1. Open customer | Current points balance shown | P1 |

---

## 2. Loyalty Program Setup

### 2.1 Program Configuration

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-06-20 | View loyalty program settings | Admin logged in | 1. Go to Loyalty Program | Program details shown (points per ₹, redemption value) | P1 |
| TC-06-21 | Configure points earning rate | Admin | 1. Set "1 point per ₹10 spent" 2. Save | Rate saved | P1 |
| TC-06-22 | Configure redemption rate | Admin | 1. Set "100 points = ₹10 discount" 2. Save | Rate saved | P1 |
| TC-06-23 | Enable/disable loyalty program | Admin | 1. Toggle program active/inactive 2. Save | Program state changes. Points not earned when inactive | P1 |

### 2.2 Loyalty Tiers

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-06-24 | Create loyalty tier — Bronze | Admin | 1. Loyalty → Tiers → New 2. Name "Bronze", min spend ₹0, multiplier 1x 3. Save | Tier created | P1 |
| TC-06-25 | Create loyalty tier — Silver | Admin | 1. Name "Silver", min spend ₹5000, multiplier 1.5x 2. Save | Tier created | P1 |
| TC-06-26 | Create loyalty tier — Gold | Admin | 1. Name "Gold", min spend ₹20000, multiplier 2x 2. Save | Tier created | P1 |
| TC-06-27 | Tier upgrade — automatic | Customer reaches threshold | 1. Customer cumulative spend crosses Silver threshold 2. Check customer tier | Tier upgraded to Silver automatically | P2 |
| TC-06-28 | Delete tier — with customers assigned | Tier has customers | 1. Try to delete | Error or reassign warning | P2 |

---

## 3. Points Earning (at POS)

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-06-30 | Points earned on sale | Loyalty customer, program active | 1. Complete sale for ₹500 with earning rate 1 pt/₹10 | Customer earns 50 points | P1 |
| TC-06-31 | Points not earned — program inactive | Program disabled | 1. Complete sale | No points added | P1 |
| TC-06-32 | Points multiplier applied per tier | Gold customer (2x multiplier) | 1. Complete ₹500 sale | Customer earns 100 points (50 × 2) | P2 |
| TC-06-33 | Points not earned — walk-in | No customer assigned | 1. Complete sale | No loyalty transaction created | P1 |
| TC-06-34 | Points earned reflected on customer | Sale completed | 1. Go to customer profile | Points balance updated | P1 |
| TC-06-35 | Points voided on sale void | Sale voided | 1. Void a sale 2. Check customer points | Points deducted/reversed | P1 |

---

## 4. Points Redemption (at POS)

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-06-40 | Redeem points — partial | Customer has 200 points, 100 pts = ₹10 | 1. Assign customer at POS 2. Redeem 100 points 3. Pay remaining by cash | ₹10 discount applied from points. 100 points deducted | P1 |
| TC-06-41 | Redeem all points | Customer has enough points for full payment | 1. Redeem all points | Sale total covered. No additional payment needed | P2 |
| TC-06-42 | Redeem more points than available | Customer has 50 points | 1. Try to redeem 200 points | Error: insufficient points | P1 |
| TC-06-43 | Redeem points — program inactive | — | 1. Try to redeem when program disabled | Redemption option not shown | P2 |
| TC-06-44 | Points redeemed reflected on customer | After redemption | 1. Check customer profile | Deducted points show in loyalty history | P1 |

---

## 5. Rewards

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-06-50 | Create reward | Admin | 1. Loyalty → Rewards → New 2. Name "Free Shipping", cost 500 pts, type "discount" 3. Save | Reward created | P2 |
| TC-06-51 | Redeem reward | Customer with enough points | 1. At POS or customer profile → Redeem reward | Points deducted. Reward applied | P2 |
| TC-06-52 | Reward expiry | Reward with expiry date | 1. After expiry date, try to redeem | Error: reward expired | P3 |

---

## 6. Customer Ledger

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-06-60 | Credit sale recorded in ledger | Customer with credit sale | 1. Go to Customer Ledger Report 2. Filter by customer | Credit sale shown as debit in ledger | P1 |
| TC-06-61 | Payment recorded in ledger | Payment received from customer | 1. Customer Ledger Report | Payment shown as credit | P1 |
| TC-06-62 | Outstanding balance correct | Multiple transactions | 1. Verify outstanding balance | Balance = sum of debits – credits | P1 |
| TC-06-63 | Export customer ledger | — | 1. Click Export | CSV downloaded with ledger data | P2 |

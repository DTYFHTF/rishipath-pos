# TC-03 — Inventory Management

**Priority**: P1–P2  
**URL**: /admin/inventory | /admin/product-batches | /admin/stock-adjustment | /admin/stock-transfer | /admin/inventory-audit-log

---

## 1. Product Batches

Batches track stock with lot number, expiry date, purchase cost, and FIFO ordering.

### 1.1 Create Batch

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-03-01 | Create batch — all fields | Product variant exists | 1. Go to Inventory → Batches → New 2. Select variant 3. Enter lot "LOT-001", qty 100, purchase cost 40, expiry 2027-01-01 4. Save | Batch created. Appears in batch list. Stock for variant increases by 100 | P1 |
| TC-03-02 | Create batch — without expiry | Variant exists | 1. Create batch without expiry date | Batch created successfully (expiry is optional) | P2 |
| TC-03-03 | Create batch — zero quantity | — | 1. Enter quantity = 0 2. Save | Validation error: quantity must be > 0 | P2 |
| TC-03-04 | Create batch — negative cost | — | 1. Enter purchase cost = -5 2. Save | Validation error: cost must be >= 0 | P2 |
| TC-03-05 | Create batch — duplicate lot number | Batch "LOT-001" exists for same variant | 1. Create another batch "LOT-001" for same variant | Validation error: duplicate lot number | P2 |
| TC-03-06 | Create batch — past expiry | — | 1. Enter expiry date = yesterday 2. Save | Warning shown but allows save OR validation error | P2 |
| TC-03-07 | Batch auto-increments stock | Variant with 0 stock | 1. Create batch qty 50 2. Check Inventory list | Variant shows 50 in stock | P1 |

### 1.2 Batch List & Filters

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-03-08 | View batches for a product | Batches exist | 1. Go to Batches list 2. Filter by product | Only batches for that product shown | P2 |
| TC-03-09 | Expired batch indicator | Batch with past expiry date | 1. Go to Batches list | Expired batches visually highlighted in red/warning | P2 |
| TC-03-10 | Expiring soon indicator | Batch expiring within 30 days | 1. Go to Batches list | Near-expiry batches highlighted in orange/yellow | P2 |
| TC-03-11 | FIFO ordering | Two batches for same variant (different expiry) | 1. Open POS 2. Sell the variant | Older batch (earlier expiry) consumed first | P1 |

---

## 2. Stock Levels (Inventory List)

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-03-20 | View current stock levels | Products with batches | 1. Go to Inventory → Inventory List | Correct stock quantities shown per variant | P1 |
| TC-03-21 | Low stock alert shown | Variant with stock < reorder point | 1. View inventory list | Low stock badge/indicator visible | P2 |
| TC-03-22 | Zero stock shown | Variant with no batches | 1. View inventory list | Shows 0 for that variant | P1 |
| TC-03-23 | Filter by low stock | Mixed stock levels | 1. Enable "Low Stock" filter | Only variants below threshold shown | P2 |
| TC-03-24 | Search by product name | Many products | 1. Type in search box | Instant filter by name | P2 |

---

## 3. Stock Adjustment

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-03-30 | Add stock adjustment (increase) | Variant exists | 1. Go to Stock Adjustment → New 2. Select variant 3. Type "addition", qty 20, reason "Physical count correction" 4. Save | Stock increases by 20. Audit log entry created | P1 |
| TC-03-31 | Remove stock adjustment (decrease) | Variant with 50 stock | 1. New adjustment 2. Type "deduction", qty 15 3. Save | Stock decreases by 15 | P1 |
| TC-03-32 | Adjustment exceeds available stock | Variant with 10 stock | 1. Create deduction for qty 25 | Error: insufficient stock | P1 |
| TC-03-33 | Adjustment without reason | — | 1. Save without entering reason | Validation error: reason required | P2 |
| TC-03-34 | View adjustment history | Adjustments made | 1. Go to Stock Adjustments list | All past adjustments shown with user, date, qty, reason | P2 |
| TC-03-35 | Adjustment appears in audit log | Make adjustment | 1. Go to Inventory Audit Log | Adjustment entry visible | P2 |

---

## 4. Stock Transfer (Between Stores)

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-03-40 | Transfer stock between stores | Two stores with same variant | 1. Inventory → Stock Transfer → New 2. From Store A, To Store B 3. Select variant, qty 10 4. Save | Store A stock -10, Store B stock +10 | P1 |
| TC-03-41 | Transfer — insufficient source stock | Source store has 5 units | 1. Try to transfer 15 units | Error: insufficient stock in source store | P1 |
| TC-03-42 | Transfer same store | — | 1. Set From and To as same store | Validation error | P2 |
| TC-03-43 | Transfer audit trail | Transfer completed | 1. Go to Inventory Audit Log | Transfer entry visible for both source and destination | P2 |

---

## 5. Inventory Audit Log

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-03-50 | Audit log records sale | Complete a POS sale | 1. Go to Inventory Audit Log | Sale stock deduction visible | P2 |
| TC-03-51 | Audit log records batch creation | Create batch | 1. Go to Audit Log | Batch addition visible | P2 |
| TC-03-52 | Audit log records adjustment | Make stock adjustment | 1. Go to Audit Log | Adjustment visible with user and timestamp | P2 |
| TC-03-53 | Filter audit by product | — | 1. Filter by product name | Only entries for that product shown | P3 |
| TC-03-54 | Filter audit by date range | — | 1. Set from/to date | Entries within range shown | P3 |
| TC-03-55 | Filter audit by action type | — | 1. Filter by "sale", "adjustment", "batch" | Correct subset shown | P3 |

---

## 6. Low Stock Alerts

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-03-60 | Dashboard low stock widget | Variant below threshold | 1. Go to Dashboard | Low Stock Alerts widget shows affected variants | P1 |
| TC-03-61 | Alert rule triggers notification | Alert rule configured | 1. Stock falls below threshold 2. Wait for alert processing | Notification sent (email/in-app) as configured | P2 |
| TC-03-62 | Create alert rule | — | 1. Settings → Alert Rules → New 2. Set product, threshold, notification type 3. Save | Alert rule created | P2 |

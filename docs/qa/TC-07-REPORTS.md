# TC-07 — Reports & Analytics

**Priority**: P1–P3  
**URL**: /admin/sales-report | /admin/profit-report | /admin/inventory-turnover-report | /admin/stock-valuation-report | /admin/customer-analytics | /admin/cashier-performance

---

## 1. Sales Report

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-07-01 | Open sales report | Admin/Manager/Accountant logged in | 1. Go to Reports → Sales Report | Report loads. Date filter shown | P1 |
| TC-07-02 | Filter by today | Sales exist today | 1. Set date = Today | Only today's sales shown. Revenue totals correct | P1 |
| TC-07-03 | Filter by date range | Sales in range | 1. Set from/to dates | Sales within range shown | P1 |
| TC-07-04 | Filter by store | Multiple stores | 1. Select store | Only that store's sales shown | P2 |
| TC-07-05 | Filter by cashier | Sales by multiple users | 1. Select cashier | Only that cashier's sales | P2 |
| TC-07-06 | Revenue total is correct | Known sales data | 1. Verify sum of sale amounts | Total matches sum of individual sales | P1 |
| TC-07-07 | Voided sales excluded | Voided sales exist | 1. Check revenue total | Voided sales not counted in revenue | P1 |
| TC-07-08 | Export sales report to CSV | Report open | 1. Click Export CSV | Download starts. File has headers + data | P2 |
| TC-07-09 | Export sales report to PDF | Report open | 1. Click Export PDF | PDF generated with report data | P2 |

---

## 2. Profit Report

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-07-10 | Open profit report | Admin/Accountant | 1. Reports → Profit Report | Report loads | P1 |
| TC-07-11 | Gross profit calculated | Sales with cost data | 1. Check gross profit = Revenue – COGS | Calculation is correct | P1 |
| TC-07-12 | Profit by product | — | 1. View product breakdown | Each product shows revenue, cost, and margin | P2 |
| TC-07-13 | Profit by category | — | 1. Filter/group by category | Category-level margins shown | P2 |
| TC-07-14 | Filter by date range | — | 1. Set date range | Only profit from that period shown | P1 |
| TC-07-15 | Negative margin product flagged | Product sold below cost | 1. Check profit report | Negative margins visually highlighted | P3 |

---

## 3. Inventory Reports

### 3.1 Inventory Turnover Report

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-07-20 | Open turnover report | Products with sales | 1. Reports → Inventory Turnover | Report loads with turnover ratios | P1 |
| TC-07-21 | Fast-moving products identified | Varied sales velocity | 1. Sort by turnover | High-velocity products at top | P2 |
| TC-07-22 | Slow-moving products identified | — | 1. Sort ascending | Low-velocity products at top | P2 |
| TC-07-23 | Filter by date range | — | 1. Set period | Turnover calculated for that period | P2 |

### 3.2 Stock Valuation Report

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-07-24 | Open stock valuation | Products in stock | 1. Reports → Stock Valuation | Report loads with per-product stock value | P1 |
| TC-07-25 | Valuation = qty × cost | Known batch cost | 1. Verify: value = qty × purchase cost | Calculation correct | P1 |
| TC-07-26 | Total stock value shown | — | 1. Check bottom of report | Grand total of all stock value shown | P1 |
| TC-07-27 | Export stock valuation | — | 1. Click Export | CSV with product, qty, cost, total value | P2 |

---

## 4. Customer Analytics Report

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-07-30 | Open customer analytics | Customers with sales | 1. Reports → Customer Analytics | Report loads | P1 |
| TC-07-31 | Top customers by revenue | — | 1. Sort by revenue | Highest-spending customers at top | P2 |
| TC-07-32 | Customer visit frequency | — | 1. View frequency data | No. of visits per customer shown | P2 |
| TC-07-33 | Filter by date range | — | 1. Set date range | Only activity in that period | P2 |
| TC-07-34 | Export customer data | — | 1. Export | CSV with customer metrics | P2 |

---

## 5. Cashier Performance Report

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-07-40 | Open cashier performance | Sales by multiple cashiers | 1. Reports → Cashier Performance | Report loads with per-cashier breakdown | P1 |
| TC-07-41 | Sales count per cashier | — | 1. View report | Each cashier's transaction count shown | P1 |
| TC-07-42 | Revenue per cashier | — | 1. View report | Each cashier's total sales value shown | P1 |
| TC-07-43 | Filter by date | — | 1. Set date range | Report scoped to period | P2 |
| TC-07-44 | Average transaction value | — | 1. Check metric | Avg = total revenue / no. transactions | P3 |

---

## 6. Customer & Supplier Ledger Reports

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-07-50 | Customer Ledger Report | Customers with transactions | 1. Reports → Customer Ledger 2. Select customer | Debit/credit entries with balance shown | P1 |
| TC-07-51 | Supplier Ledger Report | Suppliers with purchases/payments | 1. Reports → Supplier Ledger 2. Select supplier | Debit/credit entries with balance | P1 |
| TC-07-52 | Opening balance shown | Ledger has history | 1. View ledger | Opening balance at top | P2 |
| TC-07-53 | Export ledger | — | 1. Click Export | CSV downloaded | P2 |

---

## 7. Dashboard Widgets

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-07-60 | Dashboard loads without errors | Logged in | 1. Go to /admin | All widgets load. No 500 errors | P1 |
| TC-07-61 | Sales trend chart | Sales data exists | 1. View dashboard | Sales trend chart renders with data | P1 |
| TC-07-62 | POS stats widget | Sales today | 1. View dashboard | Today's sales count and revenue shown | P1 |
| TC-07-63 | Inventory overview widget | Products with stock | 1. View dashboard | Total products, low stock count shown | P1 |
| TC-07-64 | Low stock alerts widget | Variants below threshold | 1. View dashboard | Products needing restock listed | P1 |
| TC-07-65 | Loyalty stats widget | Loyalty customers | 1. View dashboard | Active members, points issued shown | P2 |
| TC-07-66 | Profit trend chart | Sales + cost data | 1. View dashboard | Profit chart renders correctly | P2 |

---

## 8. Scheduled Reports

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-07-70 | Create report schedule | Admin | 1. Settings → Report Schedules → New 2. Select report type, frequency (daily/weekly), email 3. Save | Schedule created | P3 |
| TC-07-71 | Scheduled report sent | Schedule configured | 1. Wait for schedule or trigger manually | Email received with report attached | P3 |

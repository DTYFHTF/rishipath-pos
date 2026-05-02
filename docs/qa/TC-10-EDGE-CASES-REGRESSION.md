# TC-10 — Edge Cases, Regression & Cross-Functional Tests

**Priority**: P1–P2  
**Purpose**: Cover boundary conditions, known bugs, and scenarios that span multiple modules.

---

## 1. Known Bug Regression Tests

These bugs were previously reported and fixed. Verify they do not regress.

| # | Bug | Steps to Verify | Expected Result | Priority |
|---|-----|-----------------|-----------------|----------|
| TC-10-01 | Dashboard 500 on /livewire/update (LoyaltyStatsWidget) | 1. Log in 2. Navigate away and back to dashboard 3. Let Livewire refresh | Dashboard loads without 500 error. Loyalty stats widget shows data | P1 |
| TC-10-02 | Feedback navigation badge crash | 1. Log in when no feedback exists | Sidebar shows Feedback link. No 500 error | P1 |
| TC-10-03 | ProductVariant list defaultSort crash | 1. Navigate to Product Variants list | List loads without error. Variants shown sorted by ID | P1 |
| TC-10-04 | Supplier duplicate name allowed | 1. Create supplier "Test Supplier" 2. Create another "Test Supplier" | Second creation blocked with validation error | P1 |
| TC-10-05 | Price calculator public route | 1. Log out 2. Navigate to /price-calculator | Page loads (HTTP 200). No auth redirect | P1 |
| TC-10-06 | PurchaseItem discount/tax null crash | 1. Create purchase with no discount/tax on items 2. Save | No null reference error. Defaults to 0 | P1 |
| TC-10-07 | Notification duration too short | 1. Save any form | Toast notification visible for ~4 seconds | P2 |

---

## 2. Inventory & Stock Edge Cases

| # | Test Case | Steps | Expected Result | Priority |
|---|-----------|-------|-----------------|----------|
| TC-10-10 | Sell exact stock quantity | Product has 5 units | 1. POS: add 5 units to cart 2. Complete sale | Sale succeeds. Stock = 0 | P1 |
| TC-10-11 | Sell 1 more than stock | Product has 5 units | 1. POS: add 6 units | Error: insufficient stock. Sale blocked | P1 |
| TC-10-12 | Stock after voided sale restored | 5 units. Sell 3. Void sale | 1. Complete sale (stock → 2) 2. Void sale 3. Check stock | Stock = 5 (restored) | P1 |
| TC-10-13 | Multiple batches — FIFO enforced | 2 batches: LOT-A (expires 2026-06), LOT-B (expires 2027-01) | 1. Sell product | LOT-A consumed first (earlier expiry) | P1 |
| TC-10-14 | Batch expires today | Batch expiry = today | 1. Check inventory | Batch shown as expired or near-expiry | P2 |
| TC-10-15 | Transfer creates audit trail | Transfer stock A→B | 1. After transfer, check audit log | Both store entries in audit log | P2 |

---

## 3. POS Edge Cases

| # | Test Case | Steps | Expected Result | Priority |
|---|-----------|-------|-----------------|----------|
| TC-10-20 | Empty cart checkout | No items in cart | 1. Try to complete payment | Error: cart is empty | P1 |
| TC-10-21 | POS with 50 items | Add 50 line items | 1. Complete sale | Sale created with all 50 items. No timeout | P2 |
| TC-10-22 | Apply 100% discount | Item in cart | 1. Apply 100% discount | Item price = 0. Sale can complete (free item) | P2 |
| TC-10-23 | Negative payment amount | — | 1. Enter -100 in cash field | Validation error: must be positive | P1 |
| TC-10-24 | Concurrent sale — same product | Two tabs/sessions, same product (5 stock) | 1. Both add 3 units to cart 2. First completes (stock → 2) 3. Second tries to complete | Second sale blocked: insufficient stock | P1 |
| TC-10-25 | Loyalty points — redemption at exactly available points | Customer has exactly 100 points | 1. Redeem exactly 100 points | Accepted. Balance = 0 | P2 |

---

## 4. Form Validation Edge Cases

| # | Test Case | Steps | Expected Result | Priority |
|---|-----------|-------|-----------------|----------|
| TC-10-30 | XSS in text fields | 1. Enter `<script>alert('xss')</script>` in product name 2. Save | Stored as text. Not executed. No XSS | P1 |
| TC-10-31 | SQL injection in search | 1. Enter `' OR 1=1 --` in search box | Results filtered normally. No data leak | P1 |
| TC-10-32 | Very long text input | 1. Enter 1000-char string in a name field | Truncated at DB limit or validation error | P2 |
| TC-10-33 | Special characters in product name | 1. Enter "हल्दी पाउडर (500g)" in name | Saved and displayed correctly (UTF-8) | P2 |
| TC-10-34 | Decimal quantities | 1. Enter qty = 0.5 in batch creation | Accepted if system supports decimals; error if integers only | P2 |
| TC-10-35 | Future date in sale | 1. Manually set sale date to future | Not allowed or shows warning | P3 |

---

## 5. Performance & Usability

| # | Test Case | Steps | Expected Result | Priority |
|---|-----------|-------|-----------------|----------|
| TC-10-40 | Large product catalogue | 200+ products seeded | 1. Open POS product search 2. Type "spice" | Results appear within 2 seconds | P2 |
| TC-10-41 | Reports with 1000+ records | — | 1. Open sales report for last 12 months | Page loads within 5 seconds. Pagination works | P2 |
| TC-10-42 | Visit planner with 346 stores | All stores active | 1. Open visit planner | Recommendations load within 3 seconds | P2 |
| TC-10-43 | Bulk export large dataset | 1000+ records | 1. Export sales report | Download completes. File is not corrupt | P2 |

---

## 6. Multi-Store Isolation

| # | Test Case | Steps | Expected Result | Priority |
|---|-----------|-------|-----------------|----------|
| TC-10-50 | Store A cannot see Store B's sales | Two stores, different managers | 1. Log in as Store A Manager 2. View sales | Only Store A sales visible | P1 |
| TC-10-51 | Store A's inventory independent | Separate stock per store | 1. Transfer from A to B 2. Check Store A | Stock reduced in A, increased in B | P1 |
| TC-10-52 | User assigned to Store A logs in | User → Store A assignment | 1. Log in 2. Open POS | POS shows "Store A" header. Stock is Store A's | P1 |

---

## 7. Accessibility & UX

| # | Test Case | Steps | Expected Result | Priority |
|---|-----------|-------|-----------------|----------|
| TC-10-60 | Tab navigation in forms | 1. Open any create form 2. Use Tab key only | All fields accessible. Form submittable by keyboard | P3 |
| TC-10-61 | Mobile responsiveness | 1. Open on 375px mobile viewport | POS and main pages usable | P3 |
| TC-10-62 | Dark mode (if supported) | 1. Toggle dark mode | UI renders correctly without broken styles | P4 |

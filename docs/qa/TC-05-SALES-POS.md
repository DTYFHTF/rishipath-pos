# TC-05 — POS & Sales

**Priority**: P1 Critical  
**URL**: /admin/pos | /admin/sales

---

## 1. POS Interface

### 1.1 Basic Navigation & Load

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-05-01 | POS page loads | Logged in as Cashier/Manager/Admin | 1. Click "POS Billing" in nav | POS interface loads. Product search visible. Cart empty | P1 |
| TC-05-02 | POS shows correct store | Multiple stores configured | 1. Open POS | Store name shown matches current user's store | P1 |
| TC-05-03 | POS loads with keyboard shortcuts | POS open | 1. Check if keyboard shortcuts work (F2 for search, etc.) | Shortcuts functional | P3 |

### 1.2 Product Search & Add to Cart

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-05-10 | Search product by name | Products in stock | 1. Type "turmeric" in product search | Matching products with variants shown | P1 |
| TC-05-11 | Search product by SKU | Product with SKU | 1. Type exact SKU "TURM-100G" | Exact variant matched | P1 |
| TC-05-12 | Scan barcode | Variant with barcode | 1. Scan barcode / type barcode and press Enter | Product added to cart directly | P1 |
| TC-05-13 | Add product to cart | Product in stock | 1. Click on product variant | Item appears in cart. Qty = 1. Price shown correctly | P1 |
| TC-05-14 | Increase cart item quantity | Item in cart | 1. Click + button next to item | Quantity increases. Subtotal updates | P1 |
| TC-05-15 | Decrease cart item quantity | Item in cart (qty > 1) | 1. Click – button | Quantity decreases. Subtotal updates | P1 |
| TC-05-16 | Remove item from cart | Item in cart | 1. Click delete/trash icon | Item removed from cart | P1 |
| TC-05-17 | Add out-of-stock product | Variant with 0 stock | 1. Search and try to add | Error or warning: "Out of stock" | P1 |
| TC-05-18 | Add qty exceeding stock | 5 units in stock | 1. Set cart qty to 10 | Error: "Insufficient stock" | P1 |
| TC-05-19 | Add same product twice | Item already in cart | 1. Search same product and click again | Quantity increments (+1) instead of duplicate row | P2 |

### 1.3 Customer Assignment

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-05-20 | Assign existing customer | Customers in DB | 1. Type customer phone in customer field | Customer name shown. Loyalty points balance shown | P1 |
| TC-05-21 | Create new customer at POS | Unknown phone number | 1. Type new phone 2. Click "Add Customer" | Quick form opens. Save name + phone | P1 |
| TC-05-22 | Remove customer from sale | Customer assigned | 1. Click X on customer | Customer cleared. Loyalty points section hides | P2 |
| TC-05-23 | Walk-in (no customer) | — | 1. Proceed to payment without assigning customer | Sale created as "Walk-in" | P2 |

### 1.4 Discounts

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-05-30 | Apply percentage discount on item | Item in cart | 1. Click discount on item 2. Enter 10% | 10% deducted from that item. Total updates | P1 |
| TC-05-31 | Apply flat discount on item | Item in cart | 1. Enter ₹5 flat discount on item | ₹5 deducted. Total updates | P1 |
| TC-05-32 | Apply cart-level discount | Cart with items | 1. Enter overall discount % 2. Apply | Discount applied to total amount | P1 |
| TC-05-33 | Discount exceeds item price | — | 1. Apply 200% or ₹9999 discount | Validation error: discount cannot exceed price | P1 |
| TC-05-34 | Cashier applies discount — with permission | Cashier role (apply_discounts permission) | 1. Apply discount | Discount applied | P2 |
| TC-05-35 | Cashier applies discount — without permission | Cashier without apply_discounts | 1. Try to apply discount | Field disabled or error shown | P2 |

---

## 2. Payment Processing

### 2.1 Payment Methods

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-05-40 | Pay full amount by cash | Cart ready | 1. Select Cash 2. Enter exact amount 3. Confirm | Sale completed. No change | P1 |
| TC-05-41 | Cash payment with change | — | 1. Enter cash amount > total (e.g., ₹500 for ₹350 bill) | Change amount shown (₹150). Sale completed | P1 |
| TC-05-42 | Pay by UPI | Cart ready | 1. Select UPI 2. Enter UPI ref 3. Confirm | Sale completed with UPI payment recorded | P1 |
| TC-05-43 | Pay by card | Cart ready | 1. Select Card 2. Confirm | Sale completed with card payment | P1 |
| TC-05-44 | Split payment — cash + UPI | Cart ₹500 | 1. Pay ₹200 cash + ₹300 UPI | Both amounts recorded. Total = ₹500 | P1 |
| TC-05-45 | Split payment — 3 methods | — | 1. Pay with Cash + Card + UPI | All three shown in sale record | P2 |
| TC-05-46 | Pay amount less than total | Cart ₹500 | 1. Enter only ₹300 cash, no other payment | Error: payment amount must equal total | P1 |
| TC-05-47 | Pay with loyalty points | Customer with points | 1. Check "Redeem Points" 2. Enter points to redeem 3. Pay remaining by cash | Points deducted. Sale amount reduces accordingly | P1 |

### 2.2 Complete Sale & Receipt

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-05-50 | Sale created with correct data | Payment complete | 1. Complete sale | Sale record created with correct items, amounts, customer, store | P1 |
| TC-05-51 | Inventory decreases after sale | Product in stock | 1. Sell 5 units 2. Check inventory | Stock decreases by 5 | P1 |
| TC-05-52 | Customer loyalty points earned | Loyalty customer, active program | 1. Complete sale 2. Check customer loyalty | Points added per loyalty tier rules | P1 |
| TC-05-53 | Receipt generated | Sale completed | 1. After sale, click Print/Receipt | Receipt shows store name, items, qty, price, total, payment, date | P1 |
| TC-05-54 | Cart clears after sale | — | 1. Complete sale | Cart resets to empty. Ready for next customer | P1 |
| TC-05-55 | Sale invoice number sequential | Multiple sales | 1. Create sales | Each sale has unique, sequential invoice number (SALE-000001, etc.) | P2 |

---

## 3. Void & Refund

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-05-60 | Void sale | Completed sale, manager logged in | 1. Go to Sales list 2. Open sale 3. Click Void 4. Confirm | Sale marked as void. Inventory restored. Loyalty points reversed | P1 |
| TC-05-61 | Void sale — cashier without permission | Cashier role | 1. Try to void sale | Void action not visible or 403 | P1 |
| TC-05-62 | Void sale appears in reports | Sale voided | 1. Check Sales Report | Void sale excluded from revenue total (or clearly marked) | P2 |
| TC-05-63 | Process refund — partial | Manager, completed sale | 1. Sales → Open sale → Refund 2. Select partial items | Refund created. Stock partially restored | P2 |
| TC-05-64 | Process refund — full | — | 1. Refund all items | Full amount refunded. Full stock restored | P2 |

---

## 4. Sales History & Management

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-05-70 | View sales list | Sales exist | 1. Go to Sales | All sales listed with invoice no., date, customer, total | P1 |
| TC-05-71 | View sale detail | Sale exists | 1. Click on sale | Detail shows items, payments, customer, staff, timestamp | P1 |
| TC-05-72 | Filter sales by date range | — | 1. Set date filter | Only sales in range shown | P2 |
| TC-05-73 | Filter sales by cashier | — | 1. Filter by user | Only that user's sales shown | P2 |
| TC-05-74 | Filter sales by store | Multiple stores | 1. Filter by store | Store-scoped results | P2 |
| TC-05-75 | Search sale by invoice no. | — | 1. Type invoice number | Exact match shown | P2 |
| TC-05-76 | Search sale by customer name | — | 1. Type customer name | All sales by that customer | P2 |

---

## 5. Price Calculator

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-05-80 | Access price calculator | Not logged in | 1. Navigate to /price-calculator | Page loads (public route) | P2 |
| TC-05-81 | Price calculation | On calculator page | 1. Enter base price, markup %, GST % | Selling price calculated correctly | P2 |

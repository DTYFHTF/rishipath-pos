# TC-04 — Suppliers & Purchases

**Priority**: P1–P2  
**URL**: /admin/suppliers | /admin/purchases

---

## 1. Supplier Management

### 1.1 Create Supplier

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-04-01 | Create supplier — all required fields | Logged in as Admin/Manager/Clerk | 1. Suppliers → New 2. Enter name "Rajesh Spice Co.", phone "9876543210", city "Mumbai" 3. Save | Supplier created. Appears in supplier list | P1 |
| TC-04-02 | Create supplier — duplicate name | "Rajesh Spice Co." already exists | 1. Create another supplier with same name | Validation error: supplier name already exists in org | P1 |
| TC-04-03 | Create supplier — blank name | — | 1. Leave name empty 2. Save | Validation error: name required | P1 |
| TC-04-04 | Create supplier — with address | — | 1. Fill address, city, state, pincode 2. Save | Full address saved and shown | P2 |
| TC-04-05 | Create supplier — with GST number | — | 1. Enter GST number 2. Save | GST number saved correctly | P2 |
| TC-04-06 | Create supplier — with bank details | — | 1. Enter bank account, IFSC 2. Save | Bank details saved | P3 |
| TC-04-07 | Create supplier — with email | — | 1. Enter email 2. Save | Email saved and used for notifications | P3 |

### 1.2 Edit Supplier

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-04-08 | Edit supplier contact number | Supplier exists | 1. Edit → change phone 2. Save | Phone updated | P2 |
| TC-04-09 | Edit supplier name to existing name | Two suppliers exist | 1. Edit supplier A's name to match supplier B's name | Validation error: duplicate name | P2 |

### 1.3 Supplier List & Search

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-04-10 | Search supplier by name | Suppliers exist | 1. Type in search box | Matching suppliers shown | P2 |
| TC-04-11 | View supplier's purchase history | Supplier has purchases | 1. Click on supplier 2. View purchases | All purchase orders for that supplier listed | P2 |
| TC-04-12 | View supplier ledger | Supplier with purchases and payments | 1. Open supplier 2. Click "Ledger" | Debit/credit ledger entries shown with running balance | P1 |

### 1.4 Supplier Ledger

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-04-13 | Ledger shows purchase as debit | Purchase created for supplier | 1. Open Supplier Ledger Report 2. Filter by supplier | Purchase amount shown as debit entry | P1 |
| TC-04-14 | Ledger shows payment as credit | Payment recorded for supplier | 1. Supplier Ledger | Payment shown as credit entry | P1 |
| TC-04-15 | Ledger running balance is correct | Multiple transactions | 1. Verify running balance = sum of debits - credits | Balance is mathematically correct | P1 |

---

## 2. Purchase Orders

### 2.1 Create Purchase Order

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-04-20 | Create PO — single item | Supplier and product exist | 1. Purchases → New 2. Select supplier 3. Add item: variant, qty 100, unit cost 40 4. Save | PO created with status "Pending". Supplier ledger debited | P1 |
| TC-04-21 | Create PO — multiple items | — | 1. Add 5 different variants to one PO 2. Save | PO created with all items. Subtotal correct | P1 |
| TC-04-22 | Create PO — with discount | — | 1. Add item 2. Apply discount % or amount 3. Save | Discount deducted. Total correct | P2 |
| TC-04-23 | Create PO — with tax | — | 1. Add item 2. Set tax rate (e.g., 18% GST) 3. Save | Tax calculated correctly. Total includes tax | P2 |
| TC-04-24 | Create PO — no supplier selected | — | 1. Leave supplier blank 2. Save | Validation error | P1 |
| TC-04-25 | Create PO — no items | — | 1. Leave items list empty 2. Save | Validation error: at least one item required | P1 |
| TC-04-26 | Create PO — with expected delivery date | — | 1. Set expected delivery date 2. Save | Date saved. Shown on PO detail | P3 |

### 2.2 Receive Purchase Order (Mark Received)

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-04-30 | Mark PO as fully received | PO in Pending status | 1. Open PO 2. Click "Mark Received" or "Receive Items" 3. Confirm quantities 4. Submit | PO status → "Received". Inventory increases by received quantities. Batch(es) auto-created | P1 |
| TC-04-31 | Partial receipt | PO for 100 units | 1. Receive only 60 units | PO status → "Partially Received". Stock increases by 60. Balance 40 remains | P1 |
| TC-04-32 | Receive creates batch | PO received | 1. After receiving, go to Inventory → Batches | New batch created with lot number, cost, received date | P1 |
| TC-04-33 | Receive updates supplier ledger | PO exists | 1. After receiving, check supplier ledger | No change — ledger is updated at PO creation (or confirm based on system behavior) | P2 |

### 2.3 Purchase Returns

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-04-40 | Create purchase return — partial | Received PO | 1. Open PO 2. Return Items 3. Select items, qty, reason 4. Save | Return recorded. Inventory decreases by returned qty. Supplier ledger adjusted | P1 |
| TC-04-41 | Create purchase return — full | Received PO | 1. Return all items 2. Save | Full return created. Inventory fully reversed | P1 |
| TC-04-42 | Return quantity exceeds received | — | 1. Try to return 50 when only 30 received | Validation error: return qty cannot exceed received qty | P1 |
| TC-04-43 | Purchase return updates supplier balance | Return created | 1. Check supplier ledger | Credit entry shown matching return amount | P2 |

### 2.4 Purchase List & Filters

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-04-50 | Filter POs by supplier | — | 1. Select supplier filter | Only that supplier's POs shown | P2 |
| TC-04-51 | Filter POs by status | — | 1. Filter by "Pending" | Only pending POs shown | P2 |
| TC-04-52 | Filter POs by date range | — | 1. Set from/to date | POs within range shown | P2 |
| TC-04-53 | View PO total on list | Multiple POs | 1. View list | Total amount shown per row | P2 |

---

## 3. Record Payment to Supplier

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-04-60 | Record payment for supplier | Supplier with outstanding balance | 1. Supplier → Record Payment 2. Enter amount, date, payment method 3. Save | Payment recorded. Supplier ledger shows credit. Balance reduced | P1 |
| TC-04-61 | Payment amount > outstanding | Supplier has ₹1000 outstanding | 1. Try to record ₹1500 payment | Warning or error: overpayment | P2 |
| TC-04-62 | Payment with reference note | — | 1. Add reference number 2. Save | Reference saved in ledger entry | P3 |

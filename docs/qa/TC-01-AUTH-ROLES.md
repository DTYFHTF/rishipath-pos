# TC-01 — Authentication & Role-Based Access Control

**Priority**: P1 Critical  
**URL**: https://pos.shuddhidham.com/admin

---

## 1. Authentication

| # | Test Case | Steps | Expected Result | Priority |
|---|-----------|-------|-----------------|----------|
| TC-01-01 | Login with valid credentials | 1. Go to /admin/login 2. Enter admin@rishipath.org / password 3. Click Sign In | Redirected to /admin dashboard. Welcome shown | P1 |
| TC-01-02 | Login with wrong password | 1. Enter correct email + wrong password | "These credentials do not match" error shown. No login | P1 |
| TC-01-03 | Login with blank fields | 1. Leave email/password blank 2. Submit | Validation errors shown on both fields | P1 |
| TC-01-04 | Login with wrong email format | 1. Enter "notanemail" as email | Email format validation error shown | P2 |
| TC-01-05 | Logout | 1. Log in 2. Click user avatar (top right) 3. Click Sign Out | Redirected to /admin/login. Session cleared | P1 |
| TC-01-06 | Session persistence | 1. Log in 2. Close browser tab 3. Reopen /admin | Should be still logged in (session active) | P2 |
| TC-01-07 | Direct URL access while logged out | 1. Log out 2. Navigate to /admin/products | Redirected to /admin/login | P1 |

---

## 2. Role: Super Administrator

**Full access to all features.**

| # | Test Case | Steps | Expected Result | Priority |
|---|-----------|-------|-----------------|----------|
| TC-01-10 | Can access all nav menu items | Log in as Super Admin. Check sidebar navigation | All sections visible: Dashboard, POS, Products, Inventory, Purchases, Suppliers, Customers, Sales, Reports, Field Sales, Settings | P1 |
| TC-01-11 | Can create users | Go to Settings → Users → New | Create user form accessible. Can save | P1 |
| TC-01-12 | Can manage roles | Go to Settings → Roles | Roles list visible. Can edit role permissions | P1 |
| TC-01-13 | Can manage organizations | Go to Settings → Organizations | Organization records visible and editable | P2 |
| TC-01-14 | Can delete any record | Navigate to Products → select product → Delete | Delete action available and works | P2 |

---

## 3. Role: Store Manager

**Cannot: delete products/users, manage roles, access system settings.**

| # | Test Case | Steps | Expected Result | Priority |
|---|-----------|-------|-----------------|----------|
| TC-01-20 | Login as Store Manager | Log in with manager credentials | Dashboard visible. Navigation shows allowed items | P1 |
| TC-01-21 | Can access POS | Click "POS Billing" in nav | POS interface loads | P1 |
| TC-01-22 | Can create products | Go to Products → New | Form accessible. Can save product | P1 |
| TC-01-23 | Cannot delete products | Go to Products → View a product | Delete action NOT visible/accessible | P1 |
| TC-01-24 | Cannot manage roles | Navigate to /admin/roles | Redirected or 403 shown | P1 |
| TC-01-25 | Cannot access system settings | Navigate to /admin/organizations | Redirected or 403 shown | P2 |
| TC-01-26 | Can view all sales (not just own) | Go to Sales list | All sales from the org visible | P2 |
| TC-01-27 | Can void a sale | Go to Sales → select sale → Void | Void action available and works | P2 |

---

## 4. Role: Cashier

**Main function: POS billing. Limited to own sales. Cannot manage products/inventory.**

| # | Test Case | Steps | Expected Result | Priority |
|---|-----------|-------|-----------------|----------|
| TC-01-30 | Login as Cashier | Log in with cashier credentials | Dashboard loads with POS-focused view | P1 |
| TC-01-31 | Can access POS | Click "POS Billing" | POS loads normally | P1 |
| TC-01-32 | Can only view own sales | Go to Sales list | Only sales created by this cashier are visible | P1 |
| TC-01-33 | Cannot create products | Navigate to /admin/products/create | Redirected or 403 | P1 |
| TC-01-34 | Cannot adjust stock | Navigate to /admin/stock-adjustment | Redirected or 403 | P1 |
| TC-01-35 | Cannot void sales | Go to Sales → own sale | Void action NOT visible | P1 |
| TC-01-36 | Cannot access reports | Navigate to /admin/sales-report | Redirected or 403 | P2 |
| TC-01-37 | Can create new customers at POS | In POS, search unknown phone → Add new customer | Customer creation modal opens and saves | P2 |

---

## 5. Role: Inventory Clerk

**Full inventory access. No POS, no sales, no profit reports.**

| # | Test Case | Steps | Expected Result | Priority |
|---|-----------|-------|-----------------|----------|
| TC-01-40 | Login as Inventory Clerk | Log in with clerk credentials | Dashboard shows inventory-focused widgets | P1 |
| TC-01-41 | Can view stock levels | Go to Inventory → Stock Levels | Stock levels list loads | P1 |
| TC-01-42 | Can adjust stock | Go to Inventory → Stock Adjustment | Form accessible. Can submit | P1 |
| TC-01-43 | Can create product batches | Go to Inventory → Batches → New | Batch creation form accessible | P1 |
| TC-01-44 | Cannot access POS | Navigate to /admin/pos | Redirected or 403 | P1 |
| TC-01-45 | Cannot view profit reports | Navigate to /admin/profit-report | Redirected or 403 | P2 |
| TC-01-46 | Cannot create sales | Navigate to /admin/sales/create | Redirected or 403 | P1 |

---

## 6. Role: Accountant

**View-only for most data. Full reporting access. No POS, no data entry.**

| # | Test Case | Steps | Expected Result | Priority |
|---|-----------|-------|-----------------|----------|
| TC-01-50 | Login as Accountant | Log in with accountant credentials | Dashboard visible with finance widgets | P1 |
| TC-01-51 | Can view sales | Go to Sales | List loads. View action works | P1 |
| TC-01-52 | Cannot create sales | Check Sales list for "New" button | "New" button NOT present | P1 |
| TC-01-53 | Can access all reports | Go to Reports section | Sales, Profit, Inventory, Customer Ledger, Supplier Ledger all accessible | P1 |
| TC-01-54 | Can export reports | Open any report → click Export | CSV/PDF download starts | P2 |
| TC-01-55 | Cannot edit products | Go to Products → click a product | Edit action NOT available | P1 |
| TC-01-56 | Cannot adjust stock | Navigate to /admin/stock-adjustment | Redirected or 403 | P1 |
| TC-01-57 | Cannot access POS | Navigate to /admin/pos | Redirected or 403 | P1 |

---

## 7. Multi-Tenant Organization Isolation

| # | Test Case | Steps | Expected Result | Priority |
|---|-----------|-------|-----------------|----------|
| TC-01-60 | Users only see own org data | Log in as any role | Products, sales, customers shown belong to "Shuddhidham" only | P1 |
| TC-01-61 | Store switcher works | If multiple stores configured — switch store | Dashboard, POS, and inventory reflect selected store | P2 |

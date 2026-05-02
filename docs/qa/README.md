# QA Test Suite — Rishipath POS

**System**: pos.shuddhidham.com/admin  
**Type**: Manual Testing  
**Version**: April 2026

## Test Case Documents

| File | Coverage |
|------|----------|
| [TC-01-AUTH-ROLES.md](TC-01-AUTH-ROLES.md) | Login, logout, role-based access control, permissions |
| [TC-02-PRODUCTS.md](TC-02-PRODUCTS.md) | Categories, products, variants, barcodes |
| [TC-03-INVENTORY.md](TC-03-INVENTORY.md) | Batches, stock levels, adjustments, transfers, movements |
| [TC-04-SUPPLIERS-PURCHASES.md](TC-04-SUPPLIERS-PURCHASES.md) | Suppliers, purchase orders, receiving, returns, ledger |
| [TC-05-SALES-POS.md](TC-05-SALES-POS.md) | POS billing, sales, discounts, payments, refunds/voids |
| [TC-06-CUSTOMERS-LOYALTY.md](TC-06-CUSTOMERS-LOYALTY.md) | Customers, loyalty enroll, points, tiers, rewards |
| [TC-07-REPORTS.md](TC-07-REPORTS.md) | Sales report, profit, inventory, stock valuation, export |
| [TC-08-RETAIL-STORES.md](TC-08-RETAIL-STORES.md) | Retail store records, visits, visit planner |
| [TC-09-ADMIN-SETTINGS.md](TC-09-ADMIN-SETTINGS.md) | Users, stores, organizations, alert rules, bulk orders |

## Test Environment

| Detail | Value |
|--------|-------|
| URL | https://pos.shuddhidham.com/admin |
| Admin Email | admin@rishipath.org |
| Admin Password | password |
| Organization | Shuddhidham |

## Conventions

- **Pass ✅** — Behaviour matches expected result  
- **Fail ❌** — Behaviour does not match expected  
- **Block 🚫** — Cannot test due to dependency / missing data  
- **Skip ⏭️** — Not in scope for this round  

### Priority Levels
- **P1 — Critical**: System unusable if broken  
- **P2 — High**: Core feature broken  
- **P3 — Medium**: Feature works with workaround  
- **P4 — Low**: Minor / cosmetic  

## Test Data Quick Reference

### Demo Accounts (to be created)
| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@rishipath.org | password |
| Store Manager | manager@rishipath.org | password |
| Cashier | cashier@rishipath.org | password |
| Inventory Clerk | clerk@rishipath.org | password |
| Accountant | accountant@rishipath.org | password |

### Sample Products (seeded)
- Turmeric Powder — 50g, 100g, 500g, 1kg variants
- Saffron (Kesar) — 1g, 5g, 10g variants
- Black Pepper (Whole) — 50g, 100g, 500g variants

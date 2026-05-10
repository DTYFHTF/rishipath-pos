# MVP Sales Agent Implementation - DRY Checklist

## What Already Exists (DO NOT DUPLICATE)

### Models & Relationships ✅
- **SalesAgent** — Full model with commission fields
- **SalesAgentLedger** — Ledger for earnings tracking
- **Sale** — Has: sales_agent_id, order_channel, wholesale_base_amount, company_profit_amount, agent_commission_amount, payment_status, payment_method, payment_reference, amount_paid
- **Customer** — Existing customer model
- **SalePayment** — Payment tracking
- **Product/ProductVariant** — Pricing (MRP, wholesale) already configured

### Services ✅
- **SalesAgentCommissionService::calculateForSale()** — Computes commission based on channel
- **WhatsAppService** — Send receipts
- **PricingService** — Price lookups
- **OrganizationContext** — Multi-org scoping
- **InventoryService** — Stock checks

### Filament Resources ✅
- **SalesAgentResource** — CRUD for agents (already built)
- **SaleResource** — CRUD for sales (use for admin view)
- **CustomerResource** — CRUD for customers (use for agent view)

### POS Flow ✅
- **EnhancedPOS** — Full retail POS (reuse this codebase)
- Payment method capture (cash, upi, esewa, khalti, other)
- Split payments support
- Receipt generation + WhatsApp share

### Database Tables ✅
- sales.sales_agent_id
- sales.order_channel
- sales.wholesale_base_amount
- sales.company_profit_amount
- sales.agent_commission_amount
- sales.payment_status
- sales.payment_method
- sales.payment_reference
- sales.amount_paid
- sales_agent_ledgers (for earnings tracking)

---

## What to Build (Minimal MVP = 5 Migrations + 3 Filament Pages)

### 1️⃣ MIGRATION: Add Customer Assignment (5 min)
File: `database/migrations/2026_05_11_000001_add_sales_agent_id_to_customers_table.php`

Add:
- `sales_agent_id` foreign key to customers table (nullable)

Rationale: Scope customers to agents so agents only see own customers.

### 2️⃣ MIGRATION: Add Delivery Charge (5 min)
File: `database/migrations/2026_05_11_000002_add_delivery_charge_to_sales_table.php`

Add:
- `delivery_charge` decimal(10,2) default 0
- `delivery_charge_applied` boolean default false (tracks if rule was auto-applied)

Rationale: Store delivery charges and auto-apply rule logic.

### 3️⃣ MIGRATION: Add Payment Settlement Ref (5 min)
File: `database/migrations/2026_05_11_000003_add_settlement_fields_to_sales_table.php`

Add:
- `settlement_reference` varchar(255) nullable (QR/bank ref)
- `settlement_confirmed_at` timestamp nullable (when admin confirms settlement)

Rationale: Track payment proof for cash/QR/bank modes.

### 4️⃣ MODEL UPDATES: Add Scopes & Hooks (20 min)

**app/Models/Sale.php**
- Add scope: `scopeForAgent(agent_id)` — filter sales by agent
- Add hook: `saved` — auto-post commission to SalesAgentLedger when completed + paid
- Add method: `applyDeliveryChargeRule()` — auto-apply if total < 10,000

**app/Models/Customer.php**
- Add relationship: `agent()` — BelongsTo SalesAgent
- Add scope: `scopeForAgent(agent_id)` — filter customers by agent

**app/Models/SalesAgent.php**
- Add computed property: `current_earnings` — sum of ledger entries (unpaid)
- Add method: `settlementsByPaymentMode()` — reconciliation helper

### 5️⃣ FILAMENT PAGE 1: Agent POS Page (90 min)

File: `app/Filament/Pages/AgentPOS.php`

Extend EnhancedPOS but:
- **Agent-scoped** (only show assigned customers)
- **Order mode only** (no retail POS from stock; order for future pickup/delivery)
- **Auto-delivery-charge** (apply if order < NPR 10,000)
- **Two pricing modes**: MRP (retail) and Wholesale (business)
- **Cashier gate**: button disabled until:
  - Product handover confirmed (checkbox or note)
  - Payment confirmed (payment_reference filled OR amount_paid filled)
- **Payment fields**:
  - Mode: Cash / Company QR / Bank Transfer
  - Amount
  - Reference (for QR/bank)
  - Status: Paid / Partial / Pending
- **Earnings preview**: show estimated agent earning before final complete

Rationale: Minimal, order-first, payment-safe.

### 6️⃣ FILAMENT PAGE 2: Agent Dashboard (Agent Side) (45 min)

File: `app/Filament/Pages/AgentDashboard.php`

Public for: Sales Agents

Show (today):
- Total sales amount
- Collections (cash + QR + bank settled)
- Pending collections (unpaid orders)
- Delivery charges applied
- Estimated agent earning
- Settled earning (from ledger)

Cards:
- Big numbers, large text, simple layout

Rationale: Agent motivation — they see their earnings in real-time.

### 7️⃣ FILAMENT PAGE 3: Agent Earnings Report (Admin Side) (60 min)

File: `app/Filament/Pages/AgentEarningsReport.php`

Public for: Admin

Table:
- Agent name
- Today sales
- Today collections
- Payment mode breakdown (cash / QR / bank)
- Delivery charges
- Estimated earning
- Settled amount
- Variance (accrued vs settled)

Filters:
- Agent
- Date range
- Payment mode

Actions:
- Confirm settlement (mark payment_reference as verified)

Rationale: Admin reconciliation in 2 minutes daily.

### 8️⃣ SERVICE: Delivery Charge Auto-Apply (10 min)

File: `app/Services/DeliveryChargeService.php`

Method: `applyIfNeeded(Sale $sale, float $threshold = 10000)`

Logic:
- If order total (before charges) < threshold
- Apply delivery charge (amount = configurable, e.g., NPR 100-500 based on policy)
- Mark delivery_charge_applied = true

Call in: AgentPOS before completing sale.

### 9️⃣ SERVICE: Commission Auto-Post (10 min)

File: Update `app/Services/SalesAgentCommissionService.php`

Add method: `postToLedger(Sale $sale)`

Logic:
1. Call `calculateForSale()` (already exists)
2. Create SalesAgentLedger entry
3. Set entry_type = 'commission'
4. Set amount = commission amount from calculation
5. Set reference = Sale invoice/receipt number

Call in: Sale model hook when:
- order_channel is set
- payment_status = 'paid'
- sales_agent_id is set

---

## Build Order This Week

### Day 1 (Migrations + Models)
1. Write 3 migrations
2. Update Sale model (scopes + hooks)
3. Update Customer model (relationship)
4. Update SalesAgent model (properties)
5. Run migrations locally
6. Commit

### Day 2 (Services + Pages)
1. Write DeliveryChargeService
2. Update SalesAgentCommissionService
3. Create AgentPOS page (copy EnhancedPOS, adapt)
4. Test order creation as agent
5. Commit

### Day 3 (Admin Pages + Reconciliation)
1. Create AgentDashboard page (agent view)
2. Create AgentEarningsReport page (admin view)
3. Test earnings auto-post
4. Test settlement fields
5. Commit

### Day 4 (Permissions + Scoping)
1. Add policy: AgentPolicy (agent sees only own customers/sales)
2. Add policy: AdminPolicy (admin sees all)
3. Update Filament pages with Livewire policies
4. Seed 2 test agents + 10 test customers
5. Create 5 test orders, verify commission posting

### Day 5 (Deploy + Pilot)
1. Full deploy to production
2. Create test agent account
3. Run live pilot for 1 day
4. Verify earnings calculation and settlement
5. Document any issues

---

## What NOT to Build (Keep Out of MVP)
- ❌ Full route/visit tracking
- ❌ Stock management for agents
- ❌ Complex return/refund flows
- ❌ Manager approval workflows
- ❌ Offline sync queue
- ❌ Bulk customer import
- ❌ Advanced reporting

---

## Success Checklist
✅ Agent can create order in < 90 seconds
✅ System auto-applies delivery charge correctly
✅ Payment confirmed blocks order completion until filled
✅ Every paid order creates SalesAgentLedger entry
✅ Agent sees earnings dashboard update in real-time
✅ Admin can reconcile all settlements by payment mode
✅ No manual commission calculation needed
✅ Agent can create new customer inline while ordering
✅ WhatsApp receipt share works (reuse existing service)
✅ Multi-org isolation respected

---

## Files to Create (New)
```
database/migrations/2026_05_11_000001_add_sales_agent_id_to_customers_table.php
database/migrations/2026_05_11_000002_add_delivery_charge_to_sales_table.php
database/migrations/2026_05_11_000003_add_settlement_fields_to_sales_table.php
app/Services/DeliveryChargeService.php
app/Filament/Pages/AgentPOS.php
app/Filament/Pages/AgentDashboard.php
app/Filament/Pages/AgentEarningsReport.php
```

## Files to Edit (Existing)
```
app/Models/Sale.php — add scopes, hooks, delivery charge method
app/Models/Customer.php — add agent relationship, scope
app/Models/SalesAgent.php — add computed properties
app/Services/SalesAgentCommissionService.php — add postToLedger()
```

---

## Estimated Effort
- Migrations: 15 min
- Models: 30 min
- Services: 20 min
- 3 Filament Pages: 195 min (60 + 45 + 90)
- Policies & Scoping: 45 min
- Testing & Deploy: 120 min

**Total: ~8 hours spread over 5 days = 1-2 hours per day**

Lean, DRY, uses existing code 100%, and focuses on monetization tracking.

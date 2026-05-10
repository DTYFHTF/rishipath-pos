# Sales Agent Implementation Roadmap (Lean MVP)

## Objective
Launch a monetization-first field sales system with minimal complexity.

Main principle:
- Build income tracking and payment correctness first.
- Add deep stock and operations later.

## Phase 0: Business Rules Lock (2-3 days)
- Confirm order model: order-first with optional tiny micro-stock.
- Confirm delivery charge rule: below NPR 10,000 apply charge.
- Confirm pricing modes: MRP retail and wholesale business.
- Confirm wholesale incentive ladder: 25% -> 50%.
- Confirm settlement rules for cash, company QR, and bank.

Exit criteria:
- Written policy approved
- Formula references finalized

## Phase 1: MVP Build (7-10 days)
Deliver only essential modules.

Deliverables:
- Agent POS order flow (retail and wholesale modes)
- Payment capture and status tracking
- Auto delivery charge rule (order value < NPR 10,000)
- Customer assignment scope (agent sees only own customers)
- Agent Earnings Dashboard
- Agent Earnings Ledger auto-posting
- Admin view for order-to-income traceability

Exit criteria:
- Agent can work end-to-end without manual admin intervention
- Every completed paid order updates earning records automatically

## Phase 2: MVP Stability (5-7 days)
- Reconciliation tools for cash/QR/bank settlement
- Return/cancellation impact on earnings
- Basic alerts: pending payment, overdue collection
- CSV export for earnings and settlements

Exit criteria:
- Finance reconciliation done daily with low mismatch
- Admin can audit quickly

## Phase 3: Controlled Expansion (later)
- Tiny agent stock module for top movers
- Bulk order scheduling enhancements
- Visit planner and route tracking
- Advanced commission tuning

## MVP Navigation (Keep It Tiny)
1. Dashboard
2. POS Orders
3. Customers
4. Collections
5. Earnings
6. Price List

## Data Model Focus for MVP
- sales_agents
- sales_agent_ledgers
- customer_assignments
- sales (with sales_agent_id, order_channel, payment_status, settlement_ref)
- optional delivery_charge fields on sales/order

Avoid adding complex stock tables until phase 3 unless needed.

## Commission and Incentive Engine (MVP)
- Retail earning:
- Based on margin between MRP and internal transfer basis.
- Wholesale incentive:
- Use monthly performance tiers from 25% to 50%.
- Tier basis:
- Net realized wholesale value only.
- Adjustments:
- Reverse earning entries for returns/cancellations.

## Recommended Immediate Build Order (Practical)
1. Finalize formulas and tier thresholds
2. Complete POS payment states and settlement refs
3. Auto-post ledger entries on completed paid orders
4. Build agent earnings dashboard widgets
5. Build admin reconciliation and audit screen
6. Pilot with one agent for one week

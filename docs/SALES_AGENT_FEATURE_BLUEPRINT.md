# Sales Agent Feature Blueprint (MVP First)

## Objective
Start with the smallest working model where agents can sell independently and the system tracks income automatically.

Priority for MVP:
- Take orders quickly
- Accept/record payments correctly
- Track agent earnings and company margin
- Keep admin fully informed without manual follow-up

## Core Decision for MVP: Keep Stock Light
For first rollout, avoid heavy field inventory.

Use this operating model:
1. Order-first model (default): agent takes order, warehouse fulfills, customer receives delivery or picks up.
2. Micro-stock model (optional): agent keeps only fast-moving 1-2 kg packs for instant retail sales.

Recommendation:
- Start with order-first + tiny micro-stock only for top 5 fast movers.

## Commercial Rules to Automate (System-Driven)
1. Agent buy basis: wholesale price from company.
2. Retail sale target: MRP.
3. Retail store/business sale: wholesale rates.
4. Delivery charge rule: if order total is below NPR 10,000, apply delivery charge automatically.
5. Commission ladder (for wholesale value brought):
- 25% start tier
- 30% next tier
- 35% next tier
- 40% next tier
- 45% next tier
- 50% top tier

Tier movement should be computed by system monthly using net realized wholesale value (after returns/cancellations).

## MVP Features Only (Do Not Go Deep Yet)

## 1. Agent Income Dashboard (Most Important)
- Today gross sales
- Today collections (cash + QR + bank)
- Pending receivables
- Estimated agent earning
- Settled earning
- Delivery charges applied today

This is the main page agents care about.

## 2. Order POS (Single Flow)
- Create order for retail or business customer
- Select pricing mode: MRP or Wholesale
- Auto-apply delivery charge if below NPR 10,000
- Capture payment mode:
- Cash
- Company QR
- Bank transfer
- Mark payment state:
- Paid now
- Partially paid
- Pending
- Generate receipt/share link

## 3. Cashier Access Gate (As You Requested)
- Agent gets cashier checkout capability only when:
- Products are received/handed over to customer, and
- Payment is confirmed (cash collected or company QR/bank confirmed).

Until then, order stays in "Order Placed" or "Awaiting Settlement" state.

## 4. My Customers (Dedicated)
- Only assigned customers visible
- Quick add customer/store with minimum fields
- Show customer outstanding + recent order history

## 5. Price List
- Clear MRP vs Wholesale display
- Voice/text search in Nepali/Hindi/English
- Large text mode for easy visibility

## 6. Basic Stock (Optional, Tiny)
- Only if micro-stock used
- In/Out entries with reason
- No complex batch handling in MVP unless legally required

## 7. Agent Earnings Ledger
System should auto-post earning entries from completed paid orders:
- Retail margin component (MRP - wholesale basis)
- Wholesale incentive tier component
- Delivery charge impact (if borne by agent/company, based on policy)
- Deductions from returns/cancellations

## Simple Order Lifecycle (MVP)
1. Agent creates order.
2. System assigns pricing mode and delivery charge rule.
3. Fulfillment happens (delivery or customer pickup).
4. Payment gets confirmed (cash/QR/bank).
5. Order moves to completed.
6. Earnings ledger auto-updates.

No manual calculation by you.

## Out of MVP for Now
- Full route/visit planner
- Deep field inventory operations
- Advanced return claim workflows
- Complex manager hierarchy
- Heavy offline sync conflict tooling

## Success Metrics for MVP
- Agent can create order in under 90 seconds.
- 100% paid orders are reflected in earnings ledger.
- 0 manual commission calculations by admin.
- Admin can audit every earning to order line level.

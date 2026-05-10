# Sales Agent Permissions and Admin Monitoring Plan (MVP)

## Goal
Let agents sell and collect independently while system controls monetization and admin has full visibility.

## Role Model for MVP
- Role 1: Sales Agent
- Role 2: Admin/Super Admin

Keep manager layer for later unless needed immediately.

## Sales Agent Permissions (MVP)

Allow:
- Create orders for assigned customers
- Choose pricing mode: retail MRP or wholesale
- Record payment method: cash, company QR, bank
- View own pending collections
- View own earnings dashboard and ledger
- View price list and customer list (assigned only)

Restrict:
- Access other agents' data
- Edit pricing policies or tier rules
- Manually edit earning ledger postings
- Final-cancel completed paid orders without approval

## Cashier Access Rule (Critical)
Cashier-style final checkout permission is allowed only when:
1. Product handover/delivery is confirmed, and
2. Payment confirmation exists (cash recorded or QR/bank settlement reference).

This prevents fake completion and protects income accuracy.

## Monetization Controls by System
- Delivery charge auto-applied for orders below NPR 10,000.
- Earnings computed from configured formulas only.
- Wholesale incentive tier (25%-50%) computed monthly from realized value.
- Returns/cancellations automatically reverse affected earning entries.

## Admin Monitoring (MVP Essentials)

Live panels:
- Sales by agent
- Settled payments by mode (cash/QR/bank)
- Pending and overdue collections
- Delivery charge totals
- Agent earnings accrued vs settled

Audit trails required:
- Order created/edited/completed
- Payment status changes
- Settlement reference changes
- Earnings ledger create/reverse entries

## Daily Admin Checklist
1. Verify settlement totals by payment mode.
2. Verify no completed order without payment confirmation.
3. Review top pending collections.
4. Review earning reversals from returns/cancellations.

## Security and Integrity
- Role-based scoping per organization
- Immutable earning ledger entries (append-only)
- PIN or approval for destructive actions

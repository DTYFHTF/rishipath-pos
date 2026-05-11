# TC-AGENT-MVP-POS-EARNINGS

## Scope
Validate manual delivery charge flow, loyalty tier discount disablement, reward behavior, and agent earnings/settlement screen.

## Test Account
- Email: agent.test@rishipath.local
- Password: Agent@12345
- Linked Sales Agent Code: AGT-TEST-01

## Preconditions
- Migrations applied:
  - 2026_05_10_100000_create_sales_agents_table
  - 2026_05_10_100100_add_sales_agent_fields_to_sales_table
  - 2026_05_10_100200_create_sales_agent_ledgers_table
  - 2026_05_11_000001_add_sales_agent_id_to_customers_table
  - 2026_05_11_000002_add_delivery_charge_to_sales_table
  - 2026_05_11_000003_add_settlement_fields_to_sales_table
- At least 1 product variant exists and has stock.
- Test customer exists.

## Test Cases

| ID | Title | Steps | Expected Result | Priority |
|---|---|---|---|---|
| TC-AG-01 | Manual Delivery Charge Input Visible | 1. Login as test account 2. Open POS 3. Go to payment section | Delivery Charge (Manual) input is visible with default 0.00 | P1 |
| TC-AG-02 | Delivery Charge Updates Total | 1. Add product to cart 2. Note total 3. Set delivery charge = 100 | Total increases exactly by NPR 100 | P1 |
| TC-AG-03 | Delivery Charge Cannot Be Negative | 1. Enter -50 in delivery charge input | Value stored as 0; total does not reduce | P1 |
| TC-AG-04 | Delivery Charge Saved to Sale | 1. Add cart 2. Set delivery charge = 150 3. Complete paid sale 4. Open sale record | sale.delivery_charge = 150 and delivery_charge_applied = false | P1 |
| TC-AG-05 | Loyalty Tier Auto Discount Disabled | 1. Select customer with loyalty tier discount > 0 2. Add item to cart | No automatic tier % discount line is applied to total | P1 |
| TC-AG-06 | Reward Redemption Still Works | 1. Select eligible customer 2. Open rewards 3. Apply reward | Reward discount applies and total decreases accordingly | P1 |
| TC-AG-07 | Paid Sale Creates Commission Ledger | 1. Create sale linked to sales_agent_id 2. payment_status = paid 3. Save | sales_agent_ledgers has commission entry for sale_id | P1 |
| TC-AG-08 | Unpaid Sale Does Not Auto-Post Commission | 1. Create sale with payment_status != paid 2. Save | No commission ledger entry created | P1 |
| TC-AG-09 | Agent Earnings Page Loads | 1. Open Field Sales > Agent Earnings | Page loads with filters and summary cards | P1 |
| TC-AG-10 | Agent Filter Works | 1. Select Agent Test User in filter | Table rows show only selected agent's sales | P1 |
| TC-AG-11 | Settlement Confirmation Action | 1. Pick paid sale without settlement_confirmed_at 2. Click Confirm | settlement_confirmed_at is set and status chip shows Confirmed | P1 |
| TC-AG-12 | Unpaid Sale Cannot Be Confirmed | 1. Open row with payment_status != paid 2. Try confirm | Action blocked; warning shown | P1 |
| TC-AG-13 | Date Filter Range Works | 1. Set from/to dates with no data | Table empty; summary cards reflect filtered date range | P2 |
| TC-AG-14 | Delivery Charges Aggregate Correctly | 1. Create 2 sales with delivery charges 100 and 200 2. Open earnings page | Delivery Charges card shows NPR 300 | P2 |
| TC-AG-15 | Collections Aggregate from Paid Amount | 1. Create paid and pending sales 2. Open earnings page | Paid Collections includes only paid amount_paid totals | P2 |

## Non-Regression Checks
- Existing POS cash, QR, credit, split payment flows still work.
- Existing reward modal open/apply/remove still works.
- Existing visit planner page still accessible.

## Notes
- Delivery charge is now fully manual for MVP safety (thin margin).
- Loyalty points and rewards remain global per organization.
- Tier automatic discount in cart has been intentionally disabled.

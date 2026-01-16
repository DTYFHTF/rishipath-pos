# Inventory Management System

This document describes the inventory management system implemented in RishiPath POS, similar to Swipe's approach.

## Overview

The system provides complete inventory tracking with:
- **Purchases** - Record goods received from suppliers
- **Sales** - Automatic stock deduction with full audit trail
- **Stock Adjustments** - Manual corrections with reason tracking
- **Stock Transfers** - Move inventory between stores/warehouses
- **Supplier Ledger** - Track payables and payments
- **Reports** - Stock valuation, dead stock, and movement history

## Architecture

### Core Tables

| Table | Purpose |
|-------|---------|
| `stock_levels` | Current stock per product variant per store |
| `inventory_movements` | Audit trail of every stock change |
| `purchases` | Purchase orders from suppliers |
| `purchase_items` | Line items in purchases |
| `supplier_ledger_entries` | Supplier accounts payable/receivable |

### InventoryService

The `App\Services\InventoryService` class is the central point for all stock operations:

```php
// Increase stock (e.g., purchase)
InventoryService::increaseStock($variantId, $storeId, $quantity, 'purchase', 'Purchase', $purchaseId);

// Decrease stock (e.g., sale)
InventoryService::decreaseStock($variantId, $storeId, $quantity, 'sale', 'Sale', $saleId);

// Transfer between stores
InventoryService::transferStock($variantId, $fromStoreId, $toStoreId, $quantity, 'Transfer note');

// Get current stock
$stock = InventoryService::getStock($variantId, $storeId);

// Get movement history
$history = InventoryService::getMovementHistory($variantId, $storeId);
```

### Movement Types

| Type | Description |
|------|-------------|
| `purchase` | Stock received from supplier |
| `sale` | Stock sold to customer |
| `adjustment` | Manual stock correction |
| `transfer` | Moved between stores |
| `damage` | Damaged/spoiled goods |
| `return` | Customer or supplier return |

## Filament Admin Pages

### Inventory Management

| Page | Path | Description |
|------|------|-------------|
| **Inventory List** | `/admin/inventory-list` | View all stock with Stock In/Out actions |
| **Stock Adjustment** | `/admin/stock-adjustment` | Manual stock corrections |
| **Stock Transfer** | `/admin/stock-transfer` | Transfer between stores |
| **Purchases** | `/admin/purchases` | Create and manage purchase orders |

### Reports

| Report | Path | Description |
|--------|------|-------------|
| **Stock Valuation** | `/admin/stock-valuation-report` | Cost/sale value, dead stock analysis |
| **Supplier Ledger** | `/admin/supplier-ledger-report` | Payables and payment history |
| **Inventory Turnover** | `/admin/inventory-turnover-report` | ABC analysis, fast/slow movers |

## Purchase Workflow

### 1. Create Purchase Order

```
Admin → Purchases → Create
├── Select supplier
├── Add items with quantities and costs
├── Set expected delivery date
└── Save as draft
```

### 2. Receive Goods

```
Admin → Purchases → [View Order] → Receive
├── Stock levels automatically increased
├── Inventory movements created
├── Supplier ledger entry created (payable)
└── Variant cost prices updated
```

### 3. Record Payment

```
Admin → Purchases → [View Order] → Record Payment
├── Enter amount and payment method
├── Supplier balance reduced
├── Ledger entry created (payment)
└── Payment status updated
```

## Stock In/Out Quick Actions

From the Inventory List page:

- **Stock In (+)** - Add stock with reason (purchase, return, adjustment)
- **Stock Out (-)** - Remove stock with reason (damage, adjustment, etc.)
- **Timeline (📜)** - View complete movement history for the item

## Supplier Ledger

Each supplier has a running balance:

- **Purchases** increase the payable balance
- **Payments** decrease the payable balance
- **Returns** decrease the payable balance

View the Supplier Ledger Report for:
- Total payable across all suppliers
- Individual supplier balances
- Transaction history

## Stock Valuation Report

Provides insights into:
- Total stock cost value vs potential sale value
- Category-wise value breakdown
- Top 10 items by stock value
- Dead stock (no sales in 30 days)

## Integration with POS

The POS system automatically:
1. Decreases stock when a sale is completed
2. Creates an `inventory_movement` record with type `sale`
3. Links the movement to the `Sale` record

## Permissions Required

| Permission | Access |
|------------|--------|
| `view_inventory` | View Inventory List |
| `adjust_stock` | Stock In/Out and Adjustments |
| `transfer_stock` | Stock Transfers |
| `view_reports` | All reports |

## Testing

Run the test script to verify the system:

```bash
php test_inventory_system.php
```

This tests:
- InventoryService stock increase/decrease
- Purchase creation and receiving
- Supplier ledger entries
- Payment recording
- Stock transfers

## Database Schema

### purchases
- `id`, `organization_id`, `store_id`, `supplier_id`
- `purchase_number` (unique, auto-generated)
- `purchase_date`, `expected_delivery_date`, `received_date`
- `status` (draft, ordered, partial, received, cancelled)
- `subtotal`, `tax_amount`, `discount_amount`, `shipping_cost`, `total`
- `amount_paid`, `payment_status` (unpaid, partial, paid)
- `supplier_invoice_number`, `invoice_file`, `notes`
- `created_by`, `received_by`, `created_at`, `updated_at`

### purchase_items
- `id`, `purchase_id`, `product_variant_id`
- `product_name`, `product_sku`
- `quantity_ordered`, `quantity_received`, `unit`
- `unit_cost`, `tax_rate`, `tax_amount`, `discount_amount`, `line_total`
- `batch_id`, `expiry_date`, `notes`

### supplier_ledger_entries
- `id`, `organization_id`, `supplier_id`, `purchase_id`
- `type` (purchase, payment, return, adjustment)
- `amount`, `balance_after`
- `payment_method`, `reference_number`, `notes`
- `created_by`, `created_at`

### inventory_movements (existing, enhanced)
- `id`, `organization_id`, `store_id`, `product_variant_id`, `batch_id`
- `type` (purchase, sale, adjustment, transfer, damage, return)
- `quantity`, `unit`, `from_quantity`, `to_quantity`
- `reference_type`, `reference_id`
- `cost_price`, `user_id`, `notes`, `created_at`

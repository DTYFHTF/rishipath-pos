# Inventory Management System - User Guide

## Overview
The Rishipath POS inventory management system provides comprehensive tracking of stock across multiple stores, with full audit trails and automated stock movements.

## Core Concepts

### Inventory Service
All stock changes go through `InventoryService.php`, which ensures:
- Accurate stock tracking at store level
- Immutable audit trail via `InventoryMovement` records
- Validation (e.g., cannot decrease below zero)
- Consistent timestamps and user tracking

### Stock Levels
- Each product variant has separate stock per store
- Tracks: quantity, reserved quantity, reorder level
- Updates automatically via InventoryService

### Movement Types
- **purchase**: Stock received from supplier
- **sale**: Stock sold to customer
- **transfer**: Stock moved between stores
- **adjustment**: Manual corrections (increase/decrease/set)
- **return**: Customer or supplier returns

## User Workflows

### 1. Receiving Purchases

**Navigation**: Inventory → Purchases

1. Open a purchase order with status "Ordered"
2. Click "Receive" action
3. Enter quantity received (can be partial)
4. Stock automatically increases for the specified store
5. Status updates to "Received" when fully received
6. Supplier ledger updated with payable

**Behind the scenes**:
```php
$purchase->receive($quantityReceived);
// → Calls InventoryService::increaseStock()
// → Creates InventoryMovement audit record
// → Updates StockLevel
```

### 2. Stock Transfers

**Navigation**: Inventory → Stock Transfer

1. Select product variant
2. Choose "From Store" and "To Store"
3. Enter quantity (validated against available stock)
4. Add notes (optional)
5. Submit transfer

**Confirmation**: Large transfers (>100 units) require confirmation to prevent mistakes.

**Result**: Stock decreases in source store, increases in destination store. Single atomic transaction.

### 3. Stock Adjustments

**Navigation**: Inventory → Stock Adjustment

**Use cases**:
- Physical inventory counts revealing discrepancies
- Damaged/expired stock write-offs
- Shrinkage corrections

**Process**:
1. Select product variant and store
2. Choose adjustment type:
   - **Increase**: Add quantity
   - **Decrease**: Subtract quantity
   - **Set**: Set to exact quantity
3. Select reason (required): Damaged, Expired, Physical Count, etc.
4. Add notes explaining adjustment
5. Preview: Shows current → new stock level
6. Submit

**Audit**: All adjustments appear in the "Recent Adjustments" panel with filters by date range.

### 4. Point of Sale

**Navigation**: POS

- Products added to cart automatically check stock availability
- On sale completion:
  - Stock decreases via `InventoryService::decreaseStock()`
  - InventoryMovement records created
  - Sale and payment recorded

**Multi-session**: Up to 5 cart sessions can be active, allowing cashiers to park transactions and serve multiple customers.

## Reports

### Stock Valuation Report

**Navigation**: Reports → Stock Valuation

**Features**:
- Real-time valuation of inventory
- Summary metrics: total cost value, sale value, potential profit, margin %
- Breakdown by category
- Detailed item-level listing
- CSV export for Excel/accounting software

**Filters**:
- Store (or all stores)
- Category
- As of Date

**Use case**: End-of-month inventory valuation, financial reporting.

### Supplier Ledger Report

**Navigation**: Reports → Supplier Ledger

**Shows**:
- Outstanding payables per supplier
- Transaction history (purchases, payments)
- Running balance
- Overdue amounts

### Inventory Turnover Report

**Navigation**: Reports → Inventory Turnover

**Metrics**:
- Turnover ratio (COGS / Avg Inventory)
- Days in inventory
- Fast movers vs slow movers
- Dead stock identification (no sales in X days)

## Best Practices

1. **Always use InventoryService methods** — Never manually update `stock_levels.quantity`
2. **Receive purchases promptly** — Unreceived purchases don't reflect in stock
3. **Regular physical counts** — Use Stock Adjustment to reconcile
4. **Document adjustments** — Required reason and notes for audit trail
5. **Review dead stock monthly** — Identify slow movers for promotions
6. **Set reorder levels** — Get low-stock alerts before stockouts

## Keyboard Shortcuts (POS)

- `F1`: New cart session
- `F2`: Park current session
- `F8`: Complete sale
- `F9`: Clear cart
- `/`: Focus search
- `Esc`: Clear search

## API Integration

For programmatic access:

```php
use App\Services\InventoryService;

$service = app(InventoryService::class);

// Check stock
$qty = $service->getStock($variantId, $storeId);

// Increase stock
$service->increaseStock(
    productVariantId: $variantId,
    storeId: $storeId,
    quantity: 50,
    type: 'adjustment',
    referenceType: 'API',
    referenceId: null,
    notes: 'Inventory sync',
    userId: auth()->id()
);

// Decrease stock (throws exception if insufficient)
$service->decreaseStock(...);

// Transfer between stores
$service->transferStock($variantId, $fromStore, $toStore, $qty, $notes, $userId);
```

## Troubleshooting

**Stock showing incorrect quantity?**
- Check InventoryMovement records for audit trail
- Run physical count and use Stock Adjustment to correct
- Verify all purchases are marked "Received"

**Cannot decrease stock?**
- Error: "Insufficient stock" — Current qty < requested qty
- Check if stock is reserved for other orders
- Verify correct store is selected

**Transfer failed?**
- Ensure "From Store" has sufficient stock
- Verify stores are different
- Check network/database connection

## Support

For technical issues or feature requests, contact your system administrator or refer to the developer documentation in `docs/rishipath-pos-architecture.md`.

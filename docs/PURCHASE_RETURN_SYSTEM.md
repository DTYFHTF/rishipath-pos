# Purchase Return System Implementation

## Overview

A comprehensive partial purchase return system has been implemented, allowing you to handle returns of purchased items with full validation and audit trail.

## Key Features

1. **Partial Returns**: Return any quantity up to the received quantity
2. **Validation**: Prevents over-returning (cannot return more than received)
3. **FIFO Allocation**: Returns are allocated from oldest batches first
4. **Batch Tracking**: Updates ProductBatch quantities (remaining/returned)
5. **Stock Updates**: Automatically reduces StockLevel quantities
6. **Supplier Ledger**: Creates credit entries to reduce payables
7. **Audit Trail**: Full InventoryMovement records for traceability
8. **Return Numbers**: Auto-generated unique return numbers (STORE-RET-XXXXXX)

## Database Schema

### `purchase_returns` Table
- `return_number`: Unique identifier (auto-generated)
- `purchase_id`, `purchase_item_id`: Links to original purchase
- `product_variant_id`, `batch_id`: Product and batch references
- `quantity_returned`: Number of units returned
- `unit_cost`, `return_amount`: Financial tracking
- `reason`: Return reason (Defective, Damaged, Wrong Product, etc.)
- `notes`: Additional details
- `status`: pending/approved/refunded
- `created_by`, `approved_by`: User tracking
- Timestamps for audit

## Implementation

### Backend Components

1. **Model**: `app/Models/PurchaseReturn.php`
   - Auto-generates return numbers on creation
   - Full relationships to Purchase, PurchaseItem, ProductVariant, Batch, Store
   - Tracks creator and approver

2. **Business Logic**: `app/Models/Purchase.php`
   - `processReturn(array $returnItems, string $reason, ?string $notes)`
   - Validates return quantities
   - Checks for existing returns
   - Allocates returns using FIFO
   - Updates all related entities in transaction
   - Returns array of PurchaseReturn records

### Filament UI

**Location**: Purchase View Page (admin/purchases/{id})

**Return Action Button**:
- Visible only when: Purchase status = 'received' AND items have quantities available to return
- Opens modal with:
  - Input fields for each returnable item (shows received qty, already returned qty, available qty)
  - Return reason dropdown (Defective, Damaged, Wrong Product, Expired, Poor Quality, Overstocked, Other)
  - Notes textarea for additional details
  - Validation: Cannot enter more than available quantity

**Returns Section**:
- Shows history of all returns for the purchase
- Displays: Return #, Date, Product, Quantity, Amount, Reason, Status, Notes
- Collapsible section (only visible if returns exist)

## Usage Flow

### 1. Navigate to Purchase
- Go to admin/purchases
- Click on a received purchase to view details

### 2. Initiate Return
- Click "Process Return" button (red, with arrow icon)
- Modal opens showing all returnable items

### 3. Enter Return Details
- For each item you want to return:
  - Enter quantity (system shows: "Received: X, Returned: Y, Available: Z")
  - Cannot exceed available quantity (validated)
- Select reason from dropdown
- Add notes (optional)
- Click confirm

### 4. System Processing
The system automatically:
1. Validates quantities (cannot exceed received or available)
2. Finds appropriate batches (FIFO - oldest first)
3. Updates ProductBatch:
   - Decreases `quantity_remaining`
   - Increases `quantity_returned`
   - Appends notes with return date
4. Creates InventoryMovement records (type='return', negative quantity)
5. Updates StockLevel (reduces quantity)
6. Creates PurchaseReturn record(s) for each batch
7. Creates SupplierLedgerEntry (type='return', negative amount to credit supplier)
8. Shows success notification with total returned qty and amount

### 5. View Return History
- Scroll to "Returns" section on purchase view page
- See all return records with complete details

## Validations

### Built-in Checks
1. **Quantity Validation**: `return_qty <= quantity_received`
2. **Duplicate Return Prevention**: Checks existing returns, only allows returning `quantity_received - already_returned`
3. **Batch Availability**: Ensures sufficient batch quantities exist
4. **Transaction Safety**: All updates wrapped in DB transaction (rollback on any error)

### Error Messages
- "Cannot return {X} units of {product}. Only {Y} units were received."
- "Only {Z} units available for return (already returned {W})."
- "Could not allocate all return quantity. Insufficient batch quantities available."

## Example Scenarios

### Scenario 1: Simple Return
```
Purchase: PUR-MAIN-000123
- Item: Turmeric Powder 500g × 100 qty @ ₹10 = ₹1000
- Received: 100 qty

Return Process:
- Return 20 qty due to "Defective"
- Result:
  - Batch quantity_remaining: 100 → 80
  - Batch quantity_returned: 0 → 20
  - Stock level: 100 → 80
  - Supplier payable: -₹200 (credited)
  - Return record: MAIN-RET-000001
```

### Scenario 2: Partial Returns Over Time
```
Purchase: PUR-MAIN-000124
- Item: Ashwagandha Tablets × 100 qty
- Received: 100 qty

First Return (Day 1):
- Return 30 qty → Available: 70 qty

Second Return (Day 3):
- Try to return 50 qty → ❌ Error: "Only 70 units available for return (already returned 30)"
- Return 40 qty → ✅ Success, Available: 30 qty
```

### Scenario 3: Multi-Batch FIFO
```
Purchase: PUR-MAIN-000125
- Batch 1 (older): 50 qty remaining
- Batch 2 (newer): 30 qty remaining
- Total available: 80 qty

Return 60 qty:
- Allocates 50 from Batch 1 (oldest) ← FIFO
- Allocates 10 from Batch 2
- Creates 2 return records (one per batch)
```

### Scenario 4: Multiple Items
```
Purchase: PUR-MAIN-000126
- Item 1: Brahmi Syrup × 100 @ ₹20 = ₹2000
- Item 2: Neem Oil × 50 @ ₹15 = ₹750

Return:
- Item 1: 20 qty = ₹400
- Item 2: 10 qty = ₹150
- Total return amount: ₹550 (credited to supplier)
```

## Technical Details

### Return Number Format
`{STORE_CODE}-RET-{SEQUENCE}`
- Example: `MAIN-RET-000001`, `BRN1-RET-000012`
- Auto-increments per store
- Generated on PurchaseReturn creation

### FIFO Batch Allocation
```php
// Returns are allocated from oldest batches first:
$batches = ProductBatch::where('purchase_id', $this->id)
    ->where('product_variant_id', $variant_id)
    ->where('quantity_remaining', '>', 0)
    ->orderBy('created_at', 'asc')  // ← FIFO: oldest first
    ->lockForUpdate()
    ->get();
```

### Supplier Ledger Integration
```php
// Reduces supplier payable balance:
SupplierLedgerEntry::createReturnEntry(
    $purchase,
    $returnAmount,  // Total of all returns
    "Purchase return: {$reason}"
);
```

### Inventory Movement Audit
```php
InventoryMovement::create([
    'type' => 'return',
    'quantity' => -$returnFromBatch,  // Negative for reduction
    'reference_type' => 'PurchaseReturn',
    'reference_id' => $purchase->id,
    'notes' => "Purchase return: {$reason}",
]);
```

## Data Relationships

```
Purchase
  ├── PurchaseItems
  │     └── ProductVariant
  ├── ProductBatches
  │     ├── InventoryMovements (type='purchase', 'return')
  │     └── StockLevel (computed from batches)
  ├── PurchaseReturns
  │     ├── PurchaseItem
  │     ├── ProductVariant
  │     └── ProductBatch
  ├── SupplierLedgerEntries
  │     ├── type='purchase'
  │     ├── type='payment'
  │     └── type='return' ← New
  └── Supplier
        └── current_balance (updated by ledger entries)
```

## Files Modified/Created

### Created Files
1. `database/migrations/2026_02_13_233610_create_purchase_returns_table.php`
2. `app/Models/PurchaseReturn.php`

### Modified Files
1. `app/Models/Purchase.php`
   - Added `returns()` relationship
   - Added `processReturn()` method

2. `app/Filament/Resources/PurchaseResource/Pages/ViewPurchase.php`
   - Added "Process Return" action button
   - Added "Returns" section to infolist
   - Added `use PurchaseReturn` import

## Testing

### Manual Testing Steps
1. Create a new purchase order
2. Add items (e.g., 100 units of a product)
3. Receive the purchase
4. Verify batch created with 100 units
5. Click "Process Return"
6. Enter return quantity (e.g., 20 units)
7. Select reason and add notes
8. Submit
9. Verify:
   - Success notification appears
   - Batch quantity_remaining = 80
   - Batch quantity_returned = 20
   - Stock level reduced by 20
   - Return record appears in "Returns" section
   - Supplier ledger has return entry (negative amount)
   - Supplier current_balance reduced

### Edge Case Testing
1. **Over-Return**: Try to return 120 when only 100 received → Should error
2. **Duplicate Return**: Return 80, then try to return 30 → Should error (only 20 available)
3. **Zero Quantity**: Enter 0 for all items → Should show warning "No items to return"
4. **Non-Received Purchase**: Return button should not appear on draft/ordered purchases

## Future Enhancements

Potential improvements:
1. **Approval Workflow**: Add approval step for large returns
2. **Return Labels**: Print return labels with barcodes
3. **Refund Processing**: Track refund status separately
4. **Supplier Notifications**: Auto-email supplier when return is processed
5. **Return Analytics**: Reports on return rates by supplier/product
6. **Return Reasons Analysis**: Track most common return reasons
7. **Partial Item Returns**: Allow returning specific units from specific batches manually

## Troubleshooting

### Return Button Not Visible
- Check purchase status = 'received'
- Check at least one item has quantity_received > 0
- Check items haven't been fully returned already

### Cannot Return Quantity
- Verify quantity_received >= return_qty
- Check existing returns: `Purchase.returns()->sum('quantity_returned')`
- Verify batch has sufficient quantity_remaining

### Stock Not Updating
- Check ProductBatchObserver is registered in EventServiceProvider
- Verify StockLevel record exists
- Check InventoryMovement created successfully

### Supplier Balance Not Updating
- Verify supplier_id is set on purchase
- Check SupplierLedgerEntry created with type='return'
- Verify amount is negative (credit)

## Security & Permissions

- Returns require authentication (Auth::id())
- User ID tracked in `created_by`, `approved_by` fields
- Timestamps track when return occurred
- Full audit trail via InventoryMovement
- Transaction safety prevents partial updates on error

## Summary

The purchase return system is fully functional with:
- ✅ Complete database schema
- ✅ Backend business logic with validation
- ✅ Filament UI integration
- ✅ FIFO batch allocation
- ✅ Stock and ledger updates
- ✅ Full audit trail
- ✅ Auto-generated return numbers
- ✅ Multi-item support
- ✅ Error handling

You can now process purchase returns directly from the Filament admin panel with full traceability and automatic updates to inventory and supplier payables.

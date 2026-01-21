# Inventory & Batch Management Model

## Overview

The system uses **TWO tables working together**:
- `stock_levels` = **Quick summary** (total stock per variant per store)
- `product_batches` = **Detailed breakdown** (expiry, supplier, batch tracking)

Think of it like a bank account:
- `stock_levels.quantity` = Your account balance (quick check)
- `product_batches` = Individual deposits with dates and sources

## Key Fields Explained

### stock_levels Table
```
quantity            → Total available stock (THIS IS YOUR "CURRENT STOCK")
reserved_quantity   → Stock held for pending orders (not implemented yet)
reorder_level       → Minimum stock before alert
last_movement_at    → Last time stock changed
```

**What you see:** When you look at products, you see `stock_levels.quantity`

### product_batches Table
```
quantity_received     → How much came in this batch (never changes)
quantity_remaining    → How much is LEFT in this batch (THIS DECREASES ON SALE)
quantity_sold         → How much sold from this batch (auto-calculated)
quantity_damaged      → Damaged/expired items removed
quantity_returned     → Returned to supplier
purchase_price        → What we paid per unit for this batch
expiry_date           → When this batch expires
supplier_id           → Where it came from
```

**Formula:** `quantity_received = quantity_remaining + quantity_sold + quantity_damaged + quantity_returned`

## How It Works

### When You Receive New Stock (Purchase)
1. Create a new `product_batch` record:
   - Set `quantity_received = 100`
   - Set `quantity_remaining = 100`
   - Set `purchase_price`, `supplier_id`, `expiry_date`
2. Auto-update `stock_levels.quantity` += 100
3. Create `inventory_movements` record (audit trail)

### When You Make a Sale
1. System finds oldest expiring batch with `quantity_remaining > 0` (FIFO)
2. Deduct from that batch:
   - `batch.quantity_remaining` -= sold amount
   - `batch.quantity_sold` += sold amount
3. Auto-update `stock_levels.quantity` -= sold amount
4. Create `inventory_movements` record with batch_id

### When Items Expire/Damage
1. Update the specific batch:
   - `batch.quantity_remaining` -= damaged amount
   - `batch.quantity_damaged` += damaged amount
2. Auto-update `stock_levels.quantity` -= damaged amount
3. Create `inventory_movements` record

## What You Need to Know

### Viewing Inventory
**Quick View (Stock Levels page):**
- Shows `stock_levels.quantity` per variant
- This is your "Current Stock" - **LOOK HERE FIRST**

**Detailed View (Batch Details):**
- Click on a product to see all batches
- Shows expiry dates, suppliers, remaining quantities
- Use this to:
  - Track expiring items
  - See which supplier a batch came from
  - Check damaged/returned quantities
  - Calculate inventory value (remaining × purchase_price)

### During POS Sale
- **You don't choose the batch** - system auto-picks oldest expiring (FIFO)
- You only check if `stock_levels.quantity >= sale quantity`
- System handles batch allocation automatically

### Barcodes
- **Barcodes are per VARIANT, not per batch**
- One SKU (RSH-TAI-075-10ML) can have multiple batches
- Scanning barcode → finds variant → deducts from oldest batch

## Common Questions

**Q: Which quantity do I look at to know current stock?**  
A: `stock_levels.quantity` - This is your single source of truth for "how many can I sell"

**Q: Do batches and inventory manage different stocks?**  
A: No! They're the **same stock**. `stock_levels.quantity` should always equal the sum of all `batch.quantity_remaining` for that variant/store.

**Q: Is quantity_sold updated automatically?**  
A: Yes! When a sale happens, the system:
1. Finds the best batch (oldest expiring)
2. Updates `quantity_remaining` (decreases)
3. Updates `quantity_sold` (increases)
4. Updates `stock_levels.quantity` (decreases)

**Q: How do I know inventory value?**  
A: Per batch: `quantity_remaining × purchase_price`  
Total for variant: Sum all batches' values

**Q: What if I don't have a batch number when receiving stock?**  
A: System auto-generates one (like "ABC12345"). Just fill in expiry, supplier, and quantity.

**Q: Can stock_levels and batches get out of sync?**  
A: Technically yes if there's a bug, but the system has safeguards:
- `InventoryService::syncBatchQuantities()` re-syncs them
- All updates go through `InventoryService` which keeps them in sync
- Run this to check: `php artisan inventory:check-sync` (TODO: create this command)

## Workflow Summary

### Receiving Stock
1. Go to "Product Batches" → "New Batch"
2. Fill in:
   - Product Variant (required)
   - Quantity Received (required)
   - Purchase Price (optional, defaults to variant cost_price)
   - Supplier (optional but recommended)
   - Expiry Date (optional but recommended)
   - Batch Number (auto-generated if blank)
3. Save → Both batch and stock_levels updated automatically

### Making a Sale (POS)
1. Scan product or search by name
2. Check available quantity (shows `stock_levels.quantity`)
3. Add to cart
4. Complete sale → System automatically:
   - Finds oldest expiring batch
   - Deducts from that batch
   - Updates stock_levels
   - Records in inventory_movements

### Checking Inventory
1. Quick check: "Inventory" page shows total stock per variant
2. Detailed check: Click variant → See all batches with:
   - Batch number, expiry date, supplier
   - Quantities (received, remaining, sold, damaged)
   - Value (remaining × purchase_price)

### Handling Expired Items
1. Go to "Product Batches"
2. Filter by "Expired" or "Expiring Soon"
3. For each expired batch:
   - Record as damaged: Update `quantity_damaged`
   - Or return to supplier: Update `quantity_returned`
4. Stock_levels auto-updates

## Technical Notes

### Data Integrity Rules
- `stock_levels.quantity` = SUM(`batch.quantity_remaining`) for same variant/store
- `batch.quantity_received` = `quantity_remaining + quantity_sold + quantity_damaged + quantity_returned`
- Stock cannot go negative (throws exception)

### FIFO Allocation
Sales always deduct from the batch with:
1. Earliest expiry date (if set)
2. Oldest manufactured date (if set)
3. Lowest ID (oldest created batch)

This ensures older stock sells first.

### Audit Trail
Every stock change creates an `inventory_movements` record showing:
- What changed (quantity, from/to amounts)
- Why (type: sale, purchase, adjustment, etc.)
- When (timestamp)
- Who (user_id)
- Which batch (batch_id)
- Reference (Sale ID, Purchase ID, etc.)

## Future Enhancements

- [ ] Visual inventory dashboard with batch expiry timeline
- [ ] Automated alerts for expiring batches (7 days, 30 days)
- [ ] Batch merge functionality (combine small batches)
- [ ] Average cost calculation across batches
- [ ] `php artisan inventory:check-sync` command to find/fix mismatches
- [ ] Reserved quantity for pending orders
- [ ] Batch QR codes for warehouse picking

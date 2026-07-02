# ProductBatch & StockLevel Integration Guide

## Architecture Overview

### Two-Tier Inventory System

The system uses a **two-tier inventory architecture** for optimal performance and detailed tracking:

```
┌─────────────────┐         ┌──────────────┐         ┌─────────┐
│  ProductBatch   │────┬───▶│  StockLevel  │────────▶│   POS   │
│ (Detail Layer)  │    │    │ (Cache Layer)│         │ (Sells) │
└─────────────────┘    │    └──────────────┘         └─────────┘
                       │
                  Auto-Sync
                  (Observer)
```

---

## 1. ProductBatch (Detail Layer)

**Purpose:** Tracks individual inventory batches with full purchase and lifecycle details.

### What It Stores:
- **Batch identification:** `batch_number`, `store_id`
- **Purchase details:** `supplier_id`, `purchase_price`, `purchase_date`
- **Product traceability:** `manufactured_date`, `expiry_date`
- **Quantity tracking:**
  - `quantity_received` - Initial batch size
  - `quantity_remaining` - Current stock in this batch
  - `quantity_sold` - Sold from this batch
  - `quantity_damaged` - Lost to damage
  - `quantity_returned` - Returned to supplier

### When To Use:
- ✅ Recording new inventory purchases
- ✅ Tracking batch expiry dates (FEFO/FIFO)
- ✅ Calculating inventory value by purchase price
- ✅ Supplier reconciliation
- ✅ Damage/loss tracking per batch

### Example:
```
Batch #: BATCH-2026-001
Product: Triphala Churna 500g
Supplier: Ayurvedic Wholesalers
Purchase Price: ₹45.00
Received: 600 units
Remaining: 562 units
Expiry: 2027-12-31
```

---

## 2. StockLevel (Cache/Performance Layer)

**Purpose:** Fast aggregated stock count per variant per store for **real-time POS operations**.

### What It Stores:
- `product_variant_id` + `store_id` (unique combination)
- `quantity` - Total available stock (sum of all batches)
- `reserved_quantity` - Stock held for pending orders
- `reorder_level` - Minimum stock trigger
- `last_movement_at` - Last stock change timestamp

### Why It Exists:
1. **Performance:** POS doesn't need to SUM all batches on every sale
2. **Quick checks:** Instant "insufficient stock" validation
3. **Locking:** Prevents overselling with row-level locks during transactions
4. **Alerts:** Fast low-stock detection for dashboards

### When To Use:
- ✅ POS stock availability checks
- ✅ Dashboard widgets (inventory value, low stock)
- ✅ Stock reservations (online orders)
- ✅ Reorder alerts

### Example:
```
Variant: Triphala Churna 500g (SKU: SHU-PRD-00001-500GMS)
Store: Kathmandu Store
Total Quantity: 562 units (aggregated from all non-expired batches)
Reserved: 0
Reorder Level: 10
```

---

## 3. How They Work Together

### Data Flow

#### A. Creating New Inventory (Purchase)
```
1. Admin creates ProductBatch in Filament
   ├─ Batch #: BATCH-001
   ├─ Quantity: 600
   └─ Store: Kathmandu

2. ProductBatchObserver fires (created event)
   └─ Calculates: SUM(quantity_remaining) for variant+store

3. StockLevel automatically updated/created
   └─ Sets quantity = 600
```

#### B. Making a Sale (POS)
```
1. Cashier scans product in POS

2. POS checks StockLevel for current store
   ├─ SELECT quantity FROM stock_levels
   │  WHERE product_variant_id = ? AND store_id = ?
   └─ If quantity >= requested: ✅ Allow sale
      If quantity < requested: ❌ "Insufficient Stock"

3. InventoryService.adjustStock() called
   ├─ Decrements StockLevel.quantity
   ├─ Updates ProductBatch quantities (FIFO/FEFO)
   └─ Creates InventoryMovement audit record

4. Observer auto-syncs in case of batch changes
```

#### C. Batch Expiry/Damage
```
1. Admin marks batch damaged or expired
   └─ Updates ProductBatch.quantity_remaining

2. Observer recalculates StockLevel
   └─ Ensures POS sees updated available stock
```

---

## 4. The Problem You Experienced

### What Happened:
```
❌ Kathmandu Store:
   - ProductBatches: 44 records, ₹28,11,860 value
   - StockLevels: 0 records
   
Result: POS showed "Insufficient Stock" for everything
```

### Why It Happened:
- Seeder created `ProductBatch` records
- But did NOT create corresponding `StockLevel` records
- POS only checks `StockLevel` (for performance)
- So it thought stock was 0 everywhere

### The Fix:
1. **Created command:** `php artisan stock:sync`
   - Scans all ProductBatches
   - Calculates totals per variant+store
   - Creates/updates StockLevels

2. **Created observer:** `ProductBatchObserver`
   - Automatically syncs StockLevels when batches change
   - Ensures consistency going forward

3. **Result:**
   ```
   ✅ Synced 162 StockLevel records
   ✅ Kathmandu now has 32 StockLevels with 11,488 total units
   ✅ POS can now sell products
   ```

---

## 5. Commands & Maintenance

### Sync Stock Levels
```bash
# Sync all stores
php artisan stock:sync

# Sync specific store only
php artisan stock:sync --store=5

# Force update existing stock levels
php artisan stock:sync --force
```

### When To Run:
- ✅ After initial seeding
- ✅ After data migration
- ✅ If stock levels become out of sync
- ✅ During system recovery

---

## 6. Best Practices

### Creating New Inventory
1. Always create `ProductBatch` first (with purchase details)
2. Observer will auto-create/update `StockLevel`
3. Never manually edit `StockLevel.quantity` (use `InventoryService`)

### Making Sales
1. POS uses `StockLevel` for validation
2. `InventoryService.adjustStock()` updates both layers
3. Creates full audit trail in `InventoryMovement`

### Handling Discrepancies
```bash
# If POS shows wrong stock:
php artisan stock:sync --store=<id> --force

# Check for mismatches:
SELECT 
    pb.store_id,
    pb.product_variant_id,
    SUM(pb.quantity_remaining) as batch_total,
    sl.quantity as stock_level
FROM product_batches pb
LEFT JOIN stock_levels sl 
    ON pb.product_variant_id = sl.product_variant_id 
    AND pb.store_id = sl.store_id
GROUP BY pb.store_id, pb.product_variant_id, sl.quantity
HAVING batch_total != sl.quantity;
```

---

## 7. Database Schema

### product_batches
```sql
- id
- product_variant_id (FK)
- store_id (FK)
- batch_number (unique per store+variant)
- supplier_id (FK, nullable)
- purchase_price
- purchase_date
- manufactured_date
- expiry_date
- quantity_received
- quantity_remaining  ← Decremented on sale
- quantity_sold
- quantity_damaged
- quantity_returned
- notes
```

### stock_levels
```sql
- id
- product_variant_id (FK)
- store_id (FK)
- quantity  ← Checked by POS
- reserved_quantity
- reorder_level
- last_counted_at
- last_movement_at
- UNIQUE(product_variant_id, store_id)
```

### inventory_movements (audit trail)
```sql
- id
- organization_id
- store_id
- product_variant_id
- batch_id (nullable)
- type (purchase, sale, adjustment, damage, return)
- quantity
- from_quantity
- to_quantity
- reference_type (Sale, Purchase, etc.)
- reference_id
- cost_price
- user_id
- notes
- created_at
```

---

## 8. Key Takeaways

✅ **ProductBatch = Source of Truth** for inventory details  
✅ **StockLevel = Performance Cache** for POS operations  
✅ **Observer keeps them synced** automatically  
✅ **Command fixes bulk sync issues** when needed  
✅ **InventoryService = Single entry point** for stock changes  

**Never modify stock directly** - always use `InventoryService.adjustStock()` for full audit trail and consistency.

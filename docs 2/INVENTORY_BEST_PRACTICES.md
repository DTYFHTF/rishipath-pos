# Inventory Management Best Practices

## The Problem: Multiple Entry Points = Data Inconsistency

### ❌ OLD APPROACH (Error-Prone)
```
Purchase → InventoryService → StockLevel (no batch tracking)
Manual Batch Creation → ProductBatch → Observer → StockLevel
Stock Adjustments → Direct StockLevel changes
```

**Issues:**
- ❌ No single source of truth
- ❌ Lost traceability (which stock came from which purchase?)
- ❌ Manual batch creation causes human error
- ❌ Difficult to track expiry dates
- ❌ Hard to reconcile with supplier invoices

---

## ✅ NEW APPROACH: Single Entry Point (Industry Standard)

### The Golden Rule
> **ALL inventory increases MUST go through Purchase Orders and create ProductBatches**

```
┌─────────────────────────────────────────────────────┐
│                SINGLE ENTRY POINT                   │
│                                                     │
│  Purchase Order (PO) → Mark as "Received"          │
│         ↓                                           │
│  Creates ProductBatch (with full details)          │
│         ↓                                           │
│  Observer auto-syncs StockLevel                    │
│         ↓                                           │
│  POS can sell                                       │
└─────────────────────────────────────────────────────┘
```

### How Popular POS Systems Handle This

#### 1. **Square POS / Shopify POS**
- Purchase Orders are the ONLY way to add inventory
- Direct stock adjustments are for corrections only (marked in audit log)
- Every item has a "received date" and "supplier" tracked

#### 2. **Lightspeed / Clover**
- Purchase receiving creates batch/lot records automatically
- Manual adjustments require manager approval + reason
- Variance reports show discrepancies

#### 3. **Toast POS / Revel Systems**
- All receiving goes through PO workflow
- Batch numbers auto-generated from PO
- Expiry dates mandatory for perishables

---

## Recommended Workflow (Now Implemented)

### For Normal Operations

#### Adding New Inventory
```
Step 1: Create Purchase Order
  └─ Supplier: Ayurvedic Wholesalers
  └─ Items: 600 × Triphala Churna 500g @ ₹45
  └─ Status: Draft

Step 2: Mark as "Received" (when shipment arrives)
  └─ System creates ProductBatch automatically:
      ├─ Batch #: PUR-20260120-SHU-PRD-00001-001
      ├─ Supplier: Ayurvedic Wholesalers
      ├─ Quantity: 600
      ├─ Purchase Price: ₹45
      ├─ Expiry: (from PO item)
      └─ Reference: PO-KAT-000123

Step 3: Observer syncs to StockLevel
  └─ Kathmandu Store: Triphala 500g = 600 units

Step 4: Ready to sell in POS ✅
```

**Benefits:**
- ✅ Full traceability: Every batch links to a purchase
- ✅ No manual entry errors
- ✅ Expiry dates tracked automatically
- ✅ Easy supplier reconciliation
- ✅ Audit trail for compliance

---

### For Special Cases

#### Stock Adjustments (Damaged/Lost/Found Stock)
**Use Case:** Product damaged, theft, stocktake corrections

```php
// Option 1: Update existing batch
$batch = ProductBatch::find($batchId);
$batch->quantity_damaged += 10;
$batch->quantity_remaining -= 10;
$batch->save(); // Observer auto-syncs StockLevel

// Option 2: Use InventoryService for adjustments
InventoryService::adjustStock(
    variantId: $variantId,
    storeId: $storeId,
    quantityChange: -10,
    type: 'damage',
    notes: 'Damaged during delivery'
);
```

**Best Practice:** Require manager approval + reason for adjustments over threshold

---

#### Stock Transfers (Between Stores)
**Use Case:** Moving stock from Main Store → Branch

```php
InventoryService::transferStock(
    productVariantId: $variantId,
    fromStoreId: 1, // Main Store
    toStoreId: 5,   // Kathmandu
    quantity: 50,
    notes: 'Replenishment'
);
```

**Implementation:** Transfers should move actual batches (FIFO) to preserve expiry tracking

---

#### Manual Batch Creation (Rare)
**Use Case:** Initial data migration, opening stock, emergency fixes

```
⚠️  RESTRICTED ACCESS: Admin only
✅  Always require:
    - Supplier (if known)
    - Purchase price (for valuation)
    - Batch number (unique)
    - Expiry date (for perishables)
    - Reason/notes (audit trail)
```

---

## Updated System Architecture

### Inventory Entry Points (Prioritized)

| Method | Use Case | Creates Batch? | When To Use |
|--------|----------|----------------|-------------|
| **Purchase::receive()** | Normal receiving | ✅ Yes | 95% of time - PRIMARY METHOD |
| **Stock Adjustment** | Corrections | ❌ No (updates existing) | Damage, loss, stocktake |
| **Stock Transfer** | Inter-store | ⚠️ Moves batches | Replenishment |
| **Manual Batch** | Emergency | ✅ Yes | Admin only, rare cases |

### Data Flow

```
┌──────────────┐
│   Purchase   │  (PO with supplier, pricing, expiry)
└──────┬───────┘
       │ receive()
       ↓
┌──────────────┐
│ProductBatch  │  (Source of Truth - detailed tracking)
│              │  • Batch #, Supplier, Expiry
│              │  • Purchase price, quantities
└──────┬───────┘
       │ Observer (auto)
       ↓
┌──────────────┐
│ StockLevel   │  (Performance Cache - POS uses this)
│              │  • Fast stock checks
│              │  • Prevents overselling
└──────┬───────┘
       │ POS checks
       ↓
┌──────────────┐
│     Sale     │  (Transaction with customer)
└──────────────┘
       │ completed
       ↓
┌──────────────┐
│Inventory     │  (Audit Trail - compliance)
│Movement      │  • Who, when, why, how much
└──────────────┘
```

---

## Implementation Checklist

### ✅ Completed
- [x] ProductBatchObserver auto-syncs StockLevel
- [x] Purchase::receive() creates ProductBatch (not direct stock)
- [x] Batch number auto-generation from PO
- [x] Observer triggers on batch create/update/delete
- [x] Stock sync command for data recovery

### 🔄 Recommended Next Steps

#### 1. Access Control
```php
// Restrict manual batch creation to admin
ProductBatchResource::canCreate() {
    return auth()->user()->hasRole(['Admin', 'Inventory Manager']);
}

// Require approval for large adjustments
if ($quantityChange > 100) {
    Notification::make()
        ->warning()
        ->title('Large adjustment requires manager approval')
        ->send();
}
```

#### 2. Expiry Date Validation
```php
// Make expiry mandatory for perishables
Forms\Components\DatePicker::make('expiry_date')
    ->required(fn($get) => $this->isPerishable($get('product_variant_id')))
    ->minDate(now())
    ->helperText('Required for food/medicine products');
```

#### 3. Batch Tracking in Sales (FEFO/FIFO)
```php
// When selling, deduct from expiring batches first
public function deductFromBatches($variantId, $storeId, $quantity) {
    $batches = ProductBatch::where('product_variant_id', $variantId)
        ->where('store_id', $storeId)
        ->where('quantity_remaining', '>', 0)
        ->orderBy('expiry_date', 'asc') // FEFO - First Expiry First Out
        ->get();
    
    foreach ($batches as $batch) {
        if ($quantity <= 0) break;
        
        $deduct = min($quantity, $batch->quantity_remaining);
        $batch->quantity_remaining -= $deduct;
        $batch->quantity_sold += $deduct;
        $batch->save(); // Observer syncs StockLevel
        
        $quantity -= $deduct;
    }
}
```

#### 4. Low Stock Alerts (Enhanced)
```php
// Alert when approaching reorder level
StockLevel::where('quantity', '<=', 'reorder_level')
    ->where('quantity', '>', 0)
    ->with('productVariant.product')
    ->chunk(100, function($stocks) {
        foreach ($stocks as $stock) {
            Notification::make()
                ->warning()
                ->title("Low Stock: {$stock->productVariant->product->name}")
                ->body("Only {$stock->quantity} left. Reorder level: {$stock->reorder_level}")
                ->sendToDatabase(User::role('Inventory Manager')->get());
        }
    });
```

#### 5. Variance Reporting
```php
// Detect discrepancies between physical count and system
public function stocktakeVariance($storeId) {
    return StockLevel::where('store_id', $storeId)
        ->selectRaw('
            product_variant_id,
            quantity as system_count,
            (SELECT SUM(quantity_remaining) 
             FROM product_batches 
             WHERE product_variant_id = stock_levels.product_variant_id
               AND store_id = stock_levels.store_id
            ) as batch_total,
            ABS(quantity - (...)) as variance
        ')
        ->having('variance', '>', 0)
        ->get();
}
```

---

## Rules & Policies

### Golden Rules
1. **NEVER edit StockLevel.quantity directly** - use InventoryService or ProductBatch
2. **ALWAYS create batches through Purchases** - 95% of the time
3. **REQUIRE expiry dates** - for food, medicine, supplements
4. **AUDIT everything** - who, when, why, reference
5. **Manager approval** - for adjustments > threshold

### Access Levels
```
Cashier:
  - ❌ Cannot adjust stock
  - ❌ Cannot create batches
  - ✅ Can sell (POS only)

Inventory Clerk:
  - ✅ Can create Purchase Orders
  - ✅ Can receive inventory
  - ⚠️  Small adjustments only (< 10 units)
  - ❌ Cannot delete batches

Inventory Manager:
  - ✅ Full purchase workflow
  - ✅ Stock adjustments (with reason)
  - ✅ Stock transfers
  - ✅ Manual batch creation (rare)
  - ✅ Approve large adjustments

Admin:
  - ✅ Everything
  - ✅ Direct database access (emergency only)
```

---

## Testing Checklist

### After Implementation
- [ ] Create PO → Receive → Check batch created
- [ ] Verify batch auto-syncs to StockLevel
- [ ] POS can sell item (sufficient stock)
- [ ] Sale decrements StockLevel correctly
- [ ] Batch quantities update after sale
- [ ] Audit trail in InventoryMovement
- [ ] Expiring batches show warnings
- [ ] Low stock alerts trigger
- [ ] Stock transfer works between stores
- [ ] Manual adjustments log properly

---

## Summary

**Before:** Multiple paths → inconsistent data → errors  
**After:** Single entry point (Purchase) → ProductBatch → StockLevel → POS

**Key Benefits:**
- ✅ **Traceability:** Every batch links to supplier invoice
- ✅ **Accuracy:** No manual entry errors
- ✅ **Compliance:** Full audit trail for regulations
- ✅ **FEFO/FIFO:** Sell expiring items first
- ✅ **Valuation:** Accurate cost of goods sold (COGS)
- ✅ **Reconciliation:** Easy supplier statement matching

**This is how enterprise POS systems work. We've implemented industry best practices.**

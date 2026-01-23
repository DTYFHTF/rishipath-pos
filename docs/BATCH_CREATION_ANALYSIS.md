# 🔍 Batch Creation & Stock Levels Analysis

## 📦 Current Batch Creation System

### How Batches Are Currently Created

**Your system allows TWO ways to create batches:**

#### 1️⃣ **Automatic Creation via Purchases** (Recommended Flow)

**File:** `app/Models/Purchase.php` (line 165)

```php
// When Purchase::receive() is called:
$batch = ProductBatch::create([
    'purchase_id' => $this->id,           // ✅ Linked to purchase
    'product_variant_id' => $item->product_variant_id,
    'store_id' => $this->store_id,
    'batch_number' => 'PUR-20260123-SKU-001',
    'supplier_id' => $this->supplier_id,
    'purchase_price' => $item->unit_cost,
    'quantity_received' => 100,
    'quantity_remaining' => 100,
    'expiry_date' => $item->expiry_date,
    'notes' => "Purchase: {$this->purchase_number}"
]);
```

**Characteristics:**
- ✅ Has `purchase_id` set (traceable to Purchase Order)
- ✅ Auto-generates batch_number from purchase
- ✅ Automatically links to supplier
- ✅ Cost tracking (purchase_price)
- ✅ Full audit trail via InventoryMovement
- ✅ Recommended for inventory control

#### 2️⃣ **Manual Creation via Filament Admin** (Currently Allowed)

**File:** `app/Filament/Resources/ProductBatchResource.php`

Users can manually create batches through the admin panel:
- Navigate to: **Inventory → Batches → Create**
- Fill in form manually
- `purchase_id` = **NULL** (not linked to any purchase)
- No automatic audit trail
- Manual entry of all fields

**Characteristics:**
- ❌ No `purchase_id` (orphan batch)
- ❌ No link to Purchase Order
- ⚠️ Can create inconsistent data
- ⚠️ Harder to track supplier/cost
- ⚠️ Risk of duplicate entries

---

## 🚨 Current Issue

**Batches can be created without Purchase Orders!**

This means:
1. Stock can appear "out of nowhere"
2. No purchase history/documentation
3. Accounting reconciliation issues
4. COGS (Cost of Goods Sold) calculation problems
5. Supplier payment tracking breaks

---

## ✅ Recommended Solution: Enforce Purchase-Only Batch Creation

### Option 1: Disable Manual Creation Entirely

**Modify:** `app/Filament/Resources/ProductBatchResource.php`

```php
public static function getPages(): array
{
    return [
        'index' => Pages\ListProductBatches::route('/'),
        // 'create' => Pages\CreateProductBatch::route('/create'), ← REMOVE
        'edit' => Pages\EditProductBatch::route('/{record}/edit'),
    ];
}

public static function canCreate(): bool
{
    return false; // Disable creation via UI
}
```

**Result:**
- ✅ Batches ONLY created through Purchase::receive()
- ✅ All batches have purchase_id
- ✅ Full traceability
- ❌ No flexibility for corrections

### Option 2: Make purchase_id Required (Strict Mode)

**Modify:** `app/Models/ProductBatch.php`

Add validation:
```php
protected static function boot()
{
    parent::boot();
    
    static::creating(function ($batch) {
        if (empty($batch->purchase_id)) {
            throw new \Exception('Batches can only be created through Purchase Orders');
        }
    });
}
```

**Result:**
- ✅ Enforces purchase_id at model level
- ✅ Prevents orphan batches
- ✅ Even API/seeders must use purchases
- ❌ Very strict (might break corrections)

### Option 3: Allow Manual Only for Admins (Flexible)

**Modify:** `app/Filament/Resources/ProductBatchResource.php`

```php
public static function canCreate(): bool
{
    return auth()->user()?->hasRole('super_admin') ?? false;
}

// Add warning in form
Forms\Components\Placeholder::make('warning')
    ->label('')
    ->content('⚠️ Manual batch creation bypasses purchase tracking. Only use for corrections.')
    ->hidden(fn ($record) => $record?->purchase_id !== null),
```

**Result:**
- ✅ Regular users must use purchases
- ✅ Admins can fix mistakes
- ✅ Warning displayed
- ✅ Best balance

---

## 💰 stock_levels.quantity Calculation

### What is stock_levels?

**Table:** `stock_levels`  
**Purpose:** Fast lookup of current inventory per variant per store

**Key Fields:**
```php
stock_levels:
  - product_variant_id
  - store_id
  - quantity           ← Total available stock (THIS!)
  - reserved_quantity  ← Pending orders (not implemented)
  - reorder_level      ← Minimum before alert
  - last_movement_at   ← Last change timestamp
```

---

## 🔢 How stock_levels.quantity is Calculated

### Automatic Sync via Observer

**File:** `app/Observers/ProductBatchObserver.php`

```php
protected function syncStockLevel(ProductBatch $batch): void
{
    // Calculate total from ALL batches for this variant+store
    $totalQuantity = ProductBatch::where('product_variant_id', $batch->product_variant_id)
        ->where('store_id', $batch->store_id)
        ->sum('quantity_remaining');  ← SUM of all batches!

    // Update stock level
    StockLevel::updateOrCreate(
        [
            'product_variant_id' => $batch->product_variant_id,
            'store_id' => $batch->store_id,
        ],
        [
            'quantity' => (int) $totalQuantity,  ← Updated here!
            'last_movement_at' => now(),
        ]
    );
}
```

### The Formula:

```
stock_levels.quantity = SUM(
  all batches.quantity_remaining
  WHERE variant_id = X
  AND store_id = Y
)
```

### Example Calculation:

**Product: Paracetamol 500mg**  
**Store: Main Pharmacy**

| Batch Number | qty_received | qty_remaining | qty_sold | Expiry Date |
|--------------|-------------|---------------|----------|-------------|
| BATCH-001    | 100         | 75            | 25       | 2026-06-01  |
| BATCH-002    | 200         | 150           | 50       | 2026-08-15  |
| BATCH-003    | 50          | 50            | 0        | 2026-12-01  |

**Calculation:**
```
stock_levels.quantity = 75 + 150 + 50 = 275 units
```

**This is what shows in:**
- POS (available to sell)
- Inventory List
- Dashboard widgets
- Stock reports

---

## 📊 Different "Values" in stock_levels Context

### 1. **quantity** (Physical Count)
```php
stock_levels.quantity = 275 units
```
This is the **number of units** available.

### 2. **available_quantity** (After Reservations)
```php
available_quantity = quantity - reserved_quantity
                   = 275 - 0 = 275
```
Currently `reserved_quantity` is not used, so same as quantity.

### 3. **Inventory Cost Value** (What You Paid)
```php
cost_value = stock_levels.quantity × product_variants.cost_price
           = 275 units × ₹50 = ₹13,750
```
**Used in:** Dashboard "Total Stock Value", Valuation Reports

### 4. **Inventory Sale Value** (Potential Revenue)
```php
sale_value = stock_levels.quantity × product_variants.selling_price
           = 275 units × ₹80 = ₹22,000
```
**Used in:** Stock Valuation Report potential profit calculation

### 5. **Weighted Average Cost** (From Batches)
```php
weighted_avg_cost = SUM(batch.quantity_remaining × batch.purchase_price) 
                  / SUM(batch.quantity_remaining)

Example:
  Batch 1: 75 × ₹48 = ₹3,600
  Batch 2: 150 × ₹52 = ₹7,800
  Batch 3: 50 × ₹50 = ₹2,500
  Total: ₹13,900 / 275 = ₹50.55 avg
```
**Used in:** More accurate COGS calculation

---

## 🔄 When stock_levels.quantity Updates

### Triggers:

| Event | Trigger | How |
|-------|---------|-----|
| **Purchase Received** | `ProductBatch` created | Observer recalculates sum |
| **Sale Completed** | `ProductBatch.quantity_remaining` decreased | Observer recalculates sum |
| **Batch Damaged** | `ProductBatch.quantity_damaged` increased | Observer recalculates sum |
| **Manual Adjustment** | `InventoryService::adjustStock()` | Syncs batches + stock_levels |
| **Transfer** | Batch moved between stores | Observer for both stores |

### Flow Diagram:

```
Any Batch Change
    ↓
ProductBatch saved/deleted
    ↓
ProductBatchObserver::updated()
    ↓
syncStockLevel($batch)
    ↓
Calculate: SUM(batches.quantity_remaining)
    ↓
StockLevel::updateOrCreate([...], [
    'quantity' => $calculated_total
])
    ↓
✅ stock_levels.quantity updated
```

---

## 🎯 Key Takeaways

### Current State:
1. ✅ Batches can be created via Purchases (good)
2. ⚠️ Batches can also be created manually (risky)
3. ✅ stock_levels.quantity auto-syncs from batches
4. ✅ Observer keeps everything in sync

### Recommendations:
1. **Disable manual batch creation** OR restrict to admins only
2. **Enforce purchase_id requirement** for traceability
3. **Add validation** to prevent orphan batches
4. **Keep observer-based sync** (it's working well)

### What stock_levels.quantity Means:
- **Physical inventory count** (number of units)
- **Calculated from batches** (sum of quantity_remaining)
- **Auto-updated** via ProductBatchObserver
- **Used for** POS availability checks, inventory reports, dashboards

### What "Stock Value" Means:
- **Cost Value** = quantity × cost_price (what you paid)
- **Sale Value** = quantity × selling_price (potential revenue)
- **Both calculated on-the-fly** in reports/widgets
- **Not stored** in stock_levels table

---

## 🛠️ Implementation Checklist

To enforce Purchase-only batch creation:

- [ ] Modify `ProductBatchResource::canCreate()` to return false OR check admin role
- [ ] Add model-level validation in `ProductBatch::boot()`
- [ ] Update documentation for users
- [ ] Migrate existing orphan batches (link to "Manual Entry" purchase)
- [ ] Add admin warning message in batch form
- [ ] Test purchase receiving flow still works
- [ ] Update seeder to only create batches via purchases

---

**Last Updated:** January 23, 2026  
**Current System Status:** Manual batch creation ALLOWED but NOT recommended

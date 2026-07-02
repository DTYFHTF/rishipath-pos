# 📊 Complete System Flow Analysis: Inventory, Batches, Stocks, Sales & Purchases

## 🎯 Executive Summary

Your POS system uses a **TWO-TABLE ARCHITECTURE** for inventory management:
- **`stock_levels`** = Fast summary (total quantity per variant per store)
- **`product_batches`** = Detailed tracking (expiry, supplier, FIFO allocation)

Think of it like a **bank account**:
- `stock_levels.quantity` = Your account balance (quick check)
- `product_batches` = Individual deposits/withdrawals with dates and sources

---

## 🔗 How Everything Interconnects

### 📦 Database Tables & Relationships

```
┌─────────────────────┐
│   product_variants  │ (Base product definition)
│  - id               │
│  - sku              │
│  - cost_price       │ ← Used for valuation
│  - selling_price    │ ← Used for potential profit
│  - unit             │
└─────────┬───────────┘
          │
          ├─────────────────────────────────┐
          │                                 │
┌─────────▼───────────┐          ┌─────────▼───────────┐
│   stock_levels      │          │  product_batches    │
│  - variant_id       │          │  - variant_id       │
│  - store_id         │          │  - store_id         │
│  - quantity ◄───────┼──────────┤  - quantity_remaining│
│  - reserved_qty     │  SYNCED  │  - quantity_sold    │
│  - reorder_level    │          │  - quantity_received│
│  - last_movement_at │          │  - purchase_price   │
└─────────┬───────────┘          │  - expiry_date      │
          │                      │  - supplier_id      │
          │                      │  - purchase_id      │
          │                      └─────────┬───────────┘
          │                                │
          └────────────┬───────────────────┘
                       │
            ┌──────────▼──────────┐
            │ inventory_movements │ (Audit trail)
            │  - type             │ (purchase/sale/adjustment)
            │  - quantity         │
            │  - from_quantity    │
            │  - to_quantity      │
            │  - batch_id         │
            │  - reference_type   │
            │  - reference_id     │
            │  - cost_price       │
            │  - created_at       │
            └─────────────────────┘
```

---

## 🔄 Complete Data Flow & Sync Mechanisms

### 1️⃣ **PURCHASE FLOW** (Stock In)

```
┌─────────────────────────────────────────────────────────┐
│ Step 1: Create Purchase Order                          │
│  Purchase Model → save()                               │
│  - purchase_number: "PUR-000001"                       │
│  - status: "draft"                                     │
│  - supplier_id, total, etc.                           │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ Step 2: Receive Stock (Purchase::receive())           │
│  ⚠️ THIS IS THE PRIMARY ENTRY POINT FOR NEW INVENTORY │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ Step 3: Create ProductBatch (SOURCE OF TRUTH)          │
│  File: app/Models/Purchase.php (line 165-180)         │
│                                                         │
│  ProductBatch::create([                                │
│    'product_variant_id' => $item->product_variant_id,  │
│    'store_id' => $this->store_id,                     │
│    'batch_number' => 'PUR-20260123-SKU-001',          │
│    'supplier_id' => $this->supplier_id,               │
│    'purchase_price' => $item->unit_cost,  ◄─────┐     │
│    'quantity_received' => 100,            ◄─────┼─ KEY│
│    'quantity_remaining' => 100,           ◄─────┘     │
│    'quantity_sold' => 0,                              │
│    'expiry_date' => $item->expiry_date,               │
│  ])                                                    │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ Step 4: ProductBatchObserver FIRES 🔥                  │
│  File: app/Observers/ProductBatchObserver.php          │
│                                                         │
│  🤖 AUTOMATIC SYNC (created/updated/deleted events)    │
│                                                         │
│  protected function syncStockLevel($batch) {           │
│    $totalQuantity = ProductBatch::where(...)          │
│                    ->sum('quantity_remaining');        │
│                                                         │
│    StockLevel::updateOrCreate([...], [                │
│      'quantity' => $totalQuantity,  ◄─── AUTO UPDATE  │
│      'last_movement_at' => now()                      │
│    ])                                                  │
│  }                                                     │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ Step 5: StockLevel Updated ✅                          │
│  stock_levels.quantity = 100 (now reflects new stock)  │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ Step 6: Create Audit Trail                            │
│  InventoryMovement::create([                           │
│    'type' => 'purchase',                              │
│    'batch_id' => $batch->id,                          │
│    'quantity' => 100,                                 │
│    'from_quantity' => 0,                              │
│    'to_quantity' => 100,                              │
│    'reference_type' => 'Purchase',                    │
│    'reference_id' => $purchase->id,                   │
│    'cost_price' => $item->unit_cost                   │
│  ])                                                    │
└─────────────────────────────────────────────────────────┘
```

**Key Math in Purchases:**
```php
// Purchase Item Calculation
line_total = quantity_ordered × unit_cost
tax_amount = line_total × (tax_rate / 100)

// Purchase Total
subtotal = SUM(all items line_total)
total = subtotal + shipping_cost
```

---

### 2️⃣ **SALES FLOW** (Stock Out - FIFO)

```
┌─────────────────────────────────────────────────────────┐
│ Step 1: POS Sale Initiated                            │
│  File: app/Filament/Pages/EnhancedPOS.php (line 927)  │
│                                                         │
│  Customer scans/adds product to cart                   │
│  System checks: stock_levels.quantity >= requested qty │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ Step 2: Create Sale Record                            │
│  Sale::create([...])                                   │
│  - Creates Sale with receipt_number                    │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ Step 3: Create Sale Items                             │
│  SaleItem::create([                                    │
│    'quantity' => 5,                                    │
│    'price_per_unit' => 150.00,                        │
│    'cost_price' => 90.00,  ◄─── From variant         │
│    'tax_rate' => 18,                                  │
│  ])                                                    │
│                                                         │
│  📐 CALCULATIONS (auto in model):                     │
│  subtotal = quantity × price_per_unit                 │
│           = 5 × 150 = 750.00                         │
│  tax_amount = subtotal × (tax_rate / 100)            │
│             = 750 × 0.18 = 135.00                    │
│  total = subtotal + tax_amount                        │
│        = 750 + 135 = 885.00                          │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ Step 4: Decrease Stock via InventoryService ⚡         │
│  File: app/Services/InventoryService.php (line 169)   │
│                                                         │
│  InventoryService::decreaseStock(                      │
│    $variantId,                                         │
│    $storeId,                                          │
│    $quantity = 5,                                     │
│    'sale',                                            │
│    'Sale',                                            │
│    $saleId                                            │
│  )                                                     │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ Step 5: FIFO Batch Allocation 🎯                       │
│  File: app/Services/InventoryService.php (line 210)   │
│                                                         │
│  allocateFromBatches() {                               │
│    // Find batches ordered by:                        │
│    // 1. expiry_date ASC (oldest expiring first)     │
│    // 2. id ASC (oldest batch first)                 │
│                                                         │
│    $batches = ProductBatch::where(...)                │
│      ->where('quantity_remaining', '>', 0)            │
│      ->orderBy('expiry_date', 'asc')                 │
│      ->orderBy('id', 'asc')                          │
│      ->get();                                         │
│                                                         │
│    foreach ($batches as $batch) {                     │
│      $allocate = min($remaining, $batch->qty_remaining)│
│                                                         │
│      // UPDATE BATCH                                  │
│      $batch->quantity_remaining -= $allocate;  ◄───┐  │
│      $batch->quantity_sold += $allocate;        ◄───┼──│
│      $batch->save();  ◄──────── Triggers Observer!  │  │
│                                                      │  │
│      // Create audit trail per batch                │  │
│      InventoryMovement::create([...])               │  │
│                                                      │  │
│      $remaining -= $allocate;                       │  │
│    }                                                 │  │
│  }                                                   │  │
└──────────────────────────────────────────────────┬───┘  │
                                                    │      │
                                                    │      │
┌───────────────────────────────────────────────────▼──────┘
│ Step 6: Observer Auto-Syncs StockLevel 🔄              │
│  ProductBatchObserver fires on batch->save()           │
│                                                         │
│  Recalculates:                                         │
│  stock_levels.quantity = SUM(all batches.qty_remaining)│
│                                                         │
│  Before: 100                                           │
│  After:  95  (100 - 5 sold)                           │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ Step 7: Update Main StockLevel                        │
│  File: app/Services/InventoryService.php (line 190)   │
│                                                         │
│  adjustStock(                                          │
│    $variantId,                                         │
│    $storeId,                                          │
│    -5,  ◄─── NEGATIVE for decrease                   │
│    'sale'                                             │
│  )                                                     │
│                                                         │
│  $stock->quantity = $fromQuantity + $quantityChange;  │
│                   = 100 + (-5) = 95  ✅               │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ Step 8: Sale Totals Calculation                       │
│  Sale::recalculateTotals()                            │
│                                                         │
│  subtotal = SUM(items.subtotal) = 750.00              │
│  tax_amount = SUM(items.tax_amount) = 135.00          │
│  total_amount = subtotal + tax - discount             │
│               = 750 + 135 - 0 = 885.00                │
└─────────────────────────────────────────────────────────┘
```

**Key Math in Sales:**
```php
// Per Item
subtotal = quantity × price_per_unit
tax_amount = subtotal × (tax_rate / 100)
item_total = subtotal + tax_amount - discount_amount

// Per Sale
sale_subtotal = SUM(all items.subtotal)
sale_tax = SUM(all items.tax_amount)
sale_total = sale_subtotal + sale_tax - sale_discount_amount

// Profit Calculation (for reports)
profit_per_item = (price_per_unit - cost_price) × quantity
total_profit = SUM(all items profit)
```

---

## 💰 INVENTORY VALUE CALCULATIONS

### Dashboard "Total Stock Value" Logic

**File:** `app/Filament/Widgets/InventoryOverviewWidget.php` (line 34)

```php
$inventoryValue = StockLevel::query()
    ->join('product_variants', 'stock_levels.product_variant_id', '=', 'product_variants.id')
    ->join('products', 'product_variants.product_id', '=', 'products.id')
    ->where('products.organization_id', $organizationId)
    ->when($storeId, fn($q) => $q->where('stock_levels.store_id', $storeId))
    ->select(DB::raw('
        SUM(
          stock_levels.quantity 
          × 
          COALESCE(product_variants.cost_price, product_variants.base_price * 0.6)
        ) as total_value
    '))
    ->value('total_value') ?? 0;
```

### 📊 Formula Breakdown:

```
Total Inventory Value = Σ (Quantity × Cost Price)

For each variant in stock:
  Quantity = stock_levels.quantity
  Cost Price = product_variants.cost_price 
               OR (base_price × 0.6) as fallback

Example:
  Product A: 100 units × ₹50 = ₹5,000
  Product B: 50 units × ₹80 = ₹4,000
  Product C: 200 units × ₹25 = ₹5,000
  ─────────────────────────────────────
  Total Inventory Value = ₹14,000
```

---

### Stock Valuation Report (Detailed)

**File:** `app/Filament/Pages/StockValuationReport.php` (line 76)

```php
$result = $query->selectRaw('
    COUNT(DISTINCT stock_levels.id) as total_items,
    SUM(CASE WHEN stock_levels.quantity > 0 THEN 1 ELSE 0 END) as items_in_stock,
    SUM(stock_levels.quantity) as total_quantity,
    SUM(stock_levels.quantity * COALESCE(product_variants.cost_price, 0)) as total_cost_value,
    SUM(stock_levels.quantity * COALESCE(product_variants.selling_price, 0)) as total_sale_value
')->first();

$potentialProfit = $total_sale_value - $total_cost_value;
$marginPercent = ($total_sale_value > 0) 
    ? (($potentialProfit / $total_sale_value) * 100) 
    : 0;
```

### 📈 All Valuation Metrics:

```
1. COST VALUE (Inventory at cost)
   = Σ (quantity × cost_price)
   = What you PAID for all stock

2. SALE VALUE (Potential revenue)
   = Σ (quantity × selling_price)
   = What you CAN EARN if you sell all stock

3. POTENTIAL PROFIT
   = Sale Value - Cost Value
   = Gross profit if everything sells

4. MARGIN PERCENTAGE
   = (Potential Profit / Sale Value) × 100
   = Profit margin %

Example:
  Cost Value: ₹14,000 (what you paid)
  Sale Value: ₹21,000 (potential revenue)
  Profit: ₹7,000
  Margin: 33.3%
```

---

## 🔢 Complete Math Reference

### 1. Purchase Order Calculations

```php
// Per Item
line_total = quantity_ordered × unit_cost

// With Tax
tax_amount = line_total × (tax_rate / 100)
taxed_total = line_total + tax_amount

// Purchase Total
subtotal = SUM(all items.line_total)
total_tax = SUM(all items.tax_amount)
total_discount = SUM(all items.discount_amount)
grand_total = subtotal + shipping_cost - total_discount
```

### 2. Sale Calculations

```php
// Per Item
subtotal = quantity × price_per_unit
discount_amount = (manual entry or calculated)
taxable_amount = subtotal - discount_amount
tax_amount = taxable_amount × (tax_rate / 100)
item_total = taxable_amount + tax_amount

// Sale Total
sale_subtotal = SUM(items.subtotal)
sale_discount = SUM(items.discount_amount) + sale.discount_amount
sale_tax = SUM(items.tax_amount)
total_amount = sale_subtotal - sale_discount + sale_tax

// Change
amount_change = amount_paid - total_amount
```

### 3. Stock Level Calculations

```php
// Available Stock
available_quantity = quantity - reserved_quantity

// After Sale
new_quantity = old_quantity - sold_quantity

// After Purchase
new_quantity = old_quantity + received_quantity

// Batch Allocation (FIFO)
allocated = min(requested_quantity, batch.quantity_remaining)
batch.quantity_remaining -= allocated
batch.quantity_sold += allocated
```

### 4. Batch Integrity Formula

```php
// This MUST always be true:
quantity_received = quantity_remaining 
                  + quantity_sold 
                  + quantity_damaged 
                  + quantity_returned

// Stock Level Sync Formula:
stock_levels.quantity = SUM(
  all batches.quantity_remaining 
  WHERE variant_id = X 
  AND store_id = Y
)
```

### 5. Profit & Margin Calculations

```php
// Per Item Profit
item_profit = (selling_price - cost_price) × quantity_sold

// Sale Profit
sale_profit = SUM(items.profit)
profit_margin = (sale_profit / sale.total_amount) × 100

// COGS (Cost of Goods Sold)
cogs = SUM(quantity_sold × cost_price)

// Inventory Turnover
turnover_ratio = cogs / average_inventory_value
days_in_inventory = 365 / turnover_ratio
```

### 6. Valuation Calculations

```php
// Cost Value (at purchase price)
cost_value = SUM(quantity × cost_price)

// Sale Value (at selling price)
sale_value = SUM(quantity × selling_price)

// Weighted Average Cost (if using multiple batches)
weighted_avg_cost = SUM(batch.qty × batch.purchase_price) 
                  / SUM(batch.qty)

// Dead Stock Value
dead_stock_value = SUM(
  quantity × cost_price 
  WHERE no sales in last X days
)
```

---

## 🔐 Data Integrity Rules

### Critical Constraints:

1. **Stock Cannot Go Negative**
   ```php
   if ($toQuantity < 0) {
       throw new \Exception('Insufficient stock');
   }
   ```

2. **Batch Allocation Must Match**
   ```php
   if ($remaining > 0) {
       throw new \Exception("Insufficient batch stock");
   }
   ```

3. **StockLevel = Sum of Batches**
   ```php
   stock_levels.quantity === SUM(batches.quantity_remaining)
   ```

4. **Batch Quantity Conservation**
   ```php
   quantity_received === quantity_remaining + quantity_sold 
                       + quantity_damaged + quantity_returned
   ```

---

## 🎯 Key Synchronization Points

### When Stock Updates Happen:

| Action | Triggers | Updates | Observer |
|--------|----------|---------|----------|
| **Purchase Received** | Purchase::receive() | Creates Batch → StockLevel synced | ✅ ProductBatchObserver |
| **Sale Completed** | InventoryService::decreaseStock() | Updates Batch → StockLevel synced | ✅ ProductBatchObserver |
| **Stock Adjustment** | InventoryService::adjustStock() | Syncs Batches → StockLevel updated | ✅ Manual sync call |
| **Batch Damaged** | Batch quantity_remaining change | StockLevel auto-synced | ✅ ProductBatchObserver |
| **Transfer** | InventoryService::transferStock() | Both stores updated | ✅ Two transactions |

### Observer Chain:

```
ProductBatch saved/deleted
    ↓
ProductBatchObserver::updated()
    ↓
syncStockLevel($batch)
    ↓
Calculate: SUM(all batches.quantity_remaining)
    ↓
StockLevel::updateOrCreate()
    ↓
✅ stock_levels.quantity synced
```

---

## 📋 Summary

### The Complete Flow in One Picture:

```
PURCHASE → BATCH CREATED → OBSERVER SYNCS → STOCK_LEVEL UPDATED
   (receive)      ↓                            ↑
                  │                            │
                  ▼                            │
         [quantity_remaining = 100]     [quantity = 100]
                  │                            │
                  │                            │
SALE → FIFO ALLOCATION → BATCH UPDATED → OBSERVER SYNCS
              ↓                  ↓              ↑
      [oldest batch]    [qty_remaining = 95]   │
              ↓                  │              │
      [reduce by 5]              ▼              │
                        [quantity_sold += 5] ───┘
                                 │
                                 ▼
                        STOCK_LEVEL UPDATED
                        [quantity = 95] ✅
```

### All Numbers/Math in Your System:

1. **Purchase Math:** Quantity × Unit Cost + Tax + Shipping
2. **Sale Math:** Quantity × Price + Tax - Discount
3. **Inventory Value:** Quantity × Cost Price
4. **Profit:** (Selling Price - Cost Price) × Quantity
5. **Margin:** (Profit / Revenue) × 100
6. **Batch Sync:** Stock Level = SUM(Batches Remaining)
7. **FIFO:** Oldest Expiry → First Out

---

## 🚀 Quick Reference Commands

```php
// Check stock
$stock = InventoryService::getStock($variantId, $storeId);

// Sync if out of sync
InventoryService::syncBatchQuantities($variantId, $storeId);

// Get inventory value
$value = StockLevel::join('product_variants', ...)
    ->sum(DB::raw('quantity * cost_price'));

// Verify batch integrity
$batch->quantity_received === 
    $batch->quantity_remaining + 
    $batch->quantity_sold + 
    $batch->quantity_damaged + 
    $batch->quantity_returned;
```

---

**Last Updated:** January 23, 2026  
**System Version:** Phase 7 Complete

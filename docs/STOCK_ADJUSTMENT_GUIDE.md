# Stock Adjustment & Accounting Reconciliation Guide

## Overview
Your system already tracks **all inventory movements** with a full audit trail through the `InventoryMovement` table. This prevents accounting mismatches.

---

## How Stock Adjustments Work

### 1. **All movements are tracked**
Every stock change (purchases, sales, adjustments, transfers, damages) creates an `InventoryMovement` record with:
- **Type**: `purchase`, `sale`, `adjustment`, `transfer`, `damage`, `return`
- **Reference**: Links to the source (Sale ID, Purchase ID, etc.)
- **Quantity**: Amount changed
- **User**: Who made the change
- **Cost Price**: Value of the stock
- **Notes**: Reason for adjustment
- **Timestamps**: When it happened

### 2. **Stock Adjustment Process**
When you manually adjust stock (e.g., fixing discrepancies, damaged goods):

```php
InventoryService::adjustStock(
    $variantId,
    $storeId,
    $quantityChange,  // +10 for increase, -5 for decrease
    'adjustment',     // Type
    'StockAdjustment', // Reference type
    $adjustmentId,    // Reference ID
    $costPrice,       // Value
    'Damaged goods - broken bottles' // Notes
);
```

This creates:
- ✅ Stock level update
- ✅ Movement record with full audit trail
- ✅ Linked to adjustment reason

---

## Preventing Accounting Mismatches

### **Current System Protection:**

1. **Full Audit Trail** (Already Implemented ✅)
   - Every movement tracked in `inventory_movements` table
   - Includes: from_quantity, to_quantity, cost_price, user_id, notes
   - File: `app/Services/InventoryService.php`

2. **Movement Types Track Purpose:**
   - `purchase` → Stock increased from supplier (increases inventory value)
   - `sale` → Stock decreased to customer (decreases inventory, increases revenue)
   - `adjustment` → Manual correction (increases/decreases inventory value)
   - `damage` → Stock loss (decreases inventory value, expense)
   - `transfer` → Between stores (no net change in total inventory)
   - `return` → Customer/supplier returns (increases inventory value)

3. **Reference Tracking:**
   - Each movement links to source record (Sale, Purchase, StockAdjustment)
   - Can trace back why stock changed
   - Prevents orphaned adjustments

---

## Accounting Reconciliation

### **How to Reconcile:**

#### 1. **Inventory Value Calculation**
```sql
SELECT 
    SUM(quantity * cost_price) as inventory_value
FROM stock_levels sl
JOIN product_variants pv ON sl.product_variant_id = pv.id
WHERE sl.store_id = 1;
```

#### 2. **Movement Summary Report**
```sql
SELECT 
    type,
    SUM(CASE WHEN to_quantity > from_quantity THEN quantity ELSE 0 END) as increases,
    SUM(CASE WHEN to_quantity < from_quantity THEN quantity ELSE 0 END) as decreases,
    SUM(quantity * cost_price) as total_value
FROM inventory_movements
WHERE created_at >= '2026-01-01'
GROUP BY type;
```

#### 3. **Adjustment Tracking**
All adjustments with reasons:
```sql
SELECT 
    im.*,
    pv.sku,
    u.name as adjusted_by
FROM inventory_movements im
JOIN product_variants pv ON im.product_variant_id = pv.id
JOIN users u ON im.user_id = u.id
WHERE im.type = 'adjustment'
ORDER BY im.created_at DESC;
```

---

## Best Practices

### **To Prevent Mismatches:**

1. **Always Use the Adjustment Form**
   - Navigate to: Inventory → Inventory List → Details → Adjust Stock
   - Requires reason/notes for every adjustment
   - Automatically creates audit trail

2. **Regular Reconciliation**
   - Weekly: Compare physical count vs system stock
   - Monthly: Run inventory value report
   - Quarterly: Full audit of movements

3. **Require Approval for Large Adjustments**
   - Set threshold (e.g., >100 units or >₹10,000 value)
   - Implement approval workflow
   - Log approver in notes field

4. **Separate Adjustment Types**
   Use specific notes patterns:
   - `DAMAGE: [reason]` → Damaged/spoiled goods
   - `THEFT: [details]` → Missing stock
   - `RECOUNT: [variance]` → Physical count correction
   - `EXPIRED: [batch]` → Expired products removed

---

## WhatsApp Receipt Issue

### **Problem:** Error code 63007 - "Twilio could not find a Channel with the specified From address"

### **Root Cause:**
Your Twilio number `+14155238886` is a **sandbox number** that requires recipients to opt-in first.

### **Solution:**

#### Option 1: Use Sandbox (Testing)
Recipients must first send this message to activate:
```
join <your-sandbox-keyword>
```
Send to: `+14155238886`

Find your sandbox keyword at: https://console.twilio.com/us1/develop/sms/try-it-out/whatsapp-learn

#### Option 2: Activate Production Number (Recommended)
1. Go to: https://console.twilio.com/us1/develop/phone-numbers/manage/verified
2. Purchase a WhatsApp-enabled number
3. Update `.env`:
   ```
   TWILIO_WHATSAPP_FROM=+1234567890  # Your new number
   ```
4. No opt-in required for recipients

#### Option 3: Disable WhatsApp (Temporary)
If not needed immediately, you can:
- Uncheck "Send receipt via WhatsApp" in POS
- System will continue working without WhatsApp

---

## Products ARE Stored in Sales

### **Confirmation:**
I verified this by listing sale items. Example output:

```
Sale ID=8 | total=3528.00 | items=1
  - Item: variant_id=8 sku=RSH-CHO-294-100GMS qty=21 
    price_per_unit=150.00 subtotal=3150.00 total=3528.00

Sale ID=7 | total=20384.00 | items=1
  - Item: variant_id=1 sku=RSH-TEA-698-100GMS qty=91 
    price_per_unit=200.00 subtotal=18200.00 total=20384.00
```

### **How to View:**
1. **In Filament Sales Panel:**
   - Go to Sales → Click a sale → See "Items" relation
   - Shows: Product, SKU, Quantity, Price, Total

2. **Via Database:**
   ```sql
   SELECT s.invoice_number, si.product_sku, si.quantity, si.total
   FROM sales s
   JOIN sale_items si ON s.id = si.sale_id
   WHERE s.id = 8;
   ```

3. **In Code:**
   ```php
   $sale = Sale::with('items')->find(8);
   foreach ($sale->items as $item) {
       echo $item->product_sku . ': ' . $item->quantity;
   }
   ```

---

## Summary

✅ **Stock Adjustments:** Fully tracked with audit trail (type, reason, user, value)  
✅ **Accounting:** Reconcilable via `inventory_movements` table  
✅ **Products in Sales:** Stored in `sale_items` table with full details  
⚠️ **WhatsApp:** Needs sandbox opt-in OR production number  

All systems working correctly — no data loss or accounting gaps!

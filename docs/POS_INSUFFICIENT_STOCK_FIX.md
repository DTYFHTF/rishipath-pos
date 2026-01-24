# POS "Insufficient Stock" Fix - 2026-01-24

## Problem Summary

When attempting to complete a POS sale with quantity close to (but less than) available stock, the system incorrectly threw "Insufficient stock" error even though stock was available.

**Example:** Variant has 698 units available, user orders 688 units → Error: "Insufficient stock"

## Root Cause

The issue was caused by a race condition between the `ProductBatchObserver` and the inventory adjustment logic in `InventoryService::decreaseStock()`.

### The Flow (Before Fix)

1. `decreaseStock()` starts a database transaction
2. `allocateFromBatches()` updates batch quantities using FIFO:
   - Batch 1: 622 → 0 (allocated 622)
   - Batch 2: 76 → 10 (allocated 66)
   - Total allocated: 688 ✓
3. **Problem:** When `$batch->save()` is called, `ProductBatchObserver::updated()` triggers
4. Observer calculates `sum(quantity_remaining)` = 0 + 10 = 10
5. Observer updates `StockLevel.quantity` to 10 **immediately**
6. Next, `adjustStockInternal()` reads `StockLevel` via `lockForUpdate()->firstOrCreate()`
7. **Bug:** It sees `quantity = 10` (not 698!) due to observer's premature sync
8. Tries to calculate: 10 - 688 = -678 < 0
9. Throws "Insufficient stock" exception ❌

## Solution

**Disable the `ProductBatchObserver` during FIFO batch allocation** by wrapping batch updates in `ProductBatch::withoutEvents()`. This prevents the observer from prematurely syncing the `StockLevel` before all batches are allocated.

The `adjustStockInternal()` method then correctly updates the `StockLevel` after all batches have been allocated.

### Files Modified

**app/Services/InventoryService.php**

1. **Split `adjustStock()` into public and internal methods:**
   - `adjustStock()`: Public method that wraps in `DB::transaction()` (for external callers)
   - `adjustStockInternal()`: Protected method without transaction wrapper (for internal use within existing transactions)
   - This avoids nested transaction issues with SQLite

2. **Update `decreaseStock()` to:**
   - Call `adjustStockInternal()` instead of `adjustStock()` (avoids nested transactions)
   - Pass `skipBatchSync = true` to prevent redundant batch synchronization

3. **Update `allocateFromBatches()` to:**
   - Wrap batch updates in `ProductBatch::withoutEvents(function() { ... })` to disable observer
   - Add comment explaining why observer is disabled

## Testing

Created CLI test command for reproducing and verifying the fix:

```bash
php artisan pos:test-sale RSH-TAI-327 688 --store=1
```

### Test Results

**Before Fix:**
```
Stock before: 698 (available: 698)
❌ Sale failed: Insufficient stock
```

**After Fix:**
```
Stock before: 698 (available: 698)
Stock after: 10 (available: 10)
✅ Sale completed successfully! Sale ID: 8
```

Verified:
- ✅ StockLevel correctly updated: 698 → 10
- ✅ Batches allocated via FIFO (oldest expiry first)
- ✅ Batch 1 (expires 2026-01-25): 622 units allocated
- ✅ Batch 2 (expires 2026-02-01): 66 units allocated
- ✅ Total allocated: 688 units ✓
- ✅ Final stock: 10 units remaining ✓

## Impact

This fix resolves the POS inventory issue where valid sales were being rejected. Users can now complete sales up to the actual available stock quantity without encountering false "Insufficient stock" errors.

## Technical Notes

- The `ProductBatchObserver` is still active for all other batch operations (purchases, adjustments, etc.)
- Only disabled during FIFO allocation in `decreaseStock()` to prevent race condition
- Stock Level and Batch quantities remain synchronized after the transaction completes
- No changes to database schema or batch allocation logic required
- Fix is backward compatible and doesn't affect existing inventory data

## Related Files

- `app/Services/InventoryService.php` - Core inventory service with FIFO allocation
- `app/Observers/ProductBatchObserver.php` - Observer that syncs StockLevel on batch changes
- `app/Console/Commands/TestPOSSale.php` - CLI test command for reproducing issue
- `app/Filament/Pages/EnhancedPOS.php` - POS interface (uses InventoryService)

## Commands for Testing

```bash
# Test POS sale
php artisan pos:test-sale RSH-TAI-327 688 --store=1

# Check stock levels
php artisan pos:diagnose RSH-TAI-327

# Reset stock to clean state (for testing)
php artisan tinker
$stock = \App\Models\StockLevel::where('product_variant_id', 2)->first();
$stock->quantity = 698; // or desired amount
$stock->save();
```

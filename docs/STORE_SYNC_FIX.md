# Store Synchronization Fix

## Problem
When switching stores using the global store selector dropdown, pages (especially POS and reports) were not updating to reflect the new store's data. The dropdown would change but the page content remained showing data from the previous store.

## Root Cause
Pages were not listening to the `store-switched` Livewire event that's broadcast when the user switches stores via the `StoreSwitcher` component.

## Solution
Updated all store-dependent pages to:
1. Import `StoreContext` service
2. Initialize `storeId` from `StoreContext::getCurrentStoreId()` in `mount()`
3. Add `'store-switched' => 'handleStoreSwitch'` to `$listeners` array
4. Implement `handleStoreSwitch($storeId)` method that updates the store and refreshes the page

## Files Updated

### 1. **EnhancedPOS.php** (POS System)
- Added `StoreContext` import
- Updated `resolveStoreId()` to check global store context first
- Added `handleStoreSwitch()` method to:
  - Update current terminal for new store
  - Reload POS sessions
  - Switch to first session or create new one
  - Show success notification
  - Refresh page data

### 2. **Report Pages** (All updated with same pattern)
- **SalesReport.php** - Sales analytics by store
- **ProfitReport.php** - Profit analysis per store
- **StockValuationReport.php** - Inventory valuation
- **InventoryTurnoverReport.php** - Stock movement analysis
- **CashierPerformanceReport.php** - Cashier metrics
- **CustomerAnalyticsReport.php** - Customer insights

Each report now:
- Imports `StoreContext`
- Initializes with current store from context
- Listens to store-switched event
- Refreshes data when store changes

## How It Works

### Before
```
User switches store → StoreSwitcher updates session → Event fires
                                                      ↓
                                                   (ignored)
                                                      ↓
                                          Page shows old store data
```

### After
```
User switches store → StoreSwitcher updates session → Event fires
                                                      ↓
                                          All pages listen to event
                                                      ↓
                                          handleStoreSwitch($storeId)
                                                      ↓
                                          Update local storeId property
                                                      ↓
                                          Dispatch '$refresh' to reload
                                                      ↓
                                          Page shows new store data ✓
```

## Usage Pattern

Any new page that needs to respond to store changes should follow this pattern:

```php
<?php

namespace App\Filament\Pages;

use App\Services\StoreContext;
use Filament\Pages\Page;

class MyPage extends Page
{
    public $storeId;

    protected $listeners = ['store-switched' => 'handleStoreSwitch'];

    public function mount(): void
    {
        $this->storeId = StoreContext::getCurrentStoreId();
        // ... other initialization
    }

    public function handleStoreSwitch($storeId): void
    {
        $this->storeId = $storeId;
        $this->dispatch('$refresh');
    }

    // Use $this->storeId in queries
    public function getData()
    {
        return Model::where('store_id', $this->storeId)->get();
    }
}
```

## Benefits

1. **Real-time Sync**: All pages instantly reflect the selected store
2. **Consistent UX**: Users see correct data immediately after switching
3. **No Confusion**: POS terminal, reports, and inventory all show same store
4. **Scalable Pattern**: Easy to add store-awareness to new pages
5. **Session Persistence**: Store selection persists across page refreshes

## Testing Checklist

- [ ] Switch stores in topbar dropdown
- [ ] Verify POS page shows new store name and sessions
- [ ] Verify Sales Report filters by new store
- [ ] Verify Profit Report shows new store data
- [ ] Verify Stock Valuation reflects new store inventory
- [ ] Verify all report pages update their data
- [ ] Confirm store persists after page refresh
- [ ] Test with user assigned to single store (dropdown hidden)
- [ ] Test with super admin (sees all stores)

## Notes

- The `StoreContext` service uses Laravel sessions to persist the selected store
- The `store-switched` event is dispatched by the `StoreSwitcher` Livewire component
- Pages that don't need store filtering (e.g., global settings) don't need these changes
- The POS page has special logic to also update the terminal reference when switching stores

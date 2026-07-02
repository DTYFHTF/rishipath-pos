# Store Management Guide

## Overview
The system uses a **global store context** that persists across all pages. Users can switch stores once and all pages/forms automatically use that selection.

## How Store Assignment Works

### For Admins (Super Admin Role)
- **Access**: All stores automatically
- **Can**: Switch between any store using the global store switcher
- **Can**: Assign stores to other users
- **Location**: Users menu → Edit User → Assigned Stores field

### For Regular Users
- **Access**: Only stores assigned to them
- **Can**: Switch between assigned stores (if multiple)
- **Cannot**: Access unassigned stores
- **Default**: First assigned store is selected on login

## Assigning Stores to Users

### Step 1: Navigate to User Management
1. Go to **Settings** → **Users**
2. Click **Edit** on the user you want to configure

### Step 2: Assign Stores
1. Find the **"Assigned Stores"** field in the form
2. Select one or multiple stores from the dropdown
3. **Leave empty** to give access to all stores (admin behavior)
4. Click **Save**

### Important Notes
- **Super Admins** always have access to all stores regardless of assignment
- Users with **no stores assigned** get access to all stores
- Users see only their assigned stores in the global store switcher
- Store selection persists across page navigation and browser sessions

## Global Store Switcher

### Location
- Top-left of the navigation bar
- Visible on all pages
- Shows current store with dropdown icon

### Behavior
- **Click** to see available stores
- **Select** a store to switch
- All pages refresh automatically
- Selection persists until manually changed
- Follows user across page navigation

## Technical Implementation

### Backend
- `app/Services/StoreContext.php` - Manages store selection in session
- `app/Livewire/StoreSwitcher.php` - Livewire component for UI
- Session key: `current_store_id`

### Frontend
- Component broadcasts `store-switched` event
- All pages listen and refresh automatically
- No manual store selection needed on individual forms

### Database
- User table has `stores` JSON field
- Stores array of store IDs: `[1, 2, 3]`
- Empty array or null = access to all stores

## Common Scenarios

### Scenario 1: Single Store User
**Setup**: Assign 1 store to user
**Result**: No dropdown shown, store name displayed only

### Scenario 2: Multi-Store User
**Setup**: Assign 2+ stores to user
**Result**: Dropdown with all assigned stores, can switch freely

### Scenario 3: Admin User
**Setup**: Super admin role or no stores assigned
**Result**: See all active stores, can switch to any

### Scenario 4: New User Default
**Setup**: User logs in for first time
**Result**: System auto-selects first assigned store (or first active store if admin)

## Pages Using Global Store

The following pages automatically use the global store selection:
- ✅ Inventory List
- ✅ Stock Adjustment
- ✅ Stock Transfer
- ✅ Purchase Orders
- ✅ Sales
- ✅ POS Terminal
- ✅ All Reports

**No individual store selectors on these pages anymore!**

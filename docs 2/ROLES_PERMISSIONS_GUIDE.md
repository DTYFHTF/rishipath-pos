# 🔐 User Roles & Permissions - Complete Guide

**Phase 3 Implementation**  
**Status:** ✅ Complete

---

## 📋 Overview

The Rishipath POS system now includes a comprehensive role-based access control (RBAC) system with 70+ granular permissions organized into 10 categories.

---

## 👥 Predefined Roles

### **1. Super Administrator** 🛡️
**Slug:** `super-admin`  
**Permissions:** All 70 permissions  
**Use Case:** System owners, technical administrators  

**Full Access To:**
- Everything in the system
- Cannot be deleted (system role)
- User management
- Role management
- System settings

---

### **2. Store Manager** 👔
**Slug:** `manager`  
**Permissions:** 44 permissions  
**Use Case:** Store managers, supervisors  

**Can Access:**
- ✅ Dashboard & Analytics (full)
- ✅ POS Operations (full including voids/refunds)
- ✅ Product Management (full)
- ✅ Inventory Management (full)
- ✅ Customer Management
- ✅ Sales & Inventory Reports
- ✅ User Management (create/edit, not delete)
- ❌ Role Management
- ❌ System Administration

---

### **3. Cashier** 💰
**Slug:** `cashier`  
**Permissions:** 12 permissions  
**Use Case:** Point of sale operators, sales staff  

**Can Access:**
- ✅ POS Billing (full access)
- ✅ View Products (read-only)
- ✅ View Inventory (read-only)
- ✅ Create Customers
- ✅ View Own Sales Only
- ❌ Edit Products
- ❌ Adjust Stock
- ❌ View Reports
- ❌ Settings

**Perfect For:** Front desk, sales counter staff

---

### **4. Inventory Clerk** 📦
**Slug:** `inventory-clerk`  
**Permissions:** 19 permissions  
**Use Case:** Warehouse staff, inventory managers  

**Can Access:**
- ✅ Inventory Management (full)
- ✅ Batch Receiving
- ✅ Stock Adjustments
- ✅ Supplier Management
- ✅ Inventory Reports
- ✅ View Products (read-only)
- ❌ POS Billing
- ❌ Edit Products
- ❌ Sales Reports

**Perfect For:** Stock room, warehouse operations

---

### **5. Accountant** 📊
**Slug:** `accountant`  
**Permissions:** 21 permissions  
**Use Case:** Accounting staff, financial analysts  

**Can Access:**
- ✅ All Reports (Sales, Inventory, Profit)
- ✅ Export & Email Reports
- ✅ View Sales (read-only)
- ✅ View Products & Inventory (read-only)
- ✅ View Customers
- ❌ POS Billing
- ❌ Edit Products
- ❌ Adjust Stock

**Perfect For:** Financial reporting, auditing

---

## 🔑 Permission Categories

### **1. Dashboard & Analytics** (4 permissions)
```
view_dashboard
view_pos_stats
view_inventory_overview
view_low_stock_alerts
```

### **2. POS Operations** (7 permissions)
```
access_pos_billing
create_sales
void_sales
apply_discounts
process_refunds
view_sales
view_own_sales_only
```

### **3. Product Management** (12 permissions)
```
view_products, create_products, edit_products, delete_products
view_product_variants, create_product_variants, edit_product_variants, delete_product_variants
view_categories, create_categories, edit_categories, delete_categories
```

### **4. Inventory Management** (14 permissions)
```
view_inventory, view_stock_levels
view_product_batches, create_product_batches, edit_product_batches, delete_product_batches
adjust_stock, view_stock_adjustments, view_inventory_movements
view_suppliers, create_suppliers, edit_suppliers, delete_suppliers
```

### **5. Customer Management** (5 permissions)
```
view_customers
create_customers
edit_customers
delete_customers
view_customer_purchase_history
```

### **6. Reporting** (5 permissions)
```
view_sales_reports
view_inventory_reports
view_profit_reports
export_reports
email_reports
```

### **7. User Management** (5 permissions)
```
view_users
create_users
edit_users
delete_users
manage_user_permissions
```

### **8. Role Management** (4 permissions)
```
view_roles
create_roles
edit_roles
delete_roles
```

### **9. Settings & Configuration** (12 permissions)
```
view_settings, edit_settings
view_organizations, edit_organizations
view_stores, create_stores, edit_stores, delete_stores
view_terminals, create_terminals, edit_terminals, delete_terminals
```

### **10. System Administration** (3 permissions)
```
access_system_logs
manage_backups
manage_integrations
```

---

## 🚀 How to Use

### **Accessing Role Management**
1. Login as Super Administrator
2. Navigate to **Settings → Roles**
3. View all predefined and custom roles

### **Creating a Custom Role**
1. Go to **Settings → Roles**
2. Click **"Create"**
3. Enter role details:
   - **Name:** Display name (e.g., "Assistant Manager")
   - **Slug:** Unique identifier (e.g., "assistant-manager")
   - **System Role:** Leave unchecked for custom roles
4. Select permissions from the list (grouped by category)
5. Use **bulk toggle** to select/deselect all
6. Save

### **Assigning Roles to Users**
1. Navigate to **Settings → Users**
2. Click **"Create"** for new user or **"Edit"** for existing
3. Fill in user information:
   - Name, Email, Phone
   - Password (optional PIN for quick login)
   - Active status
4. Select **Role** from dropdown
5. Optionally assign specific **Stores**
6. Save

### **Viewing User Permissions**
When editing a user:
- Selected role is shown
- Permission count is displayed
- All permissions are inherited from the role

---

## 🔒 Permission Checking Methods

### **In Code:**

```php
// Check single permission
if (auth()->user()->hasPermission('access_pos_billing')) {
    // User can access POS
}

// Check any permission
if (auth()->user()->hasAnyPermission(['view_sales', 'view_sales_reports'])) {
    // User has at least one
}

// Check all permissions
if (auth()->user()->hasAllPermissions(['create_sales', 'apply_discounts'])) {
    // User has both
}

// Check role
if (auth()->user()->hasRole('cashier')) {
    // User is a cashier
}

// Check super admin
if (auth()->user()->isSuperAdmin()) {
    // Full access
}
```

### **In Resources:**

```php
public static function canViewAny(): bool
{
    return auth()->user()?->hasPermission('view_products') ?? false;
}

public static function canCreate(): bool
{
    return auth()->user()?->hasPermission('create_products') ?? false;
}
```

### **In Pages:**

```php
public static function canAccess(): bool
{
    return auth()->user()?->hasPermission('access_pos_billing') ?? false;
}
```

---

## 🎯 Navigation Visibility

Navigation menu items automatically hide based on permissions:

- **POS Billing:** Requires `access_pos_billing`
- **Products:** Requires `view_products`
- **Sales Report:** Requires `view_sales_reports`
- **Stock Adjustment:** Requires `adjust_stock`
- **Roles:** Requires `view_roles`
- **Users:** Requires `view_users`

---

## 📊 Role Management Features

### **In Role List:**
- Role name and slug
- System role indicator (shield icon)
- User count badge (color-coded)
- Permission count
- Created date

### **Role Editing:**
- Cannot delete system roles
- Cannot delete roles with assigned users
- Bulk toggle for permissions
- Searchable permission list
- Grouped by category

### **User Management:**
- Active/inactive status
- Role assignment (dropdown)
- Store restrictions
- Last login tracking
- Cannot delete own account
- Role permission count displayed

---

## 🔐 Security Features

1. **Super Admin Protection:**
   - All permissions by default
   - System role (cannot be deleted)
   - Bypass permission checks

2. **Role Protection:**
   - System roles cannot be deleted
   - Roles with users cannot be deleted
   - Slug uniqueness enforced

3. **User Protection:**
   - Users cannot delete themselves
   - Inactive users cannot login
   - Password hashing
   - Optional PIN protection

4. **Permission Inheritance:**
   - User inherits role permissions
   - User-specific permissions override role
   - Cascade checking (user → role)

---

## 📱 Usage Examples

### **Example 1: Onboarding a New Cashier**
1. **Settings → Users → Create**
2. Name: "John Doe"
3. Email: "john@rishipath.org"
4. Password: Generate strong password
5. PIN: 1234 (optional)
6. Role: **Cashier**
7. Active: ✅
8. Save

**Result:** John can only access POS Billing

---

### **Example 2: Creating Department Manager**
1. **Settings → Roles → Create**
2. Name: "Department Manager"
3. Slug: "dept-manager"
4. Permissions:
   - Dashboard ✅
   - POS Operations ✅
   - View Products ✅
   - View Inventory ✅
   - Sales Reports ✅
5. Save

**Result:** Custom role with specific access

---

### **Example 3: Restricting User to Single Store**
1. **Settings → Users → Edit**
2. Select user
3. Role: Store Manager
4. Assigned Stores: **Select "Store A" only**
5. Save

**Result:** Manager only sees data for Store A

---

## 🎨 UI Features

- **Color-Coded Badges:**
  - Super Admin: 🔴 Red
  - Manager: 🟠 Orange
  - Cashier: 🟢 Green
  - Inventory Clerk: 🔵 Blue
  - Accountant: 🟣 Purple

- **Icons:**
  - Active User: ✅ Check Circle
  - Inactive User: ❌ X Circle
  - System Role: 🛡️ Shield
  - Custom Role: 👥 User Group

- **Badges:**
  - User count on role (color-coded by count)
  - Permission count
  - Active user count in navigation

---

## 🚨 Common Scenarios

### **Scenario 1: Cashier Needs to See Reports**
**Solution:** Promote to Store Manager or create custom role

### **Scenario 2: Multiple Store Managers**
**Solution:** Create multiple users with Manager role, assign different stores

### **Scenario 3: Temporary Access**
**Solution:** Create custom role with specific permissions, set user inactive when done

### **Scenario 4: Audit Trail**
**Solution:** Check user's `last_login_at` and `inventoryMovements` relationship

---

## 📈 Best Practices

1. **Use Predefined Roles First:**
   - Covers 90% of use cases
   - Well-tested permission sets

2. **Create Custom Roles for Special Cases:**
   - Seasonal staff
   - Part-time workers
   - Contractors

3. **Regular Audits:**
   - Review user roles quarterly
   - Remove inactive users
   - Update permissions as needed

4. **Principle of Least Privilege:**
   - Give minimum permissions needed
   - Promote only when necessary

5. **Test New Roles:**
   - Create test user
   - Login and verify access
   - Adjust permissions as needed

---

## 🔧 Troubleshooting

### **Issue: User can't access POS**
**Check:**
- User is active
- Role has `access_pos_billing` permission
- User is logged in

### **Issue: Navigation item missing**
**Check:**
- Permission exists in role
- Resource has `canViewAny()` method
- User role is correctly assigned

### **Issue: Can't delete role**
**Check:**
- Not a system role
- No users assigned to role

---

## 🎉 Summary

✅ **5 Predefined Roles** (Super Admin, Manager, Cashier, Inventory Clerk, Accountant)  
✅ **70+ Permissions** across 10 categories  
✅ **Granular Access Control** at resource and page level  
✅ **Flexible User Management** with role assignment  
✅ **Custom Role Creation** for special requirements  
✅ **Navigation Auto-Filtering** based on permissions  
✅ **Security Built-In** (hashing, protection, auditing)

**The system is now enterprise-ready with complete access control!** 🚀

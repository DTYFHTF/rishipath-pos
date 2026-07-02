# 🔐 User Permissions by Role

**Generated:** January 11, 2026  
**System:** Rishipath POS v1.0

---

## 📊 Quick Reference Table

| Role | Users | Key Access | Restrictions |
|------|-------|-----------|--------------|
| **Super Admin** | admin@rishipath.com | Everything | None |
| **Manager** | manager@rishipath.com | Store operations, reports | No system settings |
| **Cashier** | cashier1@, cashier2@ | POS only | No inventory, reports |
| **Inventory Clerk** | inventory@rishipath.com | Stock management | No POS, sales |
| **Accountant** | accountant@rishipath.com | All reports | No POS, editing |

---

## 1️⃣ Super Administrator

**Email:** admin@rishipath.com  
**Password:** admin123  
**PIN:** 1234

### ✅ All Permissions (70 total)

**Dashboard & Analytics (4)**
- view_dashboard
- view_pos_stats
- view_inventory_overview
- view_low_stock_alerts

**POS Operations (7)**
- access_pos_billing
- create_sales
- void_sales
- apply_discounts
- process_refunds
- view_sales
- view_own_sales_only

**Product Management (12)**
- view_products
- create_products
- edit_products
- delete_products
- view_product_variants
- create_product_variants
- edit_product_variants
- delete_product_variants
- view_categories
- create_categories
- edit_categories
- delete_categories

**Inventory Management (14)**
- view_inventory
- view_stock_levels
- view_product_batches
- create_product_batches
- edit_product_batches
- delete_product_batches
- adjust_stock
- view_stock_adjustments
- view_inventory_movements
- view_suppliers
- create_suppliers
- edit_suppliers
- delete_suppliers

**Customer Management (5)**
- view_customers
- create_customers
- edit_customers
- delete_customers
- view_customer_purchase_history

**Reports (5)**
- view_sales_reports
- view_inventory_reports
- view_profit_reports
- export_reports
- email_reports

**User Management (4)**
- view_users
- create_users
- edit_users
- delete_users

**Role Management (4)**
- view_roles
- create_roles
- edit_roles
- delete_roles

**Settings & Configuration (7)**
- view_settings
- edit_settings
- view_stores
- create_stores
- edit_stores
- delete_stores

**Loyalty Program (2)**
- view_loyalty_program
- manage_loyalty_program

---

## 2️⃣ Store Manager

**Email:** manager@rishipath.com  
**Password:** manager123  
**PIN:** 2345

### ✅ Permitted (62 permissions)

**Can Access:**
- ✅ Dashboard & Analytics (full)
- ✅ POS Operations (full including voids/refunds)
- ✅ Product Management (full CRUD)
- ✅ Inventory Management (full CRUD)
- ✅ Customer Management (full)
- ✅ All Reports (view, export, email)
- ✅ User Management (view, create, edit)
- ✅ Loyalty Program (view, manage)

### ❌ Restricted

**Cannot:**
- ❌ Delete users
- ❌ View/Create/Edit/Delete roles
- ❌ View/Edit system settings
- ❌ Create/Edit/Delete stores

**Use Case:** Daily store operations, staff management, inventory control

---

## 3️⃣ Cashier

**Email:** cashier1@rishipath.com, cashier2@rishipath.com  
**Password:** cashier123  
**PIN:** 3456 / 4567

### ✅ Permitted (12 permissions)

**Dashboard:**
- ❌ No dashboard access

**POS Operations:**
- ✅ access_pos_billing
- ✅ create_sales
- ✅ apply_discounts
- ✅ view_own_sales_only (can only see their own sales)

**Products (Read-Only):**
- ✅ view_products
- ✅ view_product_variants
- ✅ view_categories

**Inventory (Read-Only):**
- ✅ view_inventory
- ✅ view_stock_levels

**Customers:**
- ✅ view_customers
- ✅ create_customers

**Loyalty:**
- ✅ view_loyalty_program

### ❌ Restricted

**Cannot:**
- ❌ Edit products
- ❌ Adjust stock
- ❌ View reports
- ❌ Void/refund sales
- ❌ View other cashiers' sales
- ❌ Access settings
- ❌ Manage users

**Use Case:** Front desk, sales counter operations only

---

## 4️⃣ Inventory Clerk

**Email:** inventory@rishipath.com  
**Password:** inventory123  
**PIN:** 5678

### ✅ Permitted (19 permissions)

**Dashboard:**
- ✅ view_dashboard
- ✅ view_inventory_overview
- ✅ view_low_stock_alerts

**Products (Read-Only):**
- ✅ view_products
- ✅ view_product_variants
- ✅ view_categories

**Inventory Management (Full):**
- ✅ view_inventory
- ✅ view_stock_levels
- ✅ view_product_batches
- ✅ create_product_batches
- ✅ edit_product_batches
- ✅ adjust_stock
- ✅ view_stock_adjustments
- ✅ view_inventory_movements

**Suppliers:**
- ✅ view_suppliers
- ✅ create_suppliers
- ✅ edit_suppliers

**Reports:**
- ✅ view_inventory_reports

### ❌ Restricted

**Cannot:**
- ❌ Access POS
- ❌ Edit products
- ❌ Delete batches
- ❌ Delete suppliers
- ❌ View sales reports
- ❌ View profit reports

**Use Case:** Warehouse operations, stock receiving, batch management

---

## 5️⃣ Accountant

**Email:** accountant@rishipath.com  
**Password:** accountant123  
**PIN:** 6789

### ✅ Permitted (21 permissions)

**Dashboard:**
- ✅ view_dashboard
- ✅ view_pos_stats
- ✅ view_inventory_overview

**Sales (Read-Only):**
- ✅ view_sales

**Products (Read-Only):**
- ✅ view_products
- ✅ view_product_variants
- ✅ view_categories

**Inventory (Read-Only):**
- ✅ view_inventory
- ✅ view_stock_levels

**Customers (Read-Only):**
- ✅ view_customers
- ✅ view_customer_purchase_history

**Reports (Full Access):**
- ✅ view_sales_reports
- ✅ view_inventory_reports
- ✅ view_profit_reports
- ✅ export_reports
- ✅ email_reports

### ❌ Restricted

**Cannot:**
- ❌ Access POS
- ❌ Edit anything (products, inventory, customers)
- ❌ Create/adjust stock
- ❌ Manage users
- ❌ Access settings

**Use Case:** Financial reporting, auditing, business analysis

---

## 🔄 Updating Permissions

### Option 1: Via Database Seeder

Edit `/database/seeders/UserRoleSeeder.php` and run:
```bash
php artisan db:seed --class=UserRoleSeeder
```

### Option 2: Via Filament Admin Panel

1. Login as Super Admin
2. Go to **Users** > **Roles**
3. Edit role
4. Check/uncheck permissions
5. Save

### Option 3: Via Code

```php
// Get role
$cashierRole = Role::where('slug', 'cashier')->first();

// Add permission
$cashierRole->grantPermission('process_refunds');

// Remove permission
$cashierRole->revokePermission('apply_discounts');
```

---

## 📝 Permission Naming Convention

**Format:** `{action}_{resource}`

**Actions:**
- `view` - Read access
- `create` - Add new records
- `edit` - Modify existing
- `delete` - Remove records
- `manage` - Full CRUD
- `access` - Page/feature access
- `adjust` - Special actions (stock)
- `export` - Data export
- `email` - Email functionality

**Resources:**
- Singular for records (user, product, sale)
- Plural for sections (users, products, sales)
- Descriptive for features (pos_billing, loyalty_program)

---

## 🚨 Security Best Practices

1. **Cashiers should NEVER have:**
   - Edit product prices
   - Delete sales
   - Access reports (prevents data manipulation)

2. **Inventory Clerks should NEVER have:**
   - POS access
   - Price changes
   - Sales reports

3. **Accountants should have:**
   - Read-only access to everything
   - Full export capabilities
   - No editing rights

4. **Manager vs Super Admin:**
   - Managers handle daily operations
   - Super Admins handle system configuration
   - Keep Super Admin count minimal (1-2 max)

---

## 📞 Support

**Need custom roles?**
1. Create new role via Filament
2. Assign specific permissions
3. Test with test user
4. Deploy to production

**Permission not working?**
1. Check role assignment
2. Clear cache: `php artisan cache:clear`
3. Check canAccess() methods in Pages/Resources
4. Verify permission slug matches exactly

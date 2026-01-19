# Full-Scale Testing Guide

## 🎉 Database Successfully Seeded!

Your POS system is now populated with comprehensive test data across **2 organizations**.

## 📊 Test Data Summary

### **Organization 1: Rishipath International Foundation** (India)
- **Currency:** INR (Indian Rupees)
- **Stores:** 3 (Mumbai, Delhi, Bangalore)
- **Terminals:** 6 (2 per store)
- **Suppliers:** 8
- **Categories:** 8
- **Products:** 18
- **Product Variants:** 44
- **Inventory Batches:** ~140
- **Customers:** 150
- **Cashiers:** 9
- **Sales Transactions:** ~330
- **Loyalty Tiers:** 4 (Bronze, Silver, Gold, Platinum)

### **Organization 2: Shuddhidham Ayurved** (Nepal)
- **Currency:** NPR (Nepalese Rupees)
- **Stores:** 2 (Kathmandu, Pokhara)
- **Terminals:** 4 (2 per store)
- **Suppliers:** 8
- **Categories:** 8
- **Products:** 18
- **Product Variants:** 44
- **Inventory Batches:** ~95
- **Customers:** 150
- **Cashiers:** 5
- **Sales Transactions:** ~320
- **Loyalty Tiers:** 4 (Bronze, Silver, Gold, Platinum)

---

## 🔐 Test Credentials

### Super Admin
- **Email:** `admin@rishipath.org`
- **Password:** `password`
- **Organization:** Rishipath International Foundation

### Cashiers (Format)
- **Rishipath:** `mum-cashier1@rishipath.test`, `del-cashier1@rishipath.test`, etc.
- **Shuddhidham:** `ktm-cashier1@shuddhidham.test`, `pkr-cashier1@shuddhidham.test`, etc.
- **Password:** `password` (all cashiers)

---

## 🧪 Critical Testing Checklist

### **1. Organization Isolation & Form-State Scoping** ⭐ MOST CRITICAL

#### Test Scenarios:

**a) User Management with Organization Selection**
- [ ] Login as admin
- [ ] Go to Users → Create User
- [ ] **Verify organization_id select is present and defaults to current org**
- [ ] Change organization → **Verify store list updates**
- [ ] **Test email uniqueness:** Try creating user with email `test@example.com` for Rishipath
- [ ] Switch organization dropdown to Shuddhidham
- [ ] **Try same email `test@example.com` again** → Should work (different organization)
- [ ] Create the user
- [ ] Try editing and verify validation respects form state

**Expected Behavior:** Email uniqueness should be scoped to the organization selected IN THE FORM, not just session context.

**b) Customer Management**
- [ ] Create customer with code `TEST-001` and email `customer@test.com` in Rishipath
- [ ] Filter customers by organization → verify isolation
- [ ] Switch to Shuddhidham context
- [ ] Try creating customer with same code `TEST-001` and email `customer@test.com`
- [ ] **Should succeed** (different organization)

**Fields to Test:**
- `customer_code` - unique per organization
- `phone` - unique per organization
- `email` - unique per organization

**c) Product/Variant Management**
- [ ] Create product with SKU `TEST-SKU-001` in Rishipath
- [ ] Create variant with SKU `TEST-VAR-001` and barcode `123456789` for that product
- [ ] Switch to Shuddhidham
- [ ] Try creating product with same parent SKU `TEST-SKU-001` → Should succeed
- [ ] Create variant with same SKU `TEST-VAR-001` and barcode `123456789`
- [ ] **Should fail** because variant SKU/barcode scopes through product's organization

**d) Category Names**
- [ ] Create category "Test Category" in Rishipath
- [ ] Switch to Shuddhidham
- [ ] Create category "Test Category" again → Should succeed

**e) Role Management**
- [ ] Create role with slug "test-role" in Rishipath
- [ ] Verify it appears in Rishipath role list only
- [ ] Switch to Shuddhidham
- [ ] Create role with same slug "test-role" → Should succeed
- [ ] Verify roles are isolated per organization

---

### **2. Multi-Store Inventory Management**

#### Test Scenarios:

**a) Inventory Across Stores**
- [ ] Go to Product Batches
- [ ] Check Mumbai store has different inventory than Delhi store
- [ ] Create new batch for Triphala Churna 100GMS in Mumbai
- [ ] Verify batch number is unique within store+variant combination
- [ ] Try same batch number in different store → Should succeed

**b) Store-Specific Pricing**
- [ ] Check if product variants have different prices for India vs Nepal
- [ ] Verify MRP India shows for Rishipath products
- [ ] Verify Selling Price Nepal shows for Shuddhidham products

---

### **3. Sales & POS Operations**

#### Test Scenarios:

**a) Create Sale**
- [ ] Go to Sales → Create Sale
- [ ] Select Mumbai store and terminal
- [ ] Select cashier assigned to Mumbai
- [ ] Add products to sale
- [ ] **Verify only products with inventory in Mumbai store are available**
- [ ] Complete sale
- [ ] Check inventory batch quantity decreases

**b) Customer Loyalty**
- [ ] Find a customer enrolled in loyalty program
- [ ] Create sale for that customer
- [ ] **Verify loyalty points are awarded**
- [ ] Check customer's tier updated if points threshold crossed
- [ ] Verify loyalty points are NOT shared across organizations

**c) Receipt Numbers**
- [ ] Create multiple sales
- [ ] Verify receipt numbers are unique per organization
- [ ] Check format: RSH-YYYYMMDD-XXXXXX for Rishipath
- [ ] Check format: SHD-YYYYMMDD-XXXXXX for Shuddhidham

---

### **4. Supplier & Purchase Management**

#### Test Scenarios:

**a) Supplier Codes**
- [ ] Go to Suppliers
- [ ] Verify supplier codes are prefixed with org: `rishipath-SUP-001`, `shuddhidham-SUP-001`
- [ ] Try creating supplier with duplicate code in same org → Should fail
- [ ] Create supplier with same code suffix in different org → Should succeed

**b) Purchase Orders**
- [ ] Create purchase order
- [ ] Select supplier from current organization only
- [ ] Verify supplier list is filtered by organization

---

### **5. Loyalty Program**

#### Test Scenarios:

**a) Tier Management**
- [ ] Check loyalty tiers for Rishipath: bronze, silver, gold, platinum
- [ ] Check loyalty tiers for Shuddhidham: same names but different data
- [ ] Verify tier slugs are org-specific: `rishipath-bronze`, `shuddhidham-bronze`
- [ ] Edit tier → verify changes only affect that organization

**b) Points Calculation**
- [ ] Create sale for loyalty customer
- [ ] Verify points calculation uses correct tier multiplier
- [ ] Check customer automatically promoted to next tier when threshold crossed

---

### **6. Reporting & Analytics**

#### Test Scenarios:

**a) Sales Reports**
- [ ] Generate sales report
- [ ] Verify data shows only current organization's sales
- [ ] Check totals match only that org's transactions
- [ ] Switch organization → verify report data changes

**b) Inventory Reports**
- [ ] Check stock levels
- [ ] Verify inventory shows only current org's stores
- [ ] Check low stock alerts are org-specific

---

## 🚨 Where the System Might Break

### **Critical Breaking Points:**

1. **Form-State vs Context Mismatch**
   - **Where:** User/Customer/Product forms when admin can select organization
   - **Break:** If validation uses `OrganizationContext` instead of `$get('organization_id')`
   - **Symptom:** False "already taken" errors when creating records in different org
   - **Test:** Create duplicate codes/emails across organizations

2. **Product Variant Uniqueness**
   - **Where:** Creating variants with same SKU/barcode
   - **Break:** If `whereHas('product')` query doesn't properly scope to product's organization
   - **Symptom:** Can't create variants with same SKU even in different organizations
   - **Test:** Create variants with identical SKUs across organizations

3. **Store Assignment**
   - **Where:** Assigning users to stores
   - **Break:** If stores aren't filtered by selected organization in form
   - **Symptom:** User assigned to wrong organization's stores
   - **Test:** Create user → select org A → verify can only see org A's stores

4. **Loyalty Points Cross-Contamination**
   - **Where:** Awarding loyalty points
   - **Break:** If customer ID matches across orgs (shouldn't happen but test)
   - **Symptom:** Points awarded to wrong organization's customer
   - **Test:** Create sales with loyalty customers and verify points isolation

5. **Receipt Number Collisions**
   - **Where:** Creating sales
   - **Break:** If receipt numbers aren't org-specific
   - **Symptom:** Duplicate receipt numbers across organizations
   - **Test:** Create many sales in both orgs, check for duplicates

6. **Inventory Deduction**
   - **Where:** Processing sales
   - **Break:** If inventory deducted from wrong store/org
   - **Symptom:** Wrong store shows decreased inventory
   - **Test:** Create sale in Mumbai, verify only Mumbai inventory affected

7. **Query Performance**
   - **Where:** Large datasets with organization filtering
   - **Break:** Missing indexes on organization_id
   - **Symptom:** Slow queries as data grows
   - **Test:** Run reports and list views, check query execution time

8. **Role Permission Scope**
   - **Where:** Permission checks
   - **Break:** If roles from one org can be assigned to users in another
   - **Symptom:** Permission escalation across organizations
   - **Test:** Try assigning roles cross-organization

---

## 🔍 Manual Testing Workflow

### Step-by-Step Test Plan:

```bash
# 1. Fresh start
php artisan migrate:fresh --seed
php artisan db:seed --class=FullScaleTestSeeder

# 2. Login and explore
http://your-domain/admin

# 3. Test organization switching
- Click organization switcher (if present)
- OR login as different org users

# 4. For each critical feature:
   a) Test in Rishipath context
   b) Switch to Shuddhidham
   c) Test same feature
   d) Verify isolation

# 5. Test unique field validations:
   - Create duplicate codes in same org (should fail)
   - Create duplicate codes across orgs (should succeed)

# 6. Test form-state scoping:
   - Create user → select org in form
   - Try duplicate email for that org (should fail)
   - Change org dropdown → same email (should succeed)
```

---

## 📝 Expected Validation Behavior

### Resources with Form-State Scoping:

| Resource | Unique Fields | Scoping Method |
|----------|--------------|----------------|
| **User** | email | `$get('organization_id')` |
| **Customer** | customer_code, phone, email | `$get('organization_id')` |
| **Supplier** | supplier_code | `$get('organization_id')` |
| **Product** | sku | `$get('organization_id')` |
| **Category** | name | `$get('organization_id')` |
| **Sale** | receipt_number | `$get('organization_id')` |
| **Role** | slug | `$get('organization_id')` |
| **ProductVariant** | sku, barcode | `whereHas('product')` |
| **ProductBatch** | batch_number | `$get('store_id') + $get('product_variant_id')` |
| **LoyaltyTier** | slug | Organization-prefixed |

---

## 🎯 Success Criteria

### Your system is working correctly if:

✅ Same email/code can exist in different organizations  
✅ Users can only see stores from selected organization  
✅ Product variants respect parent product's organization  
✅ Sales receipt numbers don't collide across orgs  
✅ Inventory is properly isolated per store  
✅ Loyalty points are tracked separately per organization  
✅ Roles and permissions are org-specific  
✅ All filters/lists show only current org's data  
✅ Form validation uses form state, not just session context  

---

## 🛠️ Quick Verification Commands

```bash
# Check data counts
php artisan tinker --execute="
  echo 'Organizations: ' . App\Models\Organization::count() . PHP_EOL;
  echo 'Stores: ' . App\Models\Store::count() . PHP_EOL;
  echo 'Products: ' . App\Models\Product::count() . PHP_EOL;
  echo 'Variants: ' . App\Models\ProductVariant::count() . PHP_EOL;
  echo 'Customers: ' . App\Models\Customer::count() . PHP_EOL;
  echo 'Sales: ' . App\Models\Sale::count() . PHP_EOL;
  echo 'Inventory Batches: ' . App\Models\ProductBatch::count() . PHP_EOL;
"

# Check organization isolation
php artisan tinker --execute="
  \$rishipath = App\Models\Organization::where('slug', 'rishipath')->first();
  \$shuddhidham = App\Models\Organization::where('slug', 'shuddhidham')->first();
  echo 'Rishipath Products: ' . App\Models\Product::where('organization_id', \$rishipath->id)->count() . PHP_EOL;
  echo 'Shuddhidham Products: ' . App\Models\Product::where('organization_id', \$shuddhidham->id)->count() . PHP_EOL;
"

# Check for any uniqueness violations (should return 0)
php artisan tinker --execute="
  echo 'Duplicate SKUs: ' . App\Models\Product::groupBy('sku')->havingRaw('COUNT(*) > 1')->count() . PHP_EOL;
  echo 'Duplicate Receipt Numbers: ' . App\Models\Sale::groupBy('receipt_number')->havingRaw('COUNT(*) > 1')->count() . PHP_EOL;
"
```

---

## 📋 Test Results Template

Create a checklist and mark as you test:

```markdown
## Test Execution Results

### Organization Isolation
- [ ] Users filtered by org: PASS / FAIL / NOTES: ___
- [ ] Products filtered by org: PASS / FAIL / NOTES: ___
- [ ] Customers filtered by org: PASS / FAIL / NOTES: ___
- [ ] Sales filtered by org: PASS / FAIL / NOTES: ___

### Form-State Scoping
- [ ] User email validation: PASS / FAIL / NOTES: ___
- [ ] Customer code validation: PASS / FAIL / NOTES: ___
- [ ] Product SKU validation: PASS / FAIL / NOTES: ___
- [ ] Role slug validation: PASS / FAIL / NOTES: ___

### Multi-Store Operations
- [ ] Inventory per store: PASS / FAIL / NOTES: ___
- [ ] Store assignment: PASS / FAIL / NOTES: ___
- [ ] Sales per terminal: PASS / FAIL / NOTES: ___

### Data Integrity
- [ ] No cross-org data leaks: PASS / FAIL / NOTES: ___
- [ ] Loyalty points isolated: PASS / FAIL / NOTES: ___
- [ ] Receipt numbers unique: PASS / FAIL / NOTES: ___

### Performance
- [ ] List views load quickly: PASS / FAIL / NOTES: ___
- [ ] Reports generate fast: PASS / FAIL / NOTES: ___
- [ ] No N+1 query issues: PASS / FAIL / NOTES: ___
```

---

## 🐛 Found an Issue?

If you find any breaking points or unexpected behavior:

1. **Document the exact steps** to reproduce
2. **Check the error logs**: `storage/logs/laravel.log`
3. **Note which organization** you were in
4. **Check if it's a form-state vs context issue**
5. **Verify database constraints** are in place

---

## 🎊 Testing Complete!

Once you've verified all critical paths, your multi-organization POS system with proper data isolation and form-state scoping is production-ready! 🚀

**Topbar / UI suggestions**

- **Primary quick links:** `POS`, `Products`, `Variants`, `Inventory`, `Customers`, `Suppliers`, `Purchases`, `Reports` — place these in the topbar for fast navigation when working on the floor.
- **Why:** Cashiers and store managers switch frequently between POS and inventory/product lookups; a small set of top-level links reduces clicks and context switching.
- **Behavior notes:** When switching store via the topbar selector, the POS header and all components should reflect the new store immediately (the app broadcasts `store-switched`). Ensure the `StoreContext` session value persists across redirects and that components listen to the `store-switched` event.

If you'd like, I can refine the exact topbar labels and add role-sensitive visibility (e.g., hide `Suppliers`/`Purchases` for pure cashier roles).

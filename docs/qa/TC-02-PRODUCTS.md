# TC-02 — Products, Categories & Variants

**Priority**: P1–P2  
**URL**: /admin/products | /admin/categories | /admin/product-variants

---

## 1. Categories

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-02-01 | Create category | Logged in as Super Admin or Manager | 1. Go to Catalogue → Categories 2. Click New 3. Enter name "Spices & Masala" 4. Save | Category created. Appears in list | P2 |
| TC-02-02 | Create category — duplicate name | Category "Spices" already exists | 1. Create another category "Spices" | Validation error: duplicate name | P2 |
| TC-02-03 | Create sub-category | Parent category exists | 1. New Category 2. Set parent = existing category 3. Save | Sub-category created with parent relationship | P2 |
| TC-02-04 | Edit category name | Category exists | 1. Click Edit on category 2. Change name 3. Save | Name updated. Products in category still intact | P2 |
| TC-02-05 | Delete category — no products | Empty category exists | 1. Click Delete | Category deleted | P3 |
| TC-02-06 | Delete category — has products | Category with products | 1. Click Delete | Error or confirmation: "Category has products" | P2 |
| TC-02-07 | Category search/filter | Multiple categories exist | 1. Type in search box on list page | Filtered results shown | P3 |

---

## 2. Products

### 2.1 Create Product

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-02-10 | Create product — all required fields | Category exists | 1. Go to Products → New 2. Fill: Name "Turmeric Powder", Category, Unit "gram", Base Price 50 3. Save | Product created. Appears in products list | P1 |
| TC-02-11 | Create product — missing name | — | 1. Leave name blank 2. Save | Validation error: name required | P1 |
| TC-02-12 | Create product — missing category | — | 1. Leave category blank 2. Save | Validation error: category required | P2 |
| TC-02-13 | Create product — negative price | — | 1. Enter base price = -10 2. Save | Validation error: price must be positive | P2 |
| TC-02-14 | Create product — with description | — | 1. Fill all fields + add description text 2. Save | Description saved and shown on product detail | P3 |
| TC-02-15 | Create product — with image upload | — | 1. Upload product image 2. Save | Image displayed on product card | P3 |

### 2.2 Edit Product

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-02-16 | Edit product name | Product exists | 1. Click Edit 2. Change name 3. Save | Name updated everywhere (POS, receipts) | P1 |
| TC-02-17 | Edit product price | Product exists | 1. Edit base price 2. Save | New price reflected in POS immediately | P1 |
| TC-02-18 | Change product category | Product exists | 1. Edit category 2. Save | Product moves to new category | P2 |
| TC-02-19 | Mark product as inactive | Product exists | 1. Edit → toggle Status to inactive 2. Save | Product hidden from POS search | P2 |

### 2.3 Delete Product

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-02-20 | Delete product — no sales history | Product with no transactions | 1. Select product 2. Delete | Product deleted | P3 |
| TC-02-21 | Delete product — has sales | Product used in past sales | 1. Try to delete | Error shown: "Cannot delete — has sales history" OR product is soft-deleted | P2 |

### 2.4 Product List

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-02-22 | Search product by name | Products seeded | 1. Type "turmeric" in search | Matching products shown instantly | P2 |
| TC-02-23 | Filter by category | Multiple categories | 1. Select category filter | Only that category's products shown | P2 |
| TC-02-24 | Sort products | Products list | 1. Click column headers | Sorting works for name, price, created date | P3 |

---

## 3. Product Variants

Variants represent pack sizes (e.g., Turmeric 50g, 100g, 500g).

### 3.1 Create Variant

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-02-30 | Create variant for product | Product exists | 1. Go to product → Variants tab 2. New Variant 3. Fill: Pack Size "100g", SKU "TURM-100G", Price 45, MRP 55 4. Save | Variant created and linked to product | P1 |
| TC-02-31 | Create variant — duplicate SKU | Variant with SKU "TURM-100G" exists | 1. Create another variant with same SKU | Validation error: SKU already used | P1 |
| TC-02-32 | Create variant — barcode | New variant | 1. Fill barcode field with EAN-13 2. Save | Barcode saved. Scannable in POS | P1 |
| TC-02-33 | Create variant — auto-generate barcode | New variant | 1. Click "Generate Barcode" button 2. Save | Unique barcode generated and saved | P2 |
| TC-02-34 | Create variant — multiple sizes | Product exists | 1. Create 50g, 100g, 500g, 1kg variants | All 4 variants appear in variant list and POS | P1 |
| TC-02-35 | Create variant — missing SKU | — | 1. Leave SKU blank 2. Save | Validation error OR auto-SKU generated | P2 |

### 3.2 Edit Variant

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-02-36 | Edit variant price | Variant exists | 1. Edit price 2. Save | New price reflected in POS | P1 |
| TC-02-37 | Edit variant MRP | Variant exists | 1. Edit MRP 2. Save | MRP updated in price displays | P2 |
| TC-02-38 | Edit variant SKU | Variant exists | 1. Edit SKU to unique value 2. Save | SKU updated | P2 |

### 3.3 Barcode Printing

| # | Test Case | Preconditions | Steps | Expected Result | Priority |
|---|-----------|---------------|-------|-----------------|----------|
| TC-02-40 | Print barcode label | Variant with barcode | 1. Go to Inventory → Barcode Label Printing 2. Select variant 3. Set quantity 4. Print/Preview | Barcode labels generated with product name, SKU, price | P2 |
| TC-02-41 | Scan barcode in POS | Variant with barcode, POS open | 1. In POS, use barcode scanner / enter barcode 2. Press Enter | Correct product variant added to cart | P1 |

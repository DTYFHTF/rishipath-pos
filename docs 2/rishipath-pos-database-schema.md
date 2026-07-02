# Rishipath POS - Database Schema Reference

> **Comprehensive database design for multi-tenant, white-label POS system**  
> **Optimized for**: Offline-first, FIFO inventory, Ayurvedic products  
> **Supports**: India (GST) & Nepal (VAT)

---

## 📊 Schema Overview

### Entity Relationships

```
Organizations (White-label tenants)
  └─ Stores (Physical locations)
      ├─ Terminals (POS counters)
      ├─ Users (Staff members)
      ├─ Products (Store-specific catalog)
      ├─ Stock Levels (Current inventory)
      ├─ Sales (Transactions)
      └─ Customers (Optional)
```

---

## 🏢 Multi-Tenancy & Organization

### organizations

```sql
CREATE TABLE organizations (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    slug VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    legal_name VARCHAR(255),
    country_code CHAR(2) NOT NULL,
    currency CHAR(3) NOT NULL,
    timezone VARCHAR(50) DEFAULT 'Asia/Kolkata',
    locale VARCHAR(5) DEFAULT 'en',
    config JSON,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_slug (slug),
    INDEX idx_country (country_code),
    INDEX idx_active (active)
);

-- Config JSON structure:
{
  "branding": {...},
  "features": {...},
  "tax": {...},
  "receipt": {...},
  "inventory": {...},
  "localization": {...},
  "payments": {...},
  "backup": {...}
}
```

### stores

```sql
CREATE TABLE stores (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    country_code CHAR(2) NOT NULL,
    postal_code VARCHAR(20),
    phone VARCHAR(20),
    email VARCHAR(255),
    tax_number VARCHAR(50),
    license_number VARCHAR(100),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    config JSON,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_org_code (organization_id, code),
    INDEX idx_org (organization_id),
    INDEX idx_active (active)
);

-- Config JSON structure:
{
  "hours": {
    "monday": {"open": "09:00", "close": "18:00"},
    "sunday": {"closed": true}
  },
  "receipt_template_id": 1,
  "low_stock_alert_enabled": true
}
```

### terminals

```sql
CREATE TABLE terminals (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    store_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    device_id VARCHAR(255) UNIQUE,
    printer_config JSON,
    scanner_config JSON,
    last_receipt_number VARCHAR(50),
    last_synced_at TIMESTAMP,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    UNIQUE KEY unique_store_code (store_id, code),
    INDEX idx_store (store_id),
    INDEX idx_device (device_id)
);

-- Printer config example:
{
  "type": "thermal",
  "width": 80,
  "connection": "usb",
  "device_name": "POS-58",
  "paper_cut": true
}
```

---

## 👥 Users & Permissions

### users

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    pin CHAR(4),
    role_id BIGINT UNSIGNED,
    stores JSON,
    permissions JSON,
    active BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL,
    INDEX idx_org (organization_id),
    INDEX idx_email (email),
    INDEX idx_role (role_id)
);

-- stores JSON: [1, 2, 3] (accessible store IDs)
-- permissions JSON: {"sales.discount": true, "inventory.adjust": true}
```

### roles

```sql
CREATE TABLE roles (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    permissions JSON NOT NULL,
    is_system_role BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_org_slug (organization_id, slug),
    INDEX idx_org (organization_id)
);

-- permissions JSON:
{
  "sales": ["create", "view", "discount"],
  "products": ["view"],
  "reports": ["sales_daily"]
}
```

---

## 📦 Product Management

### categories

```sql
CREATE TABLE categories (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED,
    parent_id BIGINT UNSIGNED,
    name VARCHAR(255) NOT NULL,
    name_nepali VARCHAR(255),
    name_hindi VARCHAR(255),
    slug VARCHAR(255) NOT NULL,
    product_type VARCHAR(50),
    config JSON,
    sort_order INT DEFAULT 0,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_org (organization_id),
    INDEX idx_parent (parent_id),
    INDEX idx_type (product_type),
    INDEX idx_sort (sort_order)
);

-- product_type: choorna, tailam, ghritam, rasayana, capsules, etc.
-- config JSON:
{
  "unit_type": "weight",
  "default_unit": "GMS",
  "tax_category": "essential",
  "requires_batch": true,
  "requires_expiry": true,
  "shelf_life_months": 24
}
```

### products

```sql
CREATE TABLE products (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED,
    sku VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    name_nepali VARCHAR(255),
    name_hindi VARCHAR(255),
    name_sanskrit VARCHAR(255),
    description TEXT,
    product_type VARCHAR(50) NOT NULL,
    unit_type ENUM('weight', 'volume', 'piece') NOT NULL,
    has_variants BOOLEAN DEFAULT FALSE,
    tax_category ENUM('essential', 'standard', 'luxury') DEFAULT 'standard',
    requires_batch BOOLEAN DEFAULT TRUE,
    requires_expiry BOOLEAN DEFAULT TRUE,
    shelf_life_months INT,
    is_prescription_required BOOLEAN DEFAULT FALSE,
    ingredients JSON,
    usage_instructions TEXT,
    image_url VARCHAR(500),
    active BOOLEAN DEFAULT TRUE,
    deleted_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_org (organization_id),
    INDEX idx_category (category_id),
    INDEX idx_sku (sku),
    INDEX idx_type (product_type),
    INDEX idx_active (active),
    FULLTEXT idx_search (name, name_nepali, name_hindi, name_sanskrit)
);

-- ingredients JSON: ["Amalaki", "Haritaki", "Bibhitaki"]
```

### product_variants

```sql
CREATE TABLE product_variants (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(100) UNIQUE NOT NULL,
    pack_size DECIMAL(10, 3) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    base_price DECIMAL(10, 2),
    mrp_india DECIMAL(10, 2),
    selling_price_nepal DECIMAL(10, 2),
    cost_price DECIMAL(10, 2),
    barcode VARCHAR(100) UNIQUE,
    hsn_code VARCHAR(20),
    weight DECIMAL(10, 3),
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id),
    INDEX idx_sku (sku),
    INDEX idx_barcode (barcode),
    INDEX idx_active (active)
);

-- Example: Amalaki Choorna 100 GMS, 250 GMS as separate variants
-- unit: GMS, ML, CAPSULES, PIECES, etc.
```

### product_store_pricing

```sql
CREATE TABLE product_store_pricing (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NOT NULL,
    custom_price DECIMAL(10, 2),
    custom_tax_rate DECIMAL(5, 2),
    reorder_level INT DEFAULT 10,
    max_stock_level INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    UNIQUE KEY unique_variant_store (product_variant_id, store_id),
    INDEX idx_store (store_id)
);

-- Allows store-specific pricing overrides
```

---

## 📊 Inventory Management

### product_batches

```sql
CREATE TABLE product_batches (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NOT NULL,
    batch_number VARCHAR(100) NOT NULL,
    manufactured_date DATE,
    expiry_date DATE,
    purchase_date DATE,
    purchase_price DECIMAL(10, 2),
    supplier_id BIGINT UNSIGNED,
    quantity_received DECIMAL(10, 3) NOT NULL,
    quantity_remaining DECIMAL(10, 3) NOT NULL,
    quantity_sold DECIMAL(10, 3) DEFAULT 0,
    quantity_damaged DECIMAL(10, 3) DEFAULT 0,
    quantity_returned DECIMAL(10, 3) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    UNIQUE KEY unique_batch (store_id, product_variant_id, batch_number),
    INDEX idx_store (store_id),
    INDEX idx_variant (product_variant_id),
    INDEX idx_expiry (expiry_date),
    INDEX idx_remaining (quantity_remaining)
);

-- FIFO logic uses: ORDER BY expiry_date ASC, created_at ASC
```

### stock_levels

```sql
CREATE TABLE stock_levels (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(10, 3) NOT NULL DEFAULT 0,
    reserved_quantity DECIMAL(10, 3) DEFAULT 0,
    available_quantity DECIMAL(10, 3) GENERATED ALWAYS AS (quantity - reserved_quantity) STORED,
    reorder_level INT DEFAULT 10,
    last_counted_at TIMESTAMP,
    last_movement_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    UNIQUE KEY unique_variant_store (product_variant_id, store_id),
    INDEX idx_store (store_id),
    INDEX idx_variant (product_variant_id),
    INDEX idx_available (available_quantity)
);

-- Real-time stock snapshot per store
-- available_quantity = quantity - reserved_quantity (computed)
```

### inventory_movements

```sql
CREATE TABLE inventory_movements (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    batch_id BIGINT UNSIGNED,
    type ENUM('purchase', 'sale', 'adjustment', 'transfer', 'damage', 'return') NOT NULL,
    quantity DECIMAL(10, 3) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    from_quantity DECIMAL(10, 3),
    to_quantity DECIMAL(10, 3),
    reference_type VARCHAR(50),
    reference_id BIGINT UNSIGNED,
    cost_price DECIMAL(10, 2),
    user_id BIGINT UNSIGNED,
    notes TEXT,
    created_at TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES product_batches(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_org (organization_id),
    INDEX idx_store (store_id),
    INDEX idx_variant (product_variant_id),
    INDEX idx_type (type),
    INDEX idx_created (created_at),
    INDEX idx_reference (reference_type, reference_id)
);

-- Complete audit trail of all inventory changes
-- reference_type: 'Sale', 'Purchase', 'Adjustment', etc.
-- reference_id: ID of the related entity
```

---

## 💰 Sales & Transactions

### sales

```sql
CREATE TABLE sales (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NOT NULL,
    terminal_id BIGINT UNSIGNED NOT NULL,
    receipt_number VARCHAR(100) UNIQUE NOT NULL,
    date DATE NOT NULL,
    time TIME NOT NULL,
    cashier_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED,
    customer_name VARCHAR(255),
    customer_phone VARCHAR(20),
    customer_email VARCHAR(255),
    subtotal DECIMAL(10, 2) NOT NULL,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    discount_type ENUM('percentage', 'fixed'),
    discount_reason VARCHAR(255),
    tax_amount DECIMAL(10, 2) NOT NULL,
    tax_details JSON,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'upi', 'card', 'esewa', 'khalti', 'other') NOT NULL,
    payment_status ENUM('paid', 'pending', 'partial', 'refunded') DEFAULT 'paid',
    payment_reference VARCHAR(255),
    amount_paid DECIMAL(10, 2),
    amount_change DECIMAL(10, 2),
    notes TEXT,
    status ENUM('completed', 'cancelled', 'refunded') DEFAULT 'completed',
    is_synced BOOLEAN DEFAULT FALSE,
    synced_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE,
    FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_org (organization_id),
    INDEX idx_store (store_id),
    INDEX idx_terminal (terminal_id),
    INDEX idx_receipt (receipt_number),
    INDEX idx_date (date),
    INDEX idx_cashier (cashier_id),
    INDEX idx_sync (is_synced),
    INDEX idx_status (status)
);

-- tax_details JSON:
{
  "rate": 12,
  "cgst": 10.71,
  "sgst": 10.71,
  "total": 21.42
}
-- OR for Nepal:
{
  "rate": 13,
  "vat": 13.00,
  "total": 13.00
}
```

### sale_items

```sql
CREATE TABLE sale_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    sale_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    batch_id BIGINT UNSIGNED,
    product_name VARCHAR(255) NOT NULL,
    product_sku VARCHAR(100) NOT NULL,
    quantity DECIMAL(10, 3) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    price_per_unit DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    tax_rate DECIMAL(5, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT,
    FOREIGN KEY (batch_id) REFERENCES product_batches(id) ON DELETE SET NULL,
    INDEX idx_sale (sale_id),
    INDEX idx_variant (product_variant_id),
    INDEX idx_batch (batch_id)
);

-- Snapshot of product details at time of sale
-- batch_id links to FIFO allocation
```

### sale_payments

```sql
CREATE TABLE sale_payments (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    sale_id BIGINT UNSIGNED NOT NULL,
    payment_method ENUM('cash', 'upi', 'card', 'esewa', 'khalti', 'other') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_gateway VARCHAR(50),
    transaction_id VARCHAR(255),
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'completed',
    payment_response JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    INDEX idx_sale (sale_id),
    INDEX idx_transaction (transaction_id)
);

-- For future split payments support
```

---

## 👤 Customer Management (Optional)

### customers

```sql
CREATE TABLE customers (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    customer_code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) UNIQUE,
    email VARCHAR(255) UNIQUE,
    address TEXT,
    city VARCHAR(100),
    date_of_birth DATE,
    total_purchases INT DEFAULT 0,
    total_spent DECIMAL(10, 2) DEFAULT 0,
    loyalty_points INT DEFAULT 0,
    notes TEXT,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_org (organization_id),
    INDEX idx_code (customer_code),
    INDEX idx_phone (phone),
    INDEX idx_email (email)
);

-- customer_code: AUTO-generated like CUST-0001
```

---

## ⚙️ Configuration & White-labeling

### receipt_templates

```sql
CREATE TABLE receipt_templates (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    template_type ENUM('thermal', 'a4', 'a5') DEFAULT 'thermal',
    header_text VARCHAR(500),
    footer_text VARCHAR(500),
    blessing_message VARCHAR(500),
    show_logo BOOLEAN DEFAULT TRUE,
    show_tax_breakdown BOOLEAN DEFAULT TRUE,
    show_barcode BOOLEAN DEFAULT FALSE,
    template_json JSON,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_org (organization_id),
    INDEX idx_default (is_default)
);

-- template_json: Complete layout configuration
{
  "width": 80,
  "font_size": 12,
  "sections": {
    "header": {...},
    "items": {...},
    "footer": {...}
  }
}
```

### feature_flags

```sql
CREATE TABLE feature_flags (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED,
    store_id BIGINT UNSIGNED,
    feature_key VARCHAR(100) NOT NULL,
    enabled BOOLEAN DEFAULT TRUE,
    config JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    UNIQUE KEY unique_feature (organization_id, store_id, feature_key),
    INDEX idx_org (organization_id),
    INDEX idx_store (store_id),
    INDEX idx_key (feature_key)
);

-- feature_key examples:
-- inventory_tracking, batch_tracking, offline_mode,
-- barcode_scanning, customer_management, loyalty_program
```

---

## 🔄 Sync Management

### sync_queue

```sql
CREATE TABLE sync_queue (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NOT NULL,
    terminal_id BIGINT UNSIGNED NOT NULL,
    sync_type ENUM('sale', 'inventory', 'product', 'config', 'customer') NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    action ENUM('create', 'update', 'delete') NOT NULL,
    payload JSON NOT NULL,
    status ENUM('pending', 'syncing', 'completed', 'failed') DEFAULT 'pending',
    priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
    attempts INT DEFAULT 0,
    last_attempt_at TIMESTAMP,
    synced_at TIMESTAMP,
    error_message TEXT,
    created_at TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE,
    INDEX idx_org (organization_id),
    INDEX idx_store (store_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_created (created_at),
    INDEX idx_entity (entity_type, entity_id)
);

-- payload JSON: Complete entity data for sync
```

### sync_logs

```sql
CREATE TABLE sync_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NOT NULL,
    terminal_id BIGINT UNSIGNED NOT NULL,
    sync_batch_id VARCHAR(100),
    records_synced INT DEFAULT 0,
    records_failed INT DEFAULT 0,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    status ENUM('success', 'partial', 'failed') NOT NULL,
    errors JSON,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE,
    INDEX idx_org (organization_id),
    INDEX idx_store (store_id),
    INDEX idx_batch (sync_batch_id),
    INDEX idx_started (started_at)
);

-- Track sync history for debugging
```

---

## 📈 Additional Tables (Optional/Future)

### suppliers

```sql
CREATE TABLE suppliers (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    phone VARCHAR(20),
    email VARCHAR(255),
    address TEXT,
    gst_number VARCHAR(50),
    pan_number VARCHAR(50),
    notes TEXT,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_org (organization_id)
);
```

### purchases

```sql
CREATE TABLE purchases (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    organization_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED,
    purchase_number VARCHAR(100) UNIQUE NOT NULL,
    purchase_date DATE NOT NULL,
    invoice_number VARCHAR(100),
    subtotal DECIMAL(10, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) DEFAULT 0,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('pending', 'partial', 'paid') DEFAULT 'pending',
    notes TEXT,
    created_by BIGINT UNSIGNED,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_org (organization_id),
    INDEX idx_store (store_id),
    INDEX idx_date (purchase_date)
);
```

### purchase_items

```sql
CREATE TABLE purchase_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    purchase_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    batch_number VARCHAR(100),
    quantity DECIMAL(10, 3) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    cost_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) DEFAULT 0,
    total DECIMAL(10, 2) NOT NULL,
    manufactured_date DATE,
    expiry_date DATE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT,
    INDEX idx_purchase (purchase_id),
    INDEX idx_variant (product_variant_id)
);
```

---

## 🔑 Important Indexes Summary

```sql
-- Performance-critical indexes

-- Multi-tenant queries
CREATE INDEX idx_org_store ON sales(organization_id, store_id, date);
CREATE INDEX idx_org_product ON products(organization_id, active);

-- POS searches
CREATE FULLTEXT INDEX idx_product_search ON products(name, name_nepali, name_sanskrit);
CREATE INDEX idx_variant_barcode ON product_variants(barcode);
CREATE INDEX idx_product_sku ON products(sku);

-- Inventory FIFO
CREATE INDEX idx_batch_fifo ON product_batches(product_variant_id, store_id, expiry_date, created_at);
CREATE INDEX idx_batch_expiring ON product_batches(expiry_date, quantity_remaining);

-- Sales reporting
CREATE INDEX idx_sales_date_store ON sales(store_id, date, status);
CREATE INDEX idx_sales_cashier_date ON sales(cashier_id, date);

-- Sync queries
CREATE INDEX idx_sync_pending ON sync_queue(status, priority, created_at);
CREATE INDEX idx_sales_unsync ON sales(is_synced, created_at);
```

---

## 🎯 Query Examples for Common Operations

### 1. Create Sale with FIFO Batch Allocation

```sql
-- Step 1: Get available batches (FIFO order)
SELECT id, quantity_remaining
FROM product_batches
WHERE product_variant_id = ? 
  AND store_id = ?
  AND quantity_remaining > 0
  AND (expiry_date IS NULL OR expiry_date > CURDATE())
ORDER BY expiry_date ASC, created_at ASC;

-- Step 2: Insert sale
INSERT INTO sales (...) VALUES (...);

-- Step 3: Insert sale items with batch allocation
INSERT INTO sale_items (sale_id, batch_id, ...) VALUES (...);

-- Step 4: Deduct from batches (FIFO)
UPDATE product_batches 
SET quantity_remaining = quantity_remaining - ?,
    quantity_sold = quantity_sold + ?
WHERE id = ?;

-- Step 5: Update stock levels
UPDATE stock_levels 
SET quantity = quantity - ?
WHERE product_variant_id = ? AND store_id = ?;

-- Step 6: Log inventory movement
INSERT INTO inventory_movements (...) VALUES (...);
```

### 2. Low Stock Alert

```sql
SELECT 
    p.name,
    pv.pack_size,
    pv.unit,
    sl.quantity,
    sl.reorder_level,
    s.name as store_name
FROM stock_levels sl
JOIN product_variants pv ON sl.product_variant_id = pv.id
JOIN products p ON pv.product_id = p.id
JOIN stores s ON sl.store_id = s.id
WHERE sl.store_id = ?
  AND sl.quantity <= sl.reorder_level
  AND p.active = TRUE
ORDER BY sl.quantity ASC;
```

### 3. Expiring Products (Next 6 Months)

```sql
SELECT 
    p.name,
    pv.pack_size,
    pv.unit,
    pb.batch_number,
    pb.expiry_date,
    pb.quantity_remaining,
    DATEDIFF(pb.expiry_date, CURDATE()) as days_to_expiry
FROM product_batches pb
JOIN product_variants pv ON pb.product_variant_id = pv.id
JOIN products p ON pv.product_id = p.id
WHERE pb.store_id = ?
  AND pb.quantity_remaining > 0
  AND pb.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
ORDER BY pb.expiry_date ASC;
```

### 4. Daily Sales Report

```sql
SELECT 
    DATE(created_at) as sale_date,
    COUNT(*) as total_transactions,
    SUM(subtotal) as subtotal,
    SUM(tax_amount) as tax,
    SUM(discount_amount) as discount,
    SUM(total_amount) as total_sales,
    payment_method,
    COUNT(DISTINCT cashier_id) as cashiers
FROM sales
WHERE store_id = ?
  AND DATE(created_at) = CURDATE()
  AND status = 'completed'
GROUP BY payment_method;
```

### 5. Inventory Valuation

```sql
SELECT 
    p.name,
    pv.pack_size,
    pv.unit,
    sl.quantity,
    COALESCE(AVG(pb.purchase_price), pv.cost_price) as avg_cost,
    sl.quantity * COALESCE(AVG(pb.purchase_price), pv.cost_price) as valuation
FROM stock_levels sl
JOIN product_variants pv ON sl.product_variant_id = pv.id
JOIN products p ON pv.product_id = p.id
LEFT JOIN product_batches pb ON pv.id = pb.product_variant_id AND pb.store_id = sl.store_id
WHERE sl.store_id = ?
  AND sl.quantity > 0
GROUP BY sl.id, p.name, pv.pack_size, pv.unit, sl.quantity, pv.cost_price
ORDER BY valuation DESC;
```

---

## 🚀 Migration Order

```
1. organizations
2. stores
3. terminals
4. roles
5. users
6. categories
7. products
8. product_variants
9. product_store_pricing
10. product_batches
11. stock_levels
12. inventory_movements
13. customers
14. sales
15. sale_items
16. sale_payments
17. receipt_templates
18. feature_flags
19. sync_queue
20. sync_logs
21. suppliers (optional)
22. purchases (optional)
23. purchase_items (optional)
```

---

## 💾 SQLite vs PostgreSQL Differences

### Local (SQLite)
- File-based: `storage/app/database/pos.sqlite`
- No concurrent writes (single terminal OK)
- Fast for read-heavy operations
- Perfect for offline POS

### Cloud (PostgreSQL)
- Client-server architecture
- Concurrent connections
- Advanced features (JSON operators, full-text search)
- Better for multi-store consolidation

### Schema Compatibility
```php
// Laravel migrations handle both automatically
Schema::create('sales', function (Blueprint $table) {
    $table->id();
    $table->json('tax_details');  // Works in both
    $table->decimal('total', 10, 2);
});
```

---

## 🔒 Data Integrity Rules

### Constraints
1. **Foreign keys**: Enforce referential integrity
2. **Unique constraints**: Prevent duplicates (SKU, receipt number)
3. **Check constraints**: Business rule validation
4. **Soft deletes**: Never hard-delete (use deleted_at)

### Transactions Required
- Sale creation (sale + items + inventory deduction)
- Inventory adjustments
- Batch allocation (FIFO)
- Multi-payment processing

---

## 📊 Data Retention Policy

```
Sales: Lifetime (never delete)
Inventory movements: Lifetime (audit trail)
Sync logs: 90 days
Deleted entities: Soft delete (recoverable)
Backups: 30 days rolling
```

---

**This schema supports:**
✅ Multi-tenant isolation  
✅ Offline-first operations  
✅ FIFO inventory tracking  
✅ Multi-country tax systems  
✅ White-label customization  
✅ Audit trail compliance  
✅ Scalability (1 to 100+ stores)

**Ready for Laravel migrations! 🚀**

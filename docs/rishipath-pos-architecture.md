# Rishipath POS - Complete Architecture Documentation

> **Multi-tenant, White-label Ready, Offline-first Point of Sale System**  
> For Ayurvedic/Herbal Product Stores (India & Nepal)  
> **Generated: December 31, 2025**

---

## 🎯 Project Overview

### Vision
A **white-label ready POS system** designed specifically for:
- Ayurvedic medicine stores
- Herbal product retailers
- Spiritual/foundation outlets
- Multi-country operations (India & Nepal)

### Core Principles
1. **Offline-first**: Works without internet
2. **Multi-tenant**: One codebase, multiple organizations
3. **White-label ready**: Rebrandable without code changes
4. **Localization-native**: English, Nepali, Hindi support
5. **Feature-toggle driven**: Enable/disable features per tenant

---

## 📋 Key Decisions Made

### Business Rules
- **Receipt numbering**: Local generation with format `RSH-YYYYMMDD-###`
- **Inventory**: Real-time deduction with FIFO batch tracking
- **Payments**: Start with cash-only, expand to digital later
- **Returns**: Not in MVP (manual process)
- **Sync conflicts**: Sales always win, inventory needs manual review

### Country Support
- **India**: GST (5%, 12%, 18%), Razorpay, MRP pricing
- **Nepal**: VAT (13%), eSewa/Khalti, selling price + VAT

### Product Types
1. Choornas (Ayurvedic powders) - Weight-based
2. Tailams (Medicated oils) - Volume-based
3. Ghritams (Medicated ghee) - Volume-based
4. Rasayanas (Rejuvenatives) - Weight-based
5. Capsules/Vati - Piece-based
6. Special items (Tea, Honey, Pottalis)

---

## 🏗️ System Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────┐
│                   POS FRONTEND                          │
│              (Vue 3 + Vite + Tailwind)                  │
│                                                         │
│  Components:                                            │
│  • Billing Interface (offline-capable)                 │
│  • Product Search (Sanskrit/English)                   │
│  • Cart Management                                     │
│  • Receipt Preview                                     │
│  • Settings & Config                                   │
└────────────────────┬────────────────────────────────────┘
                     │ HTTP API (localhost:8000)
┌────────────────────▼────────────────────────────────────┐
│              LOCAL LARAVEL SERVER                       │
│              (Runs on POS machine)                      │
│                                                         │
│  • SQLite Database (local)                             │
│  • Filament Admin Panel                                │
│  • Receipt Printing Service                            │
│  • Inventory Engine (FIFO)                             │
│  • Tax Calculator (GST/VAT)                            │
│  • Sync Queue Manager                                  │
└────────────────────┬────────────────────────────────────┘
                     │ HTTPS (when online)
┌────────────────────▼────────────────────────────────────┐
│              CLOUD LARAVEL SERVER                       │
│                                                         │
│  • PostgreSQL Database                                 │
│  • Central Dashboard                                   │
│  • Reports & Analytics                                 │
│  • Multi-tenant Management                             │
│  • Backup Service                                      │
└─────────────────────────────────────────────────────────┘
```

---

## 🗄️ Database Schema

### Core Multi-Tenant Tables

```sql
-- Organizations (White-label entities)
organizations
├─ id
├─ slug (unique: rishipath, another-ashram)
├─ name (Rishipath Bhaisajyashala)
├─ legal_name
├─ country_code (IN/NP)
├─ currency (INR/NPR)
├─ timezone (Asia/Kolkata)
├─ locale (en/ne/hi)
├─ config (JSON: branding, features, tax)
├─ active
└─ timestamps

-- Stores (Physical locations per organization)
stores
├─ id
├─ organization_id
├─ code (MAIN, BRANCH-01)
├─ name
├─ address
├─ city
├─ state
├─ country_code
├─ postal_code
├─ phone
├─ email
├─ tax_number (GSTIN/PAN)
├─ license_number (Drug License for Ayurvedic)
├─ latitude
├─ longitude
├─ config (JSON: hours, receipt template)
├─ active
└─ timestamps

-- Terminals (Cash registers / POS counters)
terminals
├─ id
├─ store_id
├─ code (COUNTER-01)
├─ name (Main Counter)
├─ device_id (unique machine identifier)
├─ printer_config (JSON)
├─ scanner_config (JSON)
├─ last_receipt_number
├─ last_synced_at
├─ active
└─ timestamps
```

### User Management

```sql
users
├─ id
├─ organization_id
├─ name
├─ email
├─ phone
├─ password
├─ pin (4-digit for quick cashier login)
├─ role_id
├─ stores (JSON: array of accessible store IDs)
├─ permissions (JSON)
├─ active
├─ last_login_at
└─ timestamps

roles
├─ id
├─ organization_id (nullable for system roles)
├─ name (Admin, Manager, Cashier)
├─ slug (admin, manager, cashier)
├─ permissions (JSON: feature flags)
├─ is_system_role (boolean)
└─ timestamps
```

### Product Management

```sql
-- Product Categories
categories
├─ id
├─ organization_id (nullable for global)
├─ parent_id (for subcategories)
├─ name
├─ name_nepali
├─ name_hindi
├─ slug
├─ product_type (choorna/tailam/ghritam/etc)
├─ config (JSON: unit_type, tax_category)
├─ sort_order
├─ active
└─ timestamps

-- Products (Base products)
products
├─ id
├─ organization_id
├─ category_id
├─ sku (RSH-CH-001)
├─ name (Amalaki Choorna)
├─ name_nepali (आमलकी चूर्ण)
├─ name_hindi (आमलकी चूर्ण)
├─ name_sanskrit (आमलकी चूर्ण)
├─ description
├─ product_type (choorna/tailam/capsules/etc)
├─ unit_type (weight/volume/piece)
├─ has_variants (boolean)
├─ tax_category (essential/standard/luxury)
├─ requires_batch (boolean)
├─ requires_expiry (boolean)
├─ shelf_life_months
├─ is_prescription_required (boolean)
├─ ingredients (JSON)
├─ usage_instructions (TEXT)
├─ image_url
├─ active
├─ deleted_at (soft delete)
└─ timestamps

-- Product Variants (Different pack sizes)
product_variants
├─ id
├─ product_id
├─ sku (RSH-CH-001-100)
├─ pack_size (100)
├─ unit (GMS/ML/CAPSULES)
├─ base_price
├─ mrp_india (inclusive of GST)
├─ selling_price_nepal (exclusive of VAT)
├─ cost_price
├─ barcode
├─ hsn_code (for India)
├─ weight (for shipping)
├─ active
└─ timestamps

-- Product Store Pricing (Store-specific overrides)
product_store_pricing
├─ id
├─ product_variant_id
├─ store_id
├─ custom_price (nullable)
├─ custom_tax_rate (nullable)
├─ reorder_level
├─ max_stock_level
└─ timestamps

-- Inventory Batches
product_batches
├─ id
├─ product_variant_id
├─ store_id
├─ batch_number
├─ manufactured_date
├─ expiry_date
├─ purchase_date
├─ purchase_price
├─ supplier_id
├─ quantity_received
├─ quantity_remaining
├─ quantity_sold
├─ quantity_damaged
├─ quantity_returned
├─ notes
└─ timestamps

-- Stock Levels (Current stock per store)
stock_levels
├─ id
├─ product_variant_id
├─ store_id
├─ quantity
├─ reserved_quantity (in pending sales)
├─ available_quantity (quantity - reserved)
├─ reorder_level
├─ last_counted_at
├─ last_movement_at
└─ timestamps

-- Inventory Movements (Audit trail)
inventory_movements
├─ id
├─ organization_id
├─ store_id
├─ product_variant_id
├─ batch_id (nullable)
├─ type (purchase/sale/adjustment/transfer/damage/return)
├─ quantity
├─ unit
├─ from_quantity
├─ to_quantity
├─ reference_type (Sale/Purchase/Adjustment)
├─ reference_id
├─ cost_price (for valuation)
├─ user_id
├─ notes
├─ created_at
└─ INDEX on (store_id, product_variant_id, created_at)
```

### Sales Management

```sql
-- Sales (Transactions)
sales
├─ id
├─ organization_id
├─ store_id
├─ terminal_id
├─ receipt_number (RSH-20250101-001)
├─ date
├─ time
├─ cashier_id
├─ customer_id (nullable)
├─ customer_name (optional)
├─ customer_phone (optional)
├─ customer_email (optional)
├─ subtotal
├─ discount_amount
├─ discount_type (percentage/fixed)
├─ discount_reason
├─ tax_amount
├─ tax_details (JSON: GST/VAT breakdown)
├─ total_amount
├─ payment_method (cash/upi/esewa/khalti/card)
├─ payment_status (paid/pending/partial)
├─ payment_reference (transaction ID)
├─ amount_paid
├─ amount_change
├─ notes
├─ status (completed/cancelled/refunded)
├─ is_synced (boolean)
├─ synced_at
├─ created_at
├─ updated_at
└─ INDEX on (store_id, receipt_number, is_synced)

-- Sale Items
sale_items
├─ id
├─ sale_id
├─ product_variant_id
├─ batch_id (nullable for FIFO tracking)
├─ product_name (snapshot)
├─ product_sku
├─ quantity
├─ unit
├─ price_per_unit (at time of sale)
├─ subtotal
├─ discount_amount
├─ tax_rate
├─ tax_amount
├─ total
└─ timestamps

-- Sale Payments (for split payments future)
sale_payments
├─ id
├─ sale_id
├─ payment_method
├─ amount
├─ payment_gateway
├─ transaction_id
├─ payment_status
├─ payment_response (JSON)
└─ timestamps
```

### Customer Management (Optional MVP+)

```sql
customers
├─ id
├─ organization_id
├─ customer_code (auto-generated)
├─ name
├─ phone
├─ email
├─ address
├─ city
├─ date_of_birth
├─ total_purchases
├─ total_spent
├─ loyalty_points (future)
├─ notes
├─ active
└─ timestamps
```

### Configuration & White-labeling

```sql
-- Organization Configuration (White-label settings)
organization_configs
├─ id
├─ organization_id
├─ key (branding.logo/features.inventory_tracking/tax.gst_enabled)
├─ value (JSON/TEXT)
├─ type (string/boolean/integer/json)
└─ timestamps

-- Receipt Templates
receipt_templates
├─ id
├─ organization_id
├─ name (Default/Donation/Tax Invoice)
├─ template_type (thermal/a4/a5)
├─ header_text
├─ footer_text
├─ blessing_message
├─ show_logo (boolean)
├─ show_tax_breakdown (boolean)
├─ show_barcode (boolean)
├─ template_json (JSON: full layout config)
├─ is_default
└─ timestamps

-- Feature Toggles
feature_flags
├─ id
├─ organization_id (nullable for global)
├─ store_id (nullable for store-specific)
├─ feature_key (inventory_tracking/batch_tracking/offline_mode)
├─ enabled (boolean)
├─ config (JSON: feature-specific settings)
└─ timestamps
```

### Sync Management

```sql
-- Sync Queue (Offline → Cloud sync)
sync_queue
├─ id
├─ organization_id
├─ store_id
├─ terminal_id
├─ sync_type (sale/inventory/product/config)
├─ entity_type (Sale/Product/etc)
├─ entity_id
├─ action (create/update/delete)
├─ payload (JSON)
├─ status (pending/syncing/completed/failed)
├─ attempts
├─ last_attempt_at
├─ synced_at
├─ error_message
└─ created_at

-- Sync Logs
sync_logs
├─ id
├─ organization_id
├─ store_id
├─ terminal_id
├─ sync_batch_id
├─ records_synced
├─ records_failed
├─ started_at
├─ completed_at
├─ status
└─ errors (JSON)
```

---

## 🎨 Configuration System

### Organization Config Structure (JSON in DB)

```json
{
  "branding": {
    "logo_url": "/storage/orgs/rishipath/logo.png",
    "primary_color": "#2D5016",
    "secondary_color": "#8B4513",
    "store_name": "Rishipath Bhaisajyashala",
    "tagline": "Ancient Wisdom, Modern Wellness",
    "website": "https://rishipath.com"
  },
  "features": {
    "inventory_tracking": true,
    "batch_tracking": true,
    "expiry_tracking": true,
    "offline_mode": true,
    "barcode_scanning": true,
    "weight_scale": false,
    "customer_management": false,
    "loyalty_program": false,
    "donations": false,
    "prescriptions": true,
    "multi_currency": false
  },
  "tax": {
    "enabled": true,
    "system": "GST",
    "rates": {
      "essential": 5,
      "standard": 12,
      "luxury": 18
    },
    "inclusive": true,
    "tax_number": "29XXXXX1234X1X1",
    "tax_label": "GSTIN"
  },
  "receipt": {
    "template_id": 1,
    "header": "TAX INVOICE",
    "footer": "Thank You! Visit Again",
    "blessing": "॥ ॐ नमः शिवाय ॥",
    "show_logo": true,
    "show_address": true,
    "show_tax_breakdown": true,
    "show_license": true,
    "thermal_width": 80
  },
  "inventory": {
    "allow_negative_stock": false,
    "valuation_method": "FIFO",
    "auto_deduct": true,
    "low_stock_alert": true,
    "expiry_alert_months": 6
  },
  "localization": {
    "default_language": "en",
    "supported_languages": ["en", "ne", "hi"],
    "currency_symbol": "₹",
    "date_format": "DD/MM/YYYY",
    "time_format": "24h",
    "number_format": "en-IN"
  },
  "payments": {
    "cash_enabled": true,
    "digital_enabled": false,
    "gateways": {
      "razorpay": {
        "enabled": false,
        "key": "",
        "methods": ["upi", "card"]
      },
      "esewa": {
        "enabled": false,
        "merchant_id": ""
      }
    }
  },
  "backup": {
    "auto_backup": true,
    "backup_frequency": "daily",
    "backup_time": "23:00",
    "retention_days": 30
  }
}
```

---

## 🔐 Permission System

### Role-Based Access Control (RBAC)

```php
// Permission structure
[
  'sales' => [
    'create' => 'Can create sales',
    'view' => 'Can view sales',
    'edit' => 'Can edit sales (within same day)',
    'delete' => 'Can cancel sales',
    'discount' => 'Can apply discounts',
    'override_price' => 'Can override product price',
  ],
  'products' => [
    'create' => 'Can add new products',
    'view' => 'Can view products',
    'edit' => 'Can edit products',
    'delete' => 'Can delete products',
    'manage_inventory' => 'Can adjust inventory',
  ],
  'inventory' => [
    'view' => 'Can view inventory',
    'adjust' => 'Can make adjustments',
    'purchase' => 'Can record purchases',
    'transfer' => 'Can transfer between stores',
  ],
  'reports' => [
    'sales_daily' => 'Daily sales report',
    'sales_monthly' => 'Monthly sales report',
    'inventory_valuation' => 'Inventory valuation report',
    'tax_reports' => 'GST/VAT reports',
    'export' => 'Export reports',
  ],
  'settings' => [
    'view' => 'Can view settings',
    'edit' => 'Can edit settings',
    'users' => 'Can manage users',
    'roles' => 'Can manage roles',
    'branding' => 'Can customize branding',
  ],
  'system' => [
    'backup' => 'Can create backups',
    'restore' => 'Can restore from backup',
    'sync' => 'Can trigger sync',
    'logs' => 'Can view system logs',
  ],
]
```

### Default Roles

```php
// System roles (pre-configured)
[
  'super_admin' => [
    'label' => 'Super Admin',
    'permissions' => ['*'], // All permissions
    'description' => 'Full system access',
  ],
  'admin' => [
    'label' => 'Admin',
    'permissions' => [
      'sales.*',
      'products.*',
      'inventory.*',
      'reports.*',
      'settings.view',
      'settings.edit',
      'settings.users',
    ],
    'description' => 'Store administrator',
  ],
  'manager' => [
    'label' => 'Manager',
    'permissions' => [
      'sales.*',
      'products.view',
      'products.edit',
      'inventory.view',
      'inventory.adjust',
      'reports.sales_daily',
      'reports.sales_monthly',
    ],
    'description' => 'Store manager',
  ],
  'cashier' => [
    'label' => 'Cashier',
    'permissions' => [
      'sales.create',
      'sales.view',
      'products.view',
    ],
    'description' => 'Point of sale operator',
  ],
]
```

---

## 🌍 Multi-Country Configuration

### India Configuration

```php
// config/countries/india.php
return [
    'code' => 'IN',
    'name' => 'India',
    'currency' => 'INR',
    'currency_symbol' => '₹',
    'locale' => 'en-IN',
    
    'tax' => [
        'system' => 'GST',
        'label' => 'GST',
        'number_label' => 'GSTIN',
        'number_format' => '##AAAAA####A#Z#',
        'inclusive' => true, // MRP includes GST
        'rates' => [
            'essential' => 5,
            'standard' => 12,
            'luxury' => 18,
        ],
        'components' => ['CGST', 'SGST'],
    ],
    
    'payment_gateways' => [
        'razorpay' => [
            'name' => 'Razorpay',
            'methods' => ['upi', 'card', 'netbanking', 'wallet'],
            'test_mode' => true,
        ],
    ],
    
    'receipt' => [
        'header' => 'TAX INVOICE',
        'footer' => 'Thank You for Your Purchase',
        'show_hsn' => false,
        'show_tax_breakdown' => true,
    ],
    
    'formats' => [
        'date' => 'd/m/Y',
        'time' => 'H:i',
        'datetime' => 'd/m/Y H:i',
        'number' => [
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'decimals' => 2,
        ],
    ],
    
    'regulations' => [
        'drug_license_required' => true,
        'fssai_required' => true,
    ],
];
```

### Nepal Configuration

```php
// config/countries/nepal.php
return [
    'code' => 'NP',
    'name' => 'Nepal',
    'currency' => 'NPR',
    'currency_symbol' => 'रू',
    'locale' => 'ne-NP',
    
    'tax' => [
        'system' => 'VAT',
        'label' => 'VAT',
        'number_label' => 'PAN',
        'number_format' => '#########',
        'inclusive' => false, // VAT added on top
        'rates' => [
            'standard' => 13,
            'exempt' => 0,
        ],
        'components' => ['VAT'],
    ],
    
    'payment_gateways' => [
        'esewa' => [
            'name' => 'eSewa',
            'methods' => ['wallet'],
            'test_mode' => true,
        ],
        'khalti' => [
            'name' => 'Khalti',
            'methods' => ['wallet'],
            'test_mode' => true,
        ],
    ],
    
    'receipt' => [
        'header' => 'बीजक (INVOICE)',
        'footer' => 'धन्यवाद! फेरि पधार्नुहोस्',
        'show_pan' => true,
        'show_tax_breakdown' => true,
        'nepali_numerals' => false, // Optional
    ],
    
    'formats' => [
        'date' => 'Y/m/d',
        'time' => 'H:i',
        'datetime' => 'Y/m/d H:i',
        'number' => [
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'decimals' => 2,
        ],
    ],
    
    'regulations' => [
        'ayurvedic_license_required' => true,
    ],
];
```

---

## 🔌 API Endpoints

### Public Routes (No Auth Required)

```
GET  /api/ping                    # Health check
POST /api/auth/login              # Login (email/password or PIN)
POST /api/auth/logout             # Logout
```

### Protected Routes (Requires Authentication)

#### Sales
```
GET    /api/sales                 # List sales (with filters)
GET    /api/sales/{id}            # Get sale details
POST   /api/sales                 # Create new sale
PUT    /api/sales/{id}            # Update sale (same-day only)
DELETE /api/sales/{id}            # Cancel sale
POST   /api/sales/{id}/print      # Reprint receipt
GET    /api/sales/today/summary   # Today's summary
```

#### Products
```
GET    /api/products              # List products (search, filter)
GET    /api/products/{id}         # Get product details
POST   /api/products              # Create product
PUT    /api/products/{id}         # Update product
DELETE /api/products/{id}         # Soft delete product
GET    /api/products/search       # Quick search (for POS)
GET    /api/products/{id}/stock   # Get current stock
GET    /api/products/{id}/batches # Get batches (FIFO order)
```

#### Inventory
```
GET    /api/inventory/stock       # Stock levels
POST   /api/inventory/adjust      # Manual adjustment
POST   /api/inventory/purchase    # Record purchase
GET    /api/inventory/movements   # Movement history
GET    /api/inventory/low-stock   # Low stock alerts
GET    /api/inventory/expiring    # Expiring products
```

#### Customers (Optional)
```
GET    /api/customers             # List customers
GET    /api/customers/{id}        # Get customer
POST   /api/customers             # Create customer
PUT    /api/customers/{id}        # Update customer
GET    /api/customers/{id}/sales  # Customer purchase history
```

#### Reports
```
GET    /api/reports/sales/daily           # Daily sales report
GET    /api/reports/sales/monthly         # Monthly sales report
GET    /api/reports/inventory/valuation   # Inventory valuation
GET    /api/reports/tax/gst               # GST report (India)
GET    /api/reports/tax/vat               # VAT report (Nepal)
POST   /api/reports/export                # Export report (CSV/Excel)
```

#### Configuration
```
GET    /api/config                # Get all config
PUT    /api/config                # Update config
GET    /api/config/features       # Get feature flags
PUT    /api/config/features       # Update feature flags
```

#### Sync (Local ↔ Cloud)
```
POST   /api/sync/push             # Push local data to cloud
POST   /api/sync/pull             # Pull cloud data to local
GET    /api/sync/status           # Sync status
GET    /api/sync/conflicts        # List conflicts
POST   /api/sync/resolve          # Resolve conflicts
```

---

## 📁 Project Folder Structure

### Laravel Backend

```
rishipath-pos/
│
├── app/
│   ├── Actions/                      # Single-purpose action classes
│   │   ├── Sale/
│   │   │   ├── CreateSaleAction.php
│   │   │   ├── CalculateSaleTaxAction.php
│   │   │   └── ProcessPaymentAction.php
│   │   ├── Inventory/
│   │   │   ├── DeductInventoryAction.php
│   │   │   ├── AllocateBatchAction.php (FIFO)
│   │   │   └── AdjustStockAction.php
│   │   └── Sync/
│   │       ├── PushSalesToCloudAction.php
│   │       └── PullProductsFromCloudAction.php
│   │
│   ├── Contracts/                    # Interfaces
│   │   ├── PaymentGateway.php
│   │   ├── TaxCalculator.php
│   │   ├── ReceiptPrinter.php
│   │   └── SyncStrategy.php
│   │
│   ├── DataTransferObjects/          # DTOs for type safety
│   │   ├── SaleData.php
│   │   ├── ProductData.php
│   │   ├── TaxBreakdown.php
│   │   └── ReceiptData.php
│   │
│   ├── Enums/                        # PHP 8.1 Enums
│   │   ├── PaymentMethod.php
│   │   ├── InventoryMovementType.php
│   │   ├── TaxCategory.php
│   │   └── UserRole.php
│   │
│   ├── Filament/                     # Filament Admin Panel
│   │   ├── Resources/
│   │   │   ├── ProductResource.php
│   │   │   ├── SaleResource.php
│   │   │   ├── InventoryResource.php
│   │   │   └── UserResource.php
│   │   └── Pages/
│   │       ├── Dashboard.php
│   │       └── Settings.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── SaleController.php
│   │   │   │   ├── ProductController.php
│   │   │   │   ├── InventoryController.php
│   │   │   │   ├── ConfigController.php
│   │   │   │   └── SyncController.php
│   │   │   └── Auth/
│   │   │       └── LoginController.php
│   │   ├── Middleware/
│   │   │   ├── CheckOrganization.php
│   │   │   ├── CheckStoreAccess.php
│   │   │   └── CheckFeatureFlag.php
│   │   └── Resources/                # API Resources
│   │       ├── SaleResource.php
│   │       ├── ProductResource.php
│   │       └── InventoryMovementResource.php
│   │
│   ├── Models/
│   │   ├── Organization.php
│   │   ├── Store.php
│   │   ├── Terminal.php
│   │   ├── User.php
│   │   ├── Role.php
│   │   ├── Category.php
│   │   ├── Product.php
│   │   ├── ProductVariant.php
│   │   ├── ProductBatch.php
│   │   ├── StockLevel.php
│   │   ├── InventoryMovement.php
│   │   ├── Sale.php
│   │   ├── SaleItem.php
│   │   ├── Customer.php
│   │   ├── ReceiptTemplate.php
│   │   ├── FeatureFlag.php
│   │   └── SyncQueue.php
│   │
│   ├── Observers/                    # Model observers
│   │   ├── SaleObserver.php         # Auto-deduct inventory
│   │   └── ProductBatchObserver.php # Track batch changes
│   │
│   ├── Policies/                     # Authorization policies
│   │   ├── SalePolicy.php
│   │   ├── ProductPolicy.php
│   │   └── SettingsPolicy.php
│   │
│   ├── Services/
│   │   ├── Tax/
│   │   │   ├── TaxCalculator.php
│   │   │   ├── GSTCalculator.php
│   │   │   ├── VATCalculator.php
│   │   │   └── TaxFactory.php
│   │   ├── Payment/
│   │   │   ├── PaymentGateway.php
│   │   │   ├── RazorpayGateway.php
│   │   │   ├── EsewaGateway.php
│   │   │   ├── KhaltiGateway.php
│   │   │   └── PaymentFactory.php
│   │   ├── Receipt/
│   │   │   ├── ReceiptGenerator.php
│   │   │   ├── ThermalPrinter.php
│   │   │   └── PDFReceipt.php
│   │   ├── Inventory/
│   │   │   ├── InventoryManager.php
│   │   │   ├── FIFOAllocator.php
│   │   │   └── StockValuation.php
│   │   ├── Sync/
│   │   │   ├── SyncManager.php
│   │   │   ├── SyncStrategy.php
│   │   │   └── ConflictResolver.php
│   │   └── Config/
│   │       ├── ConfigManager.php
│   │       └── FeatureFlagService.php
│   │
│   └── Traits/
│       ├── BelongsToOrganization.php
│       ├── BelongsToStore.php
│       └── HasFeatureFlags.php
│
├── bootstrap/
├── config/
│   ├── countries/
│   │   ├── india.php
│   │   └── nepal.php
│   ├── product-types.php
│   ├── features.php
│   └── pos.php                       # POS-specific config
│
├── database/
│   ├── factories/
│   ├── migrations/
│   │   ├── 2025_01_01_000001_create_organizations_table.php
│   │   ├── 2025_01_01_000002_create_stores_table.php
│   │   ├── 2025_01_01_000003_create_terminals_table.php
│   │   ├── 2025_01_01_000010_create_users_table.php
│   │   ├── 2025_01_01_000011_create_roles_table.php
│   │   ├── 2025_01_01_000020_create_categories_table.php
│   │   ├── 2025_01_01_000021_create_products_table.php
│   │   ├── 2025_01_01_000022_create_product_variants_table.php
│   │   ├── 2025_01_01_000023_create_product_batches_table.php
│   │   ├── 2025_01_01_000024_create_stock_levels_table.php
│   │   ├── 2025_01_01_000025_create_inventory_movements_table.php
│   │   ├── 2025_01_01_000030_create_sales_table.php
│   │   ├── 2025_01_01_000031_create_sale_items_table.php
│   │   ├── 2025_01_01_000040_create_customers_table.php
│   │   ├── 2025_01_01_000050_create_receipt_templates_table.php
│   │   ├── 2025_01_01_000051_create_feature_flags_table.php
│   │   └── 2025_01_01_000060_create_sync_queue_table.php
│   │
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── OrganizationSeeder.php
│       ├── RoleSeeder.php
│       ├── CategorySeeder.php
│       ├── ProductSeeder.php          # Your actual product catalog
│       └── FeatureFlagSeeder.php
│
├── resources/
│   ├── views/
│   │   ├── receipts/
│   │   │   ├── thermal.blade.php
│   │   │   └── a4.blade.php
│   │   └── reports/
│   │       ├── sales-daily.blade.php
│   │       └── inventory-valuation.blade.php
│   │
│   └── lang/
│       ├── en/
│       ├── ne/
│       └── hi/
│
├── routes/
│   ├── api.php
│   ├── web.php
│   └── console.php
│
├── storage/
│   ├── app/
│   │   ├── database/
│   │   │   └── pos.sqlite            # Local SQLite database
│   │   └── backups/                  # Auto backups
│   └── logs/
│
├── tests/
│   ├── Feature/
│   │   ├── Sale/
│   │   │   ├── CreateSaleTest.php
│   │   │   └── CalculateTaxTest.php
│   │   ├── Inventory/
│   │   │   ├── FIFOAllocationTest.php
│   │   │   └── StockDeductionTest.php
│   │   └── Sync/
│   │       └── SyncSalesTest.php
│   │
│   └── Unit/
│       ├── TaxCalculatorTest.php
│       └── ReceiptGeneratorTest.php
│
├── .env.example
├── .env.local.example               # Local POS setup
├── .env.cloud.example               # Cloud server setup
├── composer.json
└── artisan
```

### Vue 3 Frontend

```
rishipath-pos-frontend/
│
├── public/
│   ├── favicon.ico
│   └── assets/
│
├── src/
│   ├── assets/
│   │   ├── images/
│   │   ├── fonts/
│   │   └── styles/
│   │       ├── tailwind.css
│   │       └── global.css
│   │
│   ├── components/
│   │   ├── common/
│   │   │   ├── Button.vue
│   │   │   ├── Input.vue
│   │   │   ├── Modal.vue
│   │   │   ├── Loading.vue
│   │   │   └── Alert.vue
│   │   │
│   │   ├── pos/
│   │   │   ├── ProductSearch.vue
│   │   │   ├── ProductList.vue
│   │   │   ├── ProductCard.vue
│   │   │   ├── Cart.vue
│   │   │   ├── CartItem.vue
│   │   │   ├── PaymentPanel.vue
│   │   │   ├── ReceiptPreview.vue
│   │   │   └── Numpad.vue
│   │   │
│   │   ├── inventory/
│   │   │   ├── StockTable.vue
│   │   │   ├── BatchList.vue
│   │   │   ├── ExpiryAlerts.vue
│   │   │   └── StockAdjustment.vue
│   │   │
│   │   └── reports/
│   │       ├── SalesSummary.vue
│   │       ├── DailySales.vue
│   │       └── InventoryReport.vue
│   │
│   ├── composables/
│   │   ├── useAuth.js
│   │   ├── useCart.js
│   │   ├── useProducts.js
│   │   ├── useSales.js
│   │   ├── useInventory.js
│   │   ├── useConfig.js
│   │   ├── useSync.js
│   │   └── useOffline.js
│   │
│   ├── layouts/
│   │   ├── POSLayout.vue
│   │   ├── AdminLayout.vue
│   │   └── AuthLayout.vue
│   │
│   ├── pages/
│   │   ├── Login.vue
│   │   ├── POS.vue                   # Main billing screen
│   │   ├── Products.vue
│   │   ├── Inventory.vue
│   │   ├── Sales.vue
│   │   ├── Reports.vue
│   │   ├── Settings.vue
│   │   └── Sync.vue
│   │
│   ├── router/
│   │   └── index.js
│   │
│   ├── services/
│   │   ├── api.js                    # Axios instance
│   │   ├── auth.service.js
│   │   ├── product.service.js
│   │   ├── sale.service.js
│   │   ├── inventory.service.js
│   │   ├── sync.service.js
│   │   └── printer.service.js        # Printer communication
│   │
│   ├── stores/                       # Pinia stores
│   │   ├── auth.js
│   │   ├── cart.js
│   │   ├── products.js
│   │   ├── sales.js
│   │   ├── config.js
│   │   └── offline.js
│   │
│   ├── utils/
│   │   ├── formatters.js             # Currency, date formatters
│   │   ├── validators.js
│   │   ├── barcode.js
│   │   └── offline-queue.js
│   │
│   ├── App.vue
│   └── main.js
│
├── index.html
├── package.json
├── vite.config.js
├── tailwind.config.js
└── jsconfig.json
```

---

## 🔄 Sync Strategy

### Offline-First Approach

```
Local Operations (Always Fast):
1. Create sale → Save to SQLite
2. Deduct inventory → Update local stock
3. Generate receipt → Print immediately

Background Sync (When Online):
1. Queue sales for upload
2. Push to cloud every 5 minutes
3. Pull product/price updates
4. Resolve conflicts
```

### Sync Queue Priority

```php
[
  'high' => ['sales', 'payments'],       // Push immediately when online
  'medium' => ['inventory_adjustments'], // Push every 5 minutes
  'low' => ['customer_data', 'logs'],   // Push every hour
]
```

### Conflict Resolution Rules

```php
// Sales conflicts
- Local sale always wins (never reject)
- Cloud records for reporting only

// Inventory conflicts
- Manual review required
- Flag for manager approval

// Product updates
- Cloud wins (price/tax changes)
- Local overrides possible

// Config changes
- Cloud wins
- Apply on next restart
```

---

## 🧪 Testing Strategy

### Test Coverage Goals
- Unit tests: 80%+
- Feature tests: Critical paths 100%
- Integration tests: Sync, payments, tax calculations

### Key Test Scenarios

```php
// Sales
✅ Create sale with single item
✅ Create sale with multiple items
✅ Apply discount (percentage/fixed)
✅ Calculate GST correctly
✅ Calculate VAT correctly
✅ Handle insufficient stock
✅ FIFO batch allocation
✅ Generate receipt number

// Inventory
✅ Deduct stock on sale
✅ FIFO allocation across batches
✅ Low stock alerts
✅ Expiry alerts
✅ Stock adjustment with reason
✅ Prevent negative stock

// Sync
✅ Push sales when online
✅ Handle sync failures
✅ Resolve conflicts
✅ Retry failed syncs
✅ Validate data integrity

// Multi-tenancy
✅ Organization isolation
✅ Store-level data access
✅ Permission enforcement
```

---

## 📊 Reporting Requirements

### Daily Reports
- Daily sales summary
- Payment method breakdown
- Top-selling products
- Cashier performance
- Z-report (end-of-day)

### Monthly Reports
- Monthly sales trends
- Category-wise sales
- Inventory valuation
- GST/VAT report
- Profit analysis (if cost tracking enabled)

### Inventory Reports
- Current stock levels
- Low stock items
- Expiring products (next 6 months)
- Dead stock analysis
- Batch-wise stock

### Export Formats
- PDF (for printing)
- CSV (for Excel)
- JSON (for API integration)

---

## 🚀 Deployment Strategy

### Local POS Setup (Each Counter)

```bash
# Using Laravel Herd (recommended for Mac/Windows)
1. Install Herd: https://herd.laravel.com
2. Clone repository to ~/Herd/rishipath-pos
3. Copy .env.local to .env
4. Configure SQLite database
5. Run: php artisan migrate --seed
6. Start: http://rishipath-pos.test

# OR using traditional PHP
1. Install PHP 8.2+, Composer
2. Clone repository
3. composer install
4. php artisan serve
```

### Cloud Server Setup

```bash
# Using any VPS (DigitalOcean, Linode, etc.)
1. Ubuntu 22.04 LTS
2. Install: PHP 8.2, PostgreSQL, Nginx
3. Deploy Laravel app
4. Configure PostgreSQL
5. Setup SSL certificate
6. Configure cron for sync jobs
7. Setup daily backups
```

### Suggested Hosting
- **Local**: Laravel Herd (Mac/Windows) or Docker
- **Cloud**: DigitalOcean ($12/month droplet) or AWS Lightsail

---

## 🔒 Security Considerations

### Authentication
- JWT tokens for API
- PIN-based quick login for cashiers
- Session timeout (configurable)
- Two-factor authentication (optional)

### Authorization
- Role-based access control
- Store-level isolation
- Organization-level isolation
- Audit logs for sensitive operations

### Data Protection
- Encrypted database backups
- HTTPS only for cloud sync
- Sanitize all inputs
- SQL injection prevention (Eloquent ORM)
- XSS protection

### PCI Compliance (if handling cards)
- Never store card details
- Use payment gateway tokens only
- Secure payment webhooks

---

## 📈 Scalability Plan

### Phase 1: Single Store (MVP)
- 1 counter
- 1 printer
- Local SQLite
- Manual backup

### Phase 2: Multi-Counter
- 3-5 counters
- Shared local PostgreSQL
- Network printers
- Auto sync

### Phase 3: Multi-Store
- Multiple locations
- Central cloud database
- Store-to-store transfers
- Consolidated reporting

### Phase 4: White-label Ready
- Multiple organizations
- Custom branding per org
- Feature flags per tenant
- SaaS model (optional)

---

## 🛠️ Development Workflow

### For GitHub Copilot

```php
/**
 * COPILOT CONTEXT:
 * 
 * This is a white-label POS system for Ayurvedic medicine stores.
 * 
 * KEY RULES:
 * 1. Always use organization_id for multi-tenancy
 * 2. All inventory changes must be transactional
 * 3. Use FIFO for batch allocation
 * 4. Tax calculation varies by country (GST/VAT)
 * 5. Support offline-first operations
 * 
 * PRODUCT TYPES:
 * - choorna (powder) - weight-based (GMS)
 * - tailam (oil) - volume-based (ML)
 * - ghritam (ghee) - volume-based (ML)
 * - capsules - piece-based (CAPSULES)
 * 
 * COUNTRIES:
 * - India: GST (5%, 12%, 18%), MRP inclusive
 * - Nepal: VAT (13%), price exclusive
 */
```

### Custom Instructions for Copilot

```
When working on this project:
- Use Laravel 11 best practices
- Type-hint everything
- Use Enums for constants
- Create Action classes for complex logic
- Write tests for critical paths
- Add comments for business logic
- Use Eloquent relationships
- Never hardcode organization/store IDs
- Always check feature flags
- Consider offline scenarios
```

### Git Workflow

```bash
main            # Production-ready code
├── develop     # Integration branch
    ├── feature/multi-tenant
    ├── feature/inventory-fifo
    ├── feature/sync-engine
    └── feature/receipt-printing
```

---

## 📚 Additional Documentation Files

This master document should be complemented with:

1. **`DATABASE_SCHEMA.md`** - Detailed schema with relationships
2. **`API_DOCUMENTATION.md`** - Complete API reference
3. **`DEPLOYMENT_GUIDE.md`** - Step-by-step deployment
4. **`DEVELOPER_GUIDE.md`** - Coding standards, conventions
5. **`USER_MANUAL.md`** - End-user documentation
6. **`WHITE_LABEL_GUIDE.md`** - How to customize for new tenants

---

## ✅ MVP Feature Checklist

### Phase 1 (Month 1-2): Core POS

- [ ] User authentication (email + PIN)
- [ ] Product management (CRUD)
- [ ] Product variants (pack sizes)
- [ ] Product search (name, SKU, Sanskrit)
- [ ] Shopping cart
- [ ] Basic billing
- [ ] Cash payment only
- [ ] Receipt generation
- [ ] Thermal printer integration
- [ ] Real-time inventory deduction
- [ ] Basic FIFO batch tracking
- [ ] Local SQLite storage
- [ ] Daily Z-report

### Phase 2 (Month 3): Inventory & Reporting

- [ ] Stock level tracking
- [ ] Purchase entry
- [ ] Stock adjustment
- [ ] Batch management UI
- [ ] Expiry date tracking
- [ ] Low stock alerts
- [ ] Expiry alerts (6 months)
- [ ] Sales reports (daily/monthly)
- [ ] Inventory valuation report
- [ ] Tax reports (GST/VAT)
- [ ] Export to CSV/PDF

### Phase 3 (Month 4): Multi-tenant & Sync

- [ ] Organization model
- [ ] Store model
- [ ] Multi-tenant isolation
- [ ] Cloud database setup
- [ ] Sync queue system
- [ ] Push sales to cloud
- [ ] Pull products from cloud
- [ ] Conflict resolution
- [ ] Auto backup

### Phase 4 (Month 5-6): White-label & Advanced

- [ ] Branding configuration UI
- [ ] Receipt template customization
- [ ] Feature flags system
- [ ] Role management UI
- [ ] Permission system
- [ ] Nepal localization (VAT, Nepali labels)
- [ ] Digital payments (Razorpay, eSewa, Khalti)
- [ ] Customer management (optional)
- [ ] Multi-terminal support

---

## 🎯 Success Metrics

### Technical
- Billing speed: <5 seconds per transaction
- Offline reliability: 99.9% uptime
- Sync success rate: >95%
- API response time: <200ms (local)

### Business
- Daily transactions: 50-100 initially
- Stock accuracy: >98%
- Receipt print success: >99%
- User training time: <2 hours

---

## 🤝 Support & Maintenance

### Regular Tasks
- Daily automated backups
- Weekly sync audit
- Monthly security updates
- Quarterly feature reviews

### Monitoring
- Error logs (storage/logs)
- Sync failures
- Low disk space alerts
- Database size growth

---

## 📞 Contact & Credits

**Built for**: Rishipath International Foundation  
**Purpose**: Sustainable, ethical, white-label POS system  
**Architecture Date**: December 31, 2025  
**Documentation**: Optimized for GitHub Copilot

---

## 🔜 Future Enhancements (Post-MVP)

- [ ] Mobile app for managers
- [ ] Barcode label printing
- [ ] Weight scale integration
- [ ] Loyalty program
- [ ] Donation management
- [ ] Prescription tracking
- [ ] SMS notifications
- [ ] Email receipts
- [ ] Multi-currency (if needed)
- [ ] Franchise management (if applicable)

---

**This architecture is designed to be:**
✅ Maintainable for years  
✅ Scalable from 1 to 100+ stores  
✅ White-label ready from day one  
✅ Ethical & sustainable  
✅ Community-owned  

**Ready to build! 🚀**

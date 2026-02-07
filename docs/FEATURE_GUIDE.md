# 🚀 Rishipath POS - Complete Feature Guide

**Status:** Production Ready  
**Last Updated:** December 31, 2025

---

## 📱 How to Use the System

### 🔐 **1. Login & Access**
```
URL: http://rishipath-pos.test/admin
Email: admin@rishipath.org
Password: password
```

---

## 💰 **POS Billing (NEW)**

### Location: Navigation → **POS Billing** (Top of menu)

### Features:
1. **Product Search**
   - Search by: Product name, SKU, Barcode
   - Multi-language: English, Sanskrit, Hindi names
   - Live search results
   - Click product to add to cart

2. **Shopping Cart**
   - Adjust quantities with +/- buttons
   - Remove items
   - Clear entire cart
   - Real-time calculations

3. **Customer Details** (Optional)
   - Name
   - Phone number
   - Links to customer database if exists

4. **Payment Processing**
   - Payment methods: Cash, UPI, Card, eSewa, Khalti
   - Cash: Auto-calculates change
   - Add notes to transaction

5. **Complete Sale**
   - Generates unique receipt number
   - Updates inventory automatically
   - Records sale in database
   - Can print receipt (via ReceiptService)

### Workflow:
```
Search Product → Add to Cart → Enter Customer (optional) 
→ Select Payment Method → Enter Amount (cash only) 
→ Complete Sale → Receipt Generated
```

---

## 📦 **Inventory Management (NEW)**

### **A. Batch Receiving**
**Location:** Navigation → Inventory → **Batches**

**Use Case:** Receiving new stock from suppliers

**Steps:**
1. Click "Create Batch"
2. Select Product Variant (searchable dropdown)
3. Select Store
4. Enter Batch Number (unique identifier)
5. Select Supplier (optional)
6. Enter Dates:
   - Manufacturing date
   - **Expiry date** (required)
   - Purchase date
7. Enter Quantities:
   - Quantity received
   - Purchase price per unit
8. Add notes
9. Save

**Features:**
- Color-coded stock levels (red/yellow/green)
- Expiry date warnings (red = expired, yellow = <30 days)
- Filters: Store, Low Stock, Expired, Expiring Soon
- View/Edit existing batches

---

### **B. Stock Adjustment**
**Location:** Navigation → Inventory → **Stock Adjustment**

**Use Cases:**
- Physical inventory count corrections
- Damaged goods removal
- Theft/loss recording
- Customer returns
- System error corrections

**Steps:**
1. Select Store
2. Select Product Variant
3. Current stock shows automatically
4. Choose Adjustment Type:
   - **Increase**: Add to current stock
   - **Decrease**: Subtract from current stock
   - **Set**: Override with exact number
5. Enter quantity
6. Select reason (required):
   - Damage, Theft, Return, Recount, Error, Other
7. Add notes (optional)
8. Preview old → new stock
9. Apply Adjustment

**Tracking:**
- All adjustments logged in InventoryMovement
- Shows user, timestamp, reason
- Recent adjustments displayed on right side

---

### **C. Stock Levels**
**Location:** Automatically managed

**How it Works:**
- Created when first batch received
- Updated automatically on:
  - Sales (decreases)
  - Batch receiving (increases)
  - Manual adjustments
- Tracks:
  - Current quantity
  - Reserved quantity (for future use)
  - Reorder level
  - Last movement timestamp

---

## 📊 **Sales Reporting (NEW)**

**Location:** Navigation → Reports → **Sales Report**

### Filters:
- Date range (start/end dates)
- Store (specific or all stores)
- Payment method (specific or all methods)

### Metrics Displayed:

**Summary Cards:**
- Total Sales (₹)
- Total Transactions
- Average Sale Value
- Items Sold
- Total Tax Collected
- Total Discounts Given

**Payment Method Breakdown:**
- Transactions by method
- Amount by method
- Tabular view

**Top 10 Products:**
- Product name
- Quantity sold
- Total revenue
- Sorted by revenue

**Daily Sales Trend:**
- Date-wise breakdown
- Transactions per day
- Sales per day
- Sortable table

### Use Cases:
- End of day reconciliation
- Monthly sales analysis
- Product performance tracking
- Tax reporting (GST filing)
- Cashier performance review

---

## 📈 **Dashboard Widgets**

### **1. POS Stats Widget**
- Today's sales
- This month's sales
- Active products count
- Low stock items alert

### **2. Inventory Overview Widget**
- Total inventory value
- Low stock items
- Out of stock items
- Expired batches
- Expiring soon (30 days)

### **3. Low Stock Alerts Widget** (NEW)
- Table of products below reorder level
- Sortable and searchable
- Shows current vs reorder level
- Last movement date
- Store-wise breakdown

---

## 🛍️ **Product Catalog Management**

### **Products**
**Location:** Navigation → Product Catalog → **Products**

**Features:**
- Multi-language names (English, Sanskrit, Hindi)
- Product categorization
- Unit types (Weight/Volume/Piece)
- Tax categories (Essential 5%, Standard 12%, Luxury 18%)
- Batch tracking toggle
- Expiry tracking toggle
- Shelf life in months
- Prescription requirement flag
- Ingredients list
- Usage instructions

### **Product Variants**
- Different sizes/packaging of same product
- SKU per variant
- Pack size + unit
- Pricing:
  - MRP (India)
  - Selling price (Nepal)
  - Cost price
- Barcode
- HSN code for tax
- Weight

### **Categories**
- Hierarchical structure
- Multi-language names
- Product type assignment
- Configuration per category
- Sort order

---

## 👥 **Customer Management**

**Location:** Navigation → Sales → **Customers**

**Features:**
- Unique customer codes
- Contact information
- Purchase history tracking
- Total spent calculation
- Loyalty points (future use)
- Notes field

**Integration:**
- Link customers to sales during checkout
- Track purchase patterns
- Generate customer-specific reports

---

## 🏪 **Store & Settings**

### **Stores**
**Location:** Navigation → Settings → **Stores**

- Multiple store support
- Complete address information
- Tax registration numbers
- License numbers
- Operating hours configuration
- GPS coordinates

### **Suppliers**
**Location:** Navigation → Inventory → **Suppliers**

- Supplier database
- Contact persons
- Tax information
- Link to product batches

---

## 🧾 **Receipt System**

### ReceiptService Features:
1. **Header Section:**
   - Organization name
   - Store address and contact
   - GST number

2. **Sale Details:**
   - Receipt number
   - Date and time
   - Cashier name
   - Customer info (if provided)

3. **Items List:**
   - Product names
   - Quantities
   - Prices
   - Tax rates

4. **Totals:**
   - Subtotal
   - Discounts (if any)
   - Tax (GST)
   - Grand total
   - Payment method
   - Amount paid & change (cash)

5. **Footer:**
   - Thank you message
   - Branding

### Usage:
```php
$receiptService = new ReceiptService();
$receiptText = $receiptService->generateReceipt($sale);
$receiptService->printReceipt($sale); // Sends to printer
```

---

## 🎯 **User Roles & Permissions**

### **Super Admin** (Full Access)
- All modules
- User management
- Settings
- Reports
- Sales
- Inventory
- Products

### **Cashier** (Limited)
- POS billing
- View products
- View inventory
- Daily sales report

### **Customizable**
- Create custom roles
- Granular permissions
- Per-module access control

---

## 📊 **Data Flow**

### **Sale Process:**
```
POS Billing → Create Sale Record → Create Sale Items 
→ Update Stock Levels → Create Inventory Movement 
→ Update Customer Stats (if linked) → Generate Receipt
```

### **Inventory Process:**
```
Receive Batch → Create ProductBatch Record 
→ Update/Create StockLevel → Record InventoryMovement
```

### **Adjustment Process:**
```
Stock Adjustment Page → Validate Input 
→ Update StockLevel → Create InventoryMovement 
→ Audit Trail Complete
```

---

## 🔍 **Search & Filters**

### **Available Filters:**

**Products:**
- Category
- Product type
- Active status

**Sales:**
- Date range
- Store
- Payment method
- Status

**Batches:**
- Store
- Low stock
- Expired
- Expiring soon (30 days)

**Search Works On:**
- Product names (all languages)
- SKUs
- Barcodes
- Customer names
- Receipt numbers
- Batch numbers

---

## 🚨 **Alerts & Notifications**

### **Automatic Alerts:**
1. **Low Stock:** Products below reorder level
2. **Out of Stock:** Zero quantity items
3. **Expired Batches:** Past expiry date
4. **Expiring Soon:** Within 30 days

### **Alert Locations:**
- Dashboard widgets
- Product batch list (color coding)
- Inventory overview widget
- Low stock alerts widget (table)

---

## 💾 **Data Backup & Audit**

### **Audit Trails:**
- All inventory movements logged
- User actions recorded
- Timestamps on all records
- Before/after values for adjustments

### **Backup Recommendations:**
```bash
# Database backup
php artisan backup:run

# Manual backup
sqlite3 database/database.sqlite ".backup database_backup.sqlite"
```

---

## 🎨 **UI Features**

- **Dark Mode:** Supported throughout
- **Responsive:** Works on tablets and desktops
- **Live Updates:** Livewire for real-time interactions
- **Color Coding:** Red/Yellow/Green for status
- **Icons:** Heroicons for clarity
- **Tables:** Sortable, searchable, paginated
- **Forms:** Validation and error messages

---

## 📱 **Mobile Considerations**

- Responsive layouts
- Touch-friendly buttons
- Simplified forms on small screens
- Grid adapts to screen size

---

## 🔒 **Security Features**

- User authentication required
- Role-based access control
- Password hashing
- CSRF protection
- Session management
- Audit logging

---

## 🚀 **Next Steps (Future Enhancements)**

### **Phase 3 Recommendations:**
1. **Barcode Scanner Integration**
   - USB/Bluetooth scanner support
   - Real-time scanning in POS

2. **Thermal Printer Integration**
   - Direct printer communication
   - Custom receipt templates

3. **Customer Loyalty Program**
   - Points earning rules
   - Redemption system
   - Tier levels

4. **Cloud Sync**
   - PostgreSQL central database
   - Offline queue management
   - Conflict resolution

5. **Advanced Reporting**
   - Charts and graphs
   - Export to Excel/PDF
   - Email reports
   - Scheduled reports

6. **Mobile App**
   - Native iOS/Android
   - Offline-first architecture
   - Push notifications

---

**System is now production-ready for single-store operations!** 🎉

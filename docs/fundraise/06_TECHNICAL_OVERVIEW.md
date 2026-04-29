# RishiPath POS - Technical Architecture Overview

> **For CTOs, technical investors, and due diligence teams**

---

## Technology Stack

| Layer | Technology | Why This Choice |
|-------|-----------|----------------|
| **Backend Framework** | Laravel 11 (PHP 8.4) | Most popular PHP framework, massive ecosystem, excellent ORM, strong security |
| **Admin Panel** | Filament 3 | Modern TALL-stack admin builder, rapid development, beautiful UI out of the box |
| **Frontend** | Vue 3 + Vite + Tailwind CSS | Reactive UI, fast builds, utility-first CSS for consistent design |
| **Database (Local)** | SQLite | Zero-configuration, file-based, perfect for offline-first POS machines |
| **Database (Cloud)** | MySQL / PostgreSQL | Production-grade RDBMS for central dashboard and multi-store sync |
| **ORM** | Eloquent | Active Record pattern, relationship mapping, query builder, soft deletes |
| **PDF Generation** | Barryvdh/DomPDF | Invoice and report PDF generation |
| **Excel Export** | Maatwebsite/Laravel-Excel | Styled Excel exports for reports |
| **WhatsApp** | Twilio API | Programmatic WhatsApp messaging for receipts |
| **Barcode** | Milon/Barcode | Code128, EAN13, Code39, QR code generation |

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                     CLOUD INFRASTRUCTURE                        │
│                                                                 │
│  ┌─────────────┐    ┌──────────────┐    ┌──────────────────┐   │
│  │   Laravel    │    │   MySQL /    │    │   File Storage   │   │
│  │   Backend    │◄──►│  PostgreSQL  │    │   (Invoices,     │   │
│  │   (API)      │    │  (Central)   │    │    Reports,      │   │
│  └──────┬───────┘    └──────────────┘    │    Images)       │   │
│         │                                └──────────────────┘   │
│         │                                                       │
│  ┌──────▼───────┐    ┌──────────────┐                          │
│  │  Filament 3  │    │   Scheduled  │                          │
│  │  Admin Panel │    │   Tasks      │                          │
│  │  (Dashboard) │    │  (Reports,   │                          │
│  └──────────────┘    │   Alerts)    │                          │
│                      └──────────────┘                          │
└────────────────────────────┬────────────────────────────────────┘
                             │
                    Sync (when online)
                             │
┌────────────────────────────▼────────────────────────────────────┐
│                    POS MACHINE (Per Store)                       │
│                                                                 │
│  ┌─────────────┐    ┌──────────────┐    ┌──────────────────┐   │
│  │   Laravel    │    │   SQLite     │    │   Barcode        │   │
│  │   + Filament │◄──►│  (Local DB)  │    │   Scanner        │   │
│  │   (POS UI)   │    └──────────────┘    └──────────────────┘   │
│  └──────┬───────┘                                               │
│         │            ┌──────────────┐    ┌──────────────────┐   │
│         └───────────►│   Receipt    │    │   Thermal        │   │
│                      │   Generator  │───►│   Printer        │   │
│                      └──────────────┘    └──────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Data Architecture

### Entity Relationship Overview

```
Organization (Tenant Root)
├── Store[] (Physical Locations)
│   ├── Terminal[] (POS Machines)
│   ├── StockLevel[] (Per-variant inventory)
│   ├── ProductBatch[] (Batch tracking)
│   └── InventoryMovement[] (Audit trail)
│
├── User[] (Staff)
│   └── Role (Permissions[])
│
├── Category[] (Hierarchical)
│   └── Product[]
│       └── ProductVariant[]
│           ├── ProductStorePricing[] (Store overrides)
│           ├── StockLevel[] (Per-store quantities)
│           └── ProductBatch[] (Expiry tracking)
│
├── Supplier[]
│   ├── Purchase[]
│   │   ├── PurchaseItem[]
│   │   ├── PurchaseReturn[]
│   │   └── ProductBatch[] (Created on receive)
│   └── SupplierLedgerEntry[]
│
├── Customer[]
│   ├── Sale[]
│   │   ├── SaleItem[]
│   │   ├── PaymentSplit[]
│   │   └── SalePayment[]
│   ├── CustomerLedgerEntry[]
│   ├── LoyaltyPoint[]
│   └── LoyaltyTier
│
├── LoyaltyTier[] (Bronze/Silver/Gold/Platinum)
├── Reward[] (Redeemable items)
├── AlertRule[] (Business logic alerts)
├── ReportSchedule[] (Automated reports)
├── Notification[] (Alert deliveries)
│
├── RetailStore[] (B2B Partners)
│   ├── RetailStoreVisit[]
│   └── BulkOrderInquiry[]
│
├── Invoice[] (Polymorphic)
├── Feedback[] (Polymorphic)
└── PosSession[] (Parked carts)
```

### Key Design Decisions

| Decision | Rationale |
|----------|-----------|
| **Multi-tenant via organization_id** | Simple, performant, no schema-per-tenant complexity |
| **SQLite for local POS** | Zero-config, single-file DB, works offline, easy backup |
| **Polymorphic ledger entries** | Same table tracks both customer and supplier balances |
| **Soft deletes on financial models** | Never lose financial data, audit compliance |
| **FIFO batch tracking** | Medicine regulation compliance, oldest stock sold first |
| **Semantic SKUs** | Human-readable product identification (AYU-OIL-AML vs random numbers) |
| **No-timestamp on hot tables** | StockLevel and InventoryMovement optimized for write performance |
| **JSON config columns** | Flexible per-org/store configuration without schema changes |

---

## Database Schema Summary

### 35 Models, 32+ Migrations

| Module | Tables | Purpose |
|--------|--------|---------|
| **Core** | organizations, stores, terminals, users, roles | Multi-tenant foundation |
| **Catalog** | categories, products, product_variants, product_store_pricing | Product management |
| **Inventory** | stock_levels, product_batches, inventory_movements | Stock tracking |
| **Sales** | sales, sale_items, sale_payments, payment_splits, pos_sessions | POS transactions |
| **Purchasing** | purchases, purchase_items, purchase_returns | Supplier orders |
| **Customer** | customers, customer_ledger_entries | Customer management |
| **Loyalty** | loyalty_tiers, loyalty_points, rewards | Loyalty program |
| **Supplier** | suppliers, supplier_ledger_entries | Supplier management |
| **B2B** | retail_stores, retail_store_visits, bulk_order_inquiries | Wholesale |
| **Automation** | alert_rules, report_schedules, scheduled_report_runs, notifications | Business automation |
| **Finance** | invoices, feedback | Invoicing & tracking |

---

## Service Layer Architecture

The business logic is separated into 13 focused service classes:

```
app/Services/
├── InventoryService.php       # Stock operations (FIFO, adjustments, sync)
├── LoyaltyService.php         # Points, tiers, rewards, enrollment
├── PricingService.php         # Multi-currency, tax, store overrides
├── InvoiceService.php         # PDF generation, invoice types
├── WhatsAppService.php        # Twilio integration, receipt delivery
├── BarcodeService.php         # Barcode generation (Code128/EAN13/QR)
├── CustomerLedgerService.php  # Credit tracking, FIFO payment allocation
├── AlertService.php           # Business rule evaluation, notifications
├── ExportService.php          # Excel/CSV export with formatting
├── OrganizationContext.php    # Multi-tenant session management
├── StoreContext.php           # Store context session management
├── ReportScheduleService.php  # Scheduled report automation
└── ReceiptService.php         # Receipt formatting (print/WhatsApp)
```

### Key Service Patterns

**InventoryService — FIFO Batch Deduction**
```
Sale happens → InventoryService::decreaseStockFIFO()
  → Find oldest batch with remaining quantity
  → Deduct from that batch first
  → If batch exhausted, move to next oldest
  → Log InventoryMovement for each batch touched
  → Update StockLevel aggregate
```

**PricingService — Country-Aware Pricing**
```
Get price → PricingService::getSellingPrice(variant, store)
  → Check organization country (India/Nepal)
  → India? Use mrp_india field
  → Nepal? Use selling_price_nepal field
  → Check store-specific override exists?
  → Yes? Use custom_price instead
  → Apply tax rate (GST 12% / VAT 13%)
  → Return formatted price with currency symbol
```

**LoyaltyService — Point Calculation**
```
Sale completed → LoyaltyService::awardPointsForSale()
  → Get customer's current tier multiplier (1.0-2.0)
  → Base points = sale total (₹1 = 1 point)
  → Final points = base × multiplier
  → Create LoyaltyPoint record
  → Update customer's loyalty_points balance
  → Check: should customer advance to next tier?
  → If yes → updateCustomerTier()
```

---

## Security Architecture

### Authentication & Authorization

| Layer | Implementation |
|-------|---------------|
| **Authentication** | Laravel built-in + Filament auth |
| **Authorization** | Custom role-based with 70+ granular permissions |
| **PIN Login** | Quick POS access via numeric PIN |
| **Session Management** | Organization and store context in session |
| **Multi-Org Isolation** | All queries scoped by organization_id |

### Permission Categories (70+)

```
Dashboard:       view_dashboard
POS:             access_pos_billing, void_sale, refund_sale
Products:        view/create/edit/delete_products, manage_variants, manage_batches
Inventory:       view_inventory, adjust_stock, transfer_stock, manage_stock_movements
Customers:       view/create/edit/delete_customers, view_customer_ledger
Suppliers:       view/create/edit/delete_suppliers
Sales:           view/create/edit/delete_sales, view_sales_reports
Purchases:       view/create/edit/delete_purchases, receive_purchase, record_purchase_payment
Loyalty:         view_loyalty_program, manage_rewards, manage_loyalty_tiers, award/redeem
Reporting:       view_reports
Admin:           manage_users, manage_roles, system_settings, audit_logs
```

### Data Security

- Organization-scoped queries prevent cross-tenant data access
- Soft deletes on financial models ensure audit compliance
- Every inventory movement logged with user, timestamp, and reason
- Payment references stored for reconciliation
- PIN codes for cashier quick-login (separate from password)

---

## Scalability Considerations

### Current Architecture Supports

| Dimension | Capacity | Notes |
|-----------|----------|-------|
| **Organizations** | Unlimited | Multi-tenant by design |
| **Stores per Org** | Unlimited | Each with independent stock |
| **Products** | 10,000+ | Indexed by SKU, barcode, name |
| **Customers** | 100,000+ | Indexed by code, phone |
| **Daily Transactions** | 1,000+ per store | Optimized write path |
| **Concurrent Users** | 50+ per instance | Filament handles efficiently |

### Scaling Path

**Phase 1 (Current)**: Single-server deployment per region
- SQLite for POS machines
- MySQL/PostgreSQL for cloud dashboard
- Suitable for 100-500 stores

**Phase 2 (With Funding)**: Microservices evolution
- Separate sync service for POS ↔ cloud
- Queue-based report generation
- Redis caching for dashboard
- Suitable for 500-5,000 stores

**Phase 3 (Scale)**: Distributed architecture
- Regional database clusters
- CDN for assets
- API gateway for third-party integrations
- Suitable for 5,000-50,000 stores

---

## Integration Points

### Current Integrations

| Service | Status | Purpose |
|---------|--------|---------|
| **Twilio WhatsApp** | ✅ Live | Receipt delivery |
| **eSewa** | ✅ Ready | Nepal digital payments |
| **Khalti** | ✅ Ready | Nepal digital payments |

### Planned Integrations

| Service | Timeline | Purpose |
|---------|----------|---------|
| **Razorpay** | Q1 | India payment gateway |
| **Tally** | Q2 | Accounting sync |
| **Google Business** | Q2 | Store listings |
| **SMS Gateway** | Q2 | Transactional SMS |
| **ONDC** | Q3 | Government e-commerce network |

---

## Development Metrics

| Metric | Count |
|--------|-------|
| Database Models | 35 |
| Database Migrations | 32+ |
| Filament Resources | 24 |
| Custom Pages | 16 |
| Dashboard Widgets | 8 |
| Service Classes | 13 |
| Livewire Components | 2 |
| Observers | 1 |
| Export Classes | 2 |
| Notification Classes | 1 |
| Permission Definitions | 70+ |
| Predefined Roles | 4 |
| Supported Barcode Formats | 4 |
| Supported Payment Methods | 6 |
| Supported Languages | 4 |
| Supported Countries | 2 |

---

## Code Quality & Architecture Principles

1. **Service Layer Pattern** — Business logic isolated from controllers/resources
2. **Single Responsibility** — Each service handles one domain (Inventory, Pricing, Loyalty)
3. **Eloquent Relationships** — Proper foreign keys, eager loading, scoped queries
4. **Soft Deletes** — Financial data never permanently deleted
5. **Observer Pattern** — ProductBatchObserver for automatic batch lifecycle management
6. **Context Pattern** — OrganizationContext/StoreContext for multi-tenant session
7. **Auto-Generation** — Receipt numbers, customer codes, supplier codes, SKUs
8. **Polymorphic Relations** — CustomerLedgerEntry serves both customers and suppliers
9. **JSON Columns** — Flexible configuration without schema migrations
10. **FIFO Algorithm** — Proper batch-based inventory deduction

---

*This document is intended for technical due diligence purposes.*

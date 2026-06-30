# Rishipath / Shuddhidham POS

Laravel 12 + Filament 3 admin/POS for a Nepali spice & dry-fruit wholesale/retail business.

## Stack
- **PHP 8.4**, Laravel 12, Filament 3 (admin panel at `/admin`, panel id `admin`).
- **DB: SQLite** at `database/database.sqlite` (backups in `db_backups/`).
- Key packages: spatie/laravel-permission, barryvdh/laravel-dompdf (invoices), maatwebsite/excel, picqer barcode generator.
- Local dev served by **Laravel Herd** at `https://rishipath-pos.test` (panel: `/admin`, login `/admin/login`).

## Architecture
- **Multi-tenant by `organization_id`** — the live org is `rishipath` (slug). Almost every model is scoped to it; always filter by org.
- **Custom roles, not pure Spatie.** `App\Models\Role` has a `permissions` array column + `is_system_role`. `User->role_id` points to it; `User->permissions` can override per-user. Sales-agent permissions are an allow-list (see `ShuddhidhamUsersSeeder`).
- **POS** is a Filament page: `app/Filament/Pages/EnhancedPOS.php`. Reports/tools are also Pages (SalesReport, ProfitReport, CustomerLedgerReport, RecordPayment, etc.). 44 Filament Resources under `app/Filament/Resources`.
- **Dashboard widgets** (charts/stats): `app/Filament/Widgets/` — SalesTrendChart, ProfitTrendChart, CategoryDistributionChart, POSStatsWidget, InventoryOverviewWidget, etc.

## Domain notes
- **Stock enforcement is intentionally disabled for agents** — they take preorders/credit even at 0 stock. Don't re-add blocking stock checks in the POS path.
- **Credit/preorder sales**: `Sale.payment_status = unpaid`; they create a pending `CustomerLedgerEntry` (debit) and must NOT count toward Sales Report revenue. Revenue = paid only.
- `RecordPayment` closes credit: adds a ledger credit entry, flips the sale to `paid`.
- Sales agents (`SalesAgent` model, linked to user by email) earn commission tracked via `SalesAgentLedger`.
- Invoices/receipts render via dompdf; credit invoices show an amber "ORDER CONFIRMED — Payment due on delivery" banner instead of a paid receipt.

## Key models
User, Role, Organization, Store · Product, ProductVariant, ProductStorePricing, ProductBatch, StockLevel, InventoryMovement · Supplier, Purchase, SupplierLedgerEntry · Customer, CustomerLedgerEntry, Sale, SaleItem, SalePayment · SalesAgent, SalesAgentLedger · LoyaltyTier/Point, Reward.

## Test accounts (seeded by `ShuddhidhamUsersSeeder`, password `shuddhidham`)
- `admin@shuddhidham.com` — Super Admin (full access)
- `bina@shuddhidham.com` — Sales Agent (Thamel/Asan, AGT-BINA)
- `bishal@shuddhidham.com` — Sales Agent (Patan/Lalitpur, AGT-BISHAL)

## Commands
- Run tests: `php artisan test`
- Seed users only: `php artisan db:seed --class=ShuddhidhamUsersSeeder`
- Console scripting: `php artisan tinker --execute='...'`
- **Never hard-delete users** referenced by sales — deactivate (`active=false`) instead (FK constraints).

## Conventions
- Match surrounding Filament/Laravel idioms. Scope queries by `organization_id`.
- Before destructive DB work, back up `database/database.sqlite` to `db_backups/` first.

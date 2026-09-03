# Rishipath / Shuddhidham POS

Laravel 12 + Filament 3 admin/POS for a Nepali spice & dry-fruit wholesale/retail business.

## Stack
- **PHP 8.4**, Laravel 12, Filament 3 (admin panel at `/admin`, panel id `admin`).
- **DB differs by environment**: local dev is **SQLite** at `database/database.sqlite` (back it up to `db_backups/` before destructive work); **production is MySQL** (`rishipa2_pos`). So a production repair cannot be made safe by copying a file — snapshot the affected rows to JSON in `db_backups/` first (see `products:restore-synced-images` for the pattern).
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
- **Deploy auto-seeds**: `scripts/deploy.sh` runs `migrate --force` + `db:seed --force` (DatabaseSeeder). `ProductCatalogSeeder` deactivates ALL products not in its rate list on every run — products added outside it must be re-activated by a later seeder (see `BlendProductsSeeder`).
- **Permission gating**: resources use the `HasPermissionCheck` trait (or explicit `canViewAny`), pages `canAccess()`, widgets `canView()`. The sales-agent allow-list lives in `RolePermissionSeeder::getSalesAgentPermissions()` (shared with ShuddhidhamUsersSeeder). Never leave a new Resource/Page/Widget ungated — ungated means visible to sales agents.
- **Product images** come from shuddhidham.com: `php artisan products:sync-web-images` pulls the photos published on the website's Cloudinary account via its public catalogue API. The SKU-to-slug pairing is hand-maintained in `database/data/web_product_images.json` (never name-matched at runtime), and only `res.cloudinary.com` URLs are synced — the site also serves placeholders and one stock photo. Files land on the public disk, so this must be re-run **on the server** after deploy; `--dry-run` and `--prune` are available.
- **Price calculator** (`/price-calculator`): blend tab searches POS products per row, loads saved recipes from `product_compositions`, and suggests pairings from the Ingredient KB (`/api/price-calculator/{products,recipes,suggestions}`).

## Key models
User, Role, Organization, Store · Product, ProductVariant, ProductStorePricing, ProductBatch, StockLevel, InventoryMovement · Supplier, Purchase, SupplierLedgerEntry · Customer, CustomerLedgerEntry, Sale, SaleItem, SalePayment · SalesAgent, SalesAgentLedger · LoyaltyTier/Point, Reward · Ingredient (knowledge base, `/admin/ingredients`), ProductComposition (blend recipes, e.g. Garam Masala/Rishipeya).

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

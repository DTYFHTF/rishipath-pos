<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which features actually get used, judged by the data they leave behind.
 *
 * A report page over an empty table, or a scheduled job whose rule table has
 * no rows, is a feature that was built and never adopted. Static analysis
 * cannot tell the difference — Filament auto-discovers pages, and a scheduled
 * command is "referenced" by the scheduler whether or not it ever does work.
 * Only the data knows.
 *
 * Run on the server, where the real history lives:
 *   php artisan codebase:usage
 */
class FeatureUsageAudit extends Command
{
    protected $signature = 'codebase:usage {--stale=90 : Days after which activity counts as stale}';

    protected $description = 'Report which features have ever been used, based on their data';

    /**
     * feature => [table, what it powers, timestamp column]
     */
    private const FEATURES = [
        'Alert rules' => ['alert_rules', 'alerts:check runs every 15 min', 'created_at'],
        'Scheduled reports' => ['report_schedules', 'reports:process-scheduled runs hourly', 'created_at'],
        'Scheduled report runs' => ['scheduled_report_runs', 'history of the above', 'created_at'],
        'Notifications' => ['notifications', 'notifications:send-pending runs hourly', 'created_at'],
        'Loyalty — rewards' => ['rewards', 'LoyaltyProgram page, POS reward modal', 'created_at'],
        'Loyalty — points' => ['loyalty_points', 'loyalty:birthday-bonuses runs daily', 'created_at'],
        'Bulk order inquiries' => ['bulk_order_inquiries', 'BulkOrderInquiryResource', 'created_at'],
        'Customer feedback' => ['feedbacks', 'FeedbackResource', 'created_at'],
        'Invoices (separate)' => ['invoices', 'InvoiceResource — distinct from sale receipts', 'created_at'],
        'Purchase returns' => ['purchase_returns', 'PurchaseReturnResource', 'created_at'],
        'Per-store pricing' => ['product_store_pricing', 'ProductStorePricing overrides', 'created_at'],
        'Split payments' => ['payment_splits', 'POS split payment UI', 'created_at'],
        'Retail store visits' => ['retail_store_visits', 'RetailVisitPlanner scoring', 'created_at'],
        'Agent commission ledger' => ['sales_agent_ledgers', 'AgentEarningsSettlement page', 'created_at'],
        'Supplier ledger' => ['supplier_ledger_entries', 'SupplierLedgerReport page', 'created_at'],
        'Customer ledger' => ['customer_ledger_entries', 'CustomerLedgerReport, RecordPayment', 'created_at'],
        'Product batches' => ['product_batches', 'FIFO batch tracking', 'created_at'],
        'Inventory movements' => ['inventory_movements', 'InventoryAuditLog page', 'created_at'],
        'Purchases' => ['purchases', 'PurchaseResource', 'created_at'],
        'Multi-store' => ['stores', 'store switcher, transfers', 'created_at'],
        'Terminals' => ['terminals', 'POS terminal binding', 'created_at'],
        'Parked POS carts' => ['pos_sessions', 'POS multi-cart', 'created_at'],
        'Sales' => ['sales', 'the core of the app', 'created_at'],
        'Customers' => ['customers', 'CustomerResource', 'created_at'],
        'Retail stores' => ['retail_stores', 'RetailStoreResource, Visit Planner', 'created_at'],
    ];

    public function handle(): int
    {
        $staleDays = (int) $this->option('stale');
        $unused = [];
        $stale = [];
        $rows = [];

        foreach (self::FEATURES as $label => [$table, $powers, $tsColumn]) {
            if (! Schema::hasTable($table)) {
                $rows[] = [$label, '—', 'no table', $powers];
                $unused[] = $label;

                continue;
            }

            $count = DB::table($table)->count();

            $last = null;
            if ($count > 0 && Schema::hasColumn($table, $tsColumn)) {
                $last = DB::table($table)->max($tsColumn);
            }

            $age = $last ? (int) now()->diffInDays($last, false) * -1 : null;

            $status = match (true) {
                $count === 0 => 'NEVER USED',
                $age !== null && $age > $staleDays => "stale ({$age}d)",
                default => 'active',
            };

            if ($count === 0) {
                $unused[] = $label;
            } elseif ($status !== 'active') {
                $stale[] = $label;
            }

            $rows[] = [
                $label,
                number_format($count),
                $status,
                $powers,
            ];
        }

        $this->newLine();
        $this->table(['Feature', 'Rows', 'Status', 'What it powers'], $rows);

        $this->newLine();

        if ($unused !== []) {
            $this->warn(count($unused).' feature(s) have never been used — candidates for removal:');
            foreach ($unused as $u) {
                $this->line('  · '.$u);
            }
        } else {
            $this->info('Every tracked feature has at least some data.');
        }

        if ($stale !== []) {
            $this->newLine();
            $this->comment(count($stale)." feature(s) unused in the last {$staleDays} days:");
            foreach ($stale as $s) {
                $this->line('  · '.$s);
            }
        }

        $this->newLine();
        $this->line('Nothing was changed. Use this list to decide what to delete —');
        $this->line('a feature with no rows in production is one nobody adopted.');

        return self::SUCCESS;
    }
}

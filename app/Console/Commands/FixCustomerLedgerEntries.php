<?php

namespace App\Console\Commands;

use App\Models\CustomerLedgerEntry;
use App\Models\Sale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixCustomerLedgerEntries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ledger:fix-customer-entries {--dry-run : Show what would be fixed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove customer ledger entries for paid sales (cash/card/UPI) - ledger should only track credit sales';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        } else {
            $this->warn('⚠️  This will delete ledger entries for paid sales (cash/card/UPI).');
            $this->warn('    Ledger should only track credit sales, not immediate payments.');
            if (!$this->confirm('Do you want to continue?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        DB::beginTransaction();

        try {
            // Find all sale entries for paid sales (non-credit)
            // These should NOT exist - ledger only tracks credit relationships
            $this->info('Fetching ledger entries for paid sales...');

            $deleteIds = DB::table('customer_ledger_entries as cle')
                ->join('sales as s', function ($join) {
                    $join->on('cle.reference_id', '=', 's.id')
                        ->where('cle.reference_type', '=', 'Sale');
                })
                ->where('cle.entry_type', 'sale')
                ->whereIn('s.payment_method', ['cash', 'card', 'upi', 'bank_transfer', 'cheque', 'other'])
                ->orderBy('cle.transaction_date')
                ->orderBy('cle.id')
                ->pluck('cle.id');

            $entriesToDelete = CustomerLedgerEntry::whereIn('id', $deleteIds)->get();

            $this->info("Found {$entriesToDelete->count()} ledger entries for paid sales (should be deleted)");
            $this->newLine();

            if ($entriesToDelete->isEmpty()) {
                $this->info('✅ No entries to delete. Ledger only contains credit sales!');
                DB::commit();
                return 0;
            }

            $deletedCount = 0;
            $errors = [];

            foreach ($entriesToDelete as $entry) {
                try {
                    $sale = Sale::find($entry->reference_id);

                    if (!$sale) {
                        $errors[] = "Entry ID {$entry->id}: Sale not found (ID: {$entry->reference_id})";
                        continue;
                    }

                    $this->line("Deleting Entry ID {$entry->id}: Invoice {$sale->invoice_number} - Payment: {$sale->payment_method} - Amount: ₹{$sale->total_amount}");

                    if (!$dryRun) {
                        $entry->delete();
                    }

                    $deletedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Entry ID {$entry->id}: {$e->getMessage()}";
                }
            }

            $this->newLine();
            
            if ($dryRun) {
                $this->info("✅ Would delete {$deletedCount} entries");
            } else {
                $this->info("✅ Deleted {$deletedCount} entries");
            }

            if (!empty($errors)) {
                $this->newLine();
                $this->warn('⚠️  Errors encountered:');
                foreach ($errors as $error) {
                    $this->error($error);
                }
            }

            if ($dryRun) {
                DB::rollBack();
                $this->newLine();
                $this->info('No changes made (dry run). Run without --dry-run to apply fixes.');
            } else {
                DB::commit();
                $this->newLine();
                $this->info('✅ All changes committed successfully!');
                $this->info('   Customer ledger now only tracks credit sales.');
            }

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Recalculate running balance for a customer's ledger entries
     */
    protected function recalculateCustomerBalance(int $customerId): void
    {
        $this->line("  Recalculating balance for customer ID: {$customerId}");

        $entries = CustomerLedgerEntry::where('customer_id', $customerId)
            ->orderBy('transaction_date')
            ->orderBy('id')

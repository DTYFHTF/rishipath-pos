<?php

namespace App\Console\Commands;

use App\Models\CustomerLedgerEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDuplicateLedgerEntries extends Command
{
    protected $signature = 'ledger:cleanup-duplicates';

    protected $description = 'Remove duplicate customer ledger entries created for the same sale';

    public function handle()
    {
        $this->info('Starting cleanup of duplicate ledger entries...');

        DB::beginTransaction();

        try {
            // Find duplicate entries for the same sale
            $duplicates = CustomerLedgerEntry::select('reference_type', 'reference_id', DB::raw('COUNT(*) as count'))
                ->where('reference_type', 'Sale')
                ->whereNotNull('reference_id')
                ->groupBy('reference_type', 'reference_id')
                ->having('count', '>', 2) // More than 2 entries (should only have 2 for cash/card/upi sales, 1 for credit)
                ->get();

            $this->info("Found {$duplicates->count()} sales with duplicate entries");

            $totalRemoved = 0;

            foreach ($duplicates as $duplicate) {
                $this->info("Processing Sale ID: {$duplicate->reference_id}");

                // Get all entries for this sale
                $entries = CustomerLedgerEntry::where('reference_type', 'Sale')
                    ->where('reference_id', $duplicate->reference_id)
                    ->orderBy('id', 'asc')
                    ->get();

                $this->comment("  Found {$entries->count()} entries");

                // Group entries by type
                $saleEntries = $entries->where('entry_type', 'receivable')->values();
                $paymentEntries = $entries->where('entry_type', 'payment')->values();

                // Keep only the first sale entry and first payment entry
                $entriesToDelete = [];

                if ($saleEntries->count() > 1) {
                    $this->comment("  Found {$saleEntries->count()} sale entries, keeping first one");
                    $entriesToDelete = array_merge($entriesToDelete, $saleEntries->slice(1)->pluck('id')->toArray());
                }

                if ($paymentEntries->count() > 1) {
                    $this->comment("  Found {$paymentEntries->count()} payment entries, keeping first one");
                    $entriesToDelete = array_merge($entriesToDelete, $paymentEntries->slice(1)->pluck('id')->toArray());
                }

                if (!empty($entriesToDelete)) {
                    $removed = CustomerLedgerEntry::whereIn('id', $entriesToDelete)->delete();
                    $totalRemoved += $removed;
                    $this->warn("  Removed {$removed} duplicate entries");
                }
            }

            // Now recalculate balances for all affected customers
            $this->info("\nRecalculating balances...");
            $affectedCustomers = CustomerLedgerEntry::select('customer_id')
                ->distinct()
                ->get();

            foreach ($affectedCustomers as $customerRecord) {
                $this->recalculateCustomerBalance($customerRecord->customer_id);
            }

            DB::commit();

            $this->info("\n✅ Cleanup completed successfully!");
            $this->info("Total duplicate entries removed: {$totalRemoved}");
            $this->info("Balances recalculated for {$affectedCustomers->count()} customers");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Error during cleanup: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    protected function recalculateCustomerBalance($customerId)
    {
        $entries = CustomerLedgerEntry::where('customer_id', $customerId)
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $runningBalance = 0;

        foreach ($entries as $entry) {
            $runningBalance += $entry->debit_amount;
            $runningBalance -= $entry->credit_amount;

            if ($entry->balance != $runningBalance) {
                $entry->balance = $runningBalance;
                $entry->saveQuietly(); // Don't trigger events
            }
        }

        $this->comment("  Recalculated balance for Customer ID: {$customerId} -> {$runningBalance}");
    }
}

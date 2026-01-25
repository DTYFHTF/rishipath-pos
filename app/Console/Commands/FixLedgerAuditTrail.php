<?php

namespace App\Console\Commands;

use App\Models\CustomerLedgerEntry;
use App\Models\Sale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixLedgerAuditTrail extends Command
{
    protected $signature = 'ledger:fix-audit-trail {--dry-run : Show what would be changed without applying}';

    protected $description = 'Fix customer ledger to show full audit trail (DEBIT for all sales, CREDIT for payments)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info('Analyzing customer ledger entries...');

        // Get all sales with customers
        $sales = Sale::whereNotNull('customer_id')
            ->with('customer', 'store')
            ->orderBy('created_at')
            ->get();

        $this->info("Found {$sales->count()} sales with customers");

        $toFix = [];
        $toCreate = [];

        foreach ($sales as $sale) {
            $entries = CustomerLedgerEntry::where('reference_type', 'Sale')
                ->where('reference_id', $sale->id)
                ->orderBy('created_at')
                ->get();

            $isCredit = $sale->payment_method === 'credit';

            if ($isCredit) {
                // Credit sale should have 1 DEBIT entry with type 'receivable'
                if ($entries->count() === 1 && $entries[0]->debit_amount > 0 && $entries[0]->entry_type === 'receivable') {
                    // Correct
                    continue;
                } elseif ($entries->count() === 1 && ($entries[0]->credit_amount > 0 || $entries[0]->entry_type !== 'receivable')) {
                    // Wrong: has CREDIT instead of DEBIT or wrong type
                    $toFix[] = [
                        'sale' => $sale,
                        'entry' => $entries[0],
                        'issue' => 'Credit sale recorded as CREDIT instead of DEBIT or wrong type',
                    ];
                } else {
                    // Missing or multiple entries
                    $toFix[] = [
                        'sale' => $sale,
                        'entry' => $entries->first(),
                        'issue' => 'Credit sale has ' . $entries->count() . ' entries (should be 1 DEBIT receivable)',
                    ];
                }
            } else {
                // Cash/Card/UPI sale should have 2 entries (DEBIT receivable + CREDIT payment)
                $receivableEntry = $entries->where('entry_type', 'receivable')->first();
                $paymentEntry = $entries->where('entry_type', 'payment')->first();
                
                if ($entries->count() === 2 && $receivableEntry && $receivableEntry->debit_amount > 0 && $paymentEntry && $paymentEntry->credit_amount > 0) {
                    // Correct
                    continue;
                } elseif ($entries->count() === 1 && $entries[0]->credit_amount > 0) {
                    // Wrong: only has CREDIT, missing DEBIT receivable
                    $toCreate[] = [
                        'sale' => $sale,
                        'entry' => $entries[0],
                        'issue' => 'Paid sale only has CREDIT, missing DEBIT receivable entry',
                    ];
                } elseif ($entries->count() === 1 && $entries[0]->debit_amount > 0) {
                    // Wrong: only has DEBIT, missing CREDIT payment
                    $toCreate[] = [
                        'sale' => $sale,
                        'entry' => $entries[0],
                        'issue' => 'Paid sale only has DEBIT receivable, missing CREDIT payment entry',
                    ];
                } elseif ($entries->count() === 0) {
                    // Missing entirely
                    $toCreate[] = [
                        'sale' => $sale,
                        'entry' => null,
                        'issue' => 'Paid sale has no ledger entries',
                    ];
                } else {
                    // Other issues
                    $toFix[] = [
                        'sale' => $sale,
                        'entry' => $entries->first(),
                        'issue' => 'Paid sale has ' . $entries->count() . ' entries (should be 2: DEBIT receivable + CREDIT payment)',
                    ];
                }
            }
        }

        $this->info("Found " . count($toFix) . " entries to fix");
        $this->info("Found " . count($toCreate) . " entries to create");

        if (empty($toFix) && empty($toCreate)) {
            $this->info('✅ All entries are correct!');
            return 0;
        }

        if ($dryRun) {
            $this->warn("\nWould fix the following entries:");
            foreach ($toFix as $item) {
                $this->line("  - Sale {$item['sale']->invoice_number}: {$item['issue']}");
            }
            foreach ($toCreate as $item) {
                $this->line("  - Sale {$item['sale']->invoice_number}: {$item['issue']}");
            }
            return 0;
        }

        if (!$this->confirm('Proceed with fixing entries?', true)) {
            $this->info('Aborted.');
            return 0;
        }

        DB::transaction(function () use ($toFix, $toCreate) {
            // Delete all existing entries for these sales
            $saleIds = collect($toFix)->merge($toCreate)->pluck('sale.id')->unique();
            
            $this->info("Deleting existing entries for {$saleIds->count()} sales...");
            CustomerLedgerEntry::where('reference_type', 'Sale')
                ->whereIn('reference_id', $saleIds)
                ->delete();

            // Recreate entries with correct logic
            $this->info("Recreating entries...");
            
            foreach ($saleIds as $saleId) {
                $sale = Sale::with('customer', 'store')->find($saleId);
                if ($sale && $sale->customer) {
                    CustomerLedgerEntry::createSaleEntry($sale);
                }
            }

            $this->info("Recalculating balances...");
            $this->recalculateBalances();
        });

        $this->info('✅ Fixed ' . (count($toFix) + count($toCreate)) . ' entries');
        $this->info('✅ All ledger balances recalculated');

        return 0;
    }

    protected function recalculateBalances(): void
    {
        // Get all customers with ledger entries
        $customers = CustomerLedgerEntry::select('customer_id')
            ->distinct()
            ->pluck('customer_id');

        foreach ($customers as $customerId) {
            $runningBalance = 0;
            $entries = CustomerLedgerEntry::forCustomer($customerId)
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();

            foreach ($entries as $entry) {
                $runningBalance += $entry->debit_amount;
                $runningBalance -= $entry->credit_amount;
                $entry->balance = $runningBalance;
                $entry->saveQuietly(); // Skip observers
            }
        }
    }
}

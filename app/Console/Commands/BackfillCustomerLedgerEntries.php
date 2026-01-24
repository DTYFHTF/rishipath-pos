<?php

namespace App\Console\Commands;

use App\Models\CustomerLedgerEntry;
use App\Models\Sale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCustomerLedgerEntries extends Command
{
    protected $signature = 'ledger:backfill {--dry-run : Show what would be created without actually creating}';

    protected $description = 'Backfill customer ledger entries for existing sales that have customers but no ledger entries';

    public function handle(): int
    {
        $this->info('Scanning for sales with customers but no ledger entries...');

        // Find all completed sales with customers that don't have a ledger entry
        $salesWithoutLedger = Sale::query()
            ->whereNotNull('customer_id')
            ->where('status', 'completed')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('customer_ledger_entries')
                    ->whereColumn('customer_ledger_entries.reference_id', 'sales.id')
                    ->where('customer_ledger_entries.reference_type', 'Sale');
            })
            ->with('customer')
            ->get();

        $count = $salesWithoutLedger->count();

        if ($count === 0) {
            $this->info('✅ All sales already have ledger entries. Nothing to backfill.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} sales without ledger entries.");

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN - No changes will be made.');
            $this->newLine();

            $this->table(
                ['Sale ID', 'Invoice #', 'Customer', 'Date', 'Total'],
                $salesWithoutLedger->map(fn ($sale) => [
                    $sale->id,
                    $sale->invoice_number ?? $sale->sale_number,
                    $sale->customer?->name ?? 'Unknown',
                    $sale->sale_date ?? $sale->created_at->format('Y-m-d'),
                    '₹' . number_format($sale->total_amount, 2),
                ])
            );

            return self::SUCCESS;
        }

        $this->newLine();
        if (! $this->confirm("Create ledger entries for {$count} sales?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        $created = 0;
        $errors = 0;

        foreach ($salesWithoutLedger as $sale) {
            try {
                DB::transaction(function () use ($sale) {
                    CustomerLedgerEntry::createSaleEntry($sale);
                });
                $created++;
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Failed for Sale #{$sale->id}: " . $e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("✅ Created {$created} ledger entries.");
        if ($errors > 0) {
            $this->warn("⚠️  {$errors} entries failed.");
        }

        return self::SUCCESS;
    }
}

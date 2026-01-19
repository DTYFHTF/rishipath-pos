<?php

namespace App\Console\Commands;

use App\Models\ProductBatch;
use App\Models\StockLevel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStockLevels extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'stock:sync
                            {--store= : Sync only for specific store ID}
                            {--force : Force sync even if stock levels exist}';

    /**
     * The console command description.
     */
    protected $description = 'Sync StockLevel records from ProductBatch quantities';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting stock level sync from product batches...');
        $this->newLine();

        $storeFilter = $this->option('store');
        $force = $this->option('force');

        // Get all unique store + variant combinations from batches
        $query = DB::table('product_batches')
            ->select('store_id', 'product_variant_id')
            ->selectRaw('SUM(quantity_remaining) as total_quantity')
            ->groupBy('store_id', 'product_variant_id');

        if ($storeFilter) {
            $query->where('store_id', $storeFilter);
            $this->info("Filtering for store ID: {$storeFilter}");
        }

        $batchSummaries = $query->get();

        if ($batchSummaries->isEmpty()) {
            $this->warn('No product batches found to sync!');
            return self::SUCCESS;
        }

        $this->info("Found {$batchSummaries->count()} variant-store combinations to sync");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($batchSummaries->count());
        $progressBar->start();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($batchSummaries as $summary) {
            // Check if stock level exists
            $stockLevel = StockLevel::where('product_variant_id', $summary->product_variant_id)
                ->where('store_id', $summary->store_id)
                ->first();

            if ($stockLevel && !$force) {
                // Stock level exists and not forcing, skip
                $skipped++;
            } elseif ($stockLevel) {
                // Update existing
                $stockLevel->quantity = (int) $summary->total_quantity;
                $stockLevel->last_movement_at = now();
                $stockLevel->save();
                $updated++;
            } else {
                // Create new stock level
                StockLevel::create([
                    'product_variant_id' => $summary->product_variant_id,
                    'store_id' => $summary->store_id,
                    'quantity' => (int) $summary->total_quantity,
                    'reserved_quantity' => 0,
                    'reorder_level' => 10,
                    'last_movement_at' => now(),
                ]);
                $created++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('✅ Stock level sync completed!');
        $this->newLine();
        $this->table(
            ['Action', 'Count'],
            [
                ['Created', $created],
                ['Updated', $updated],
                ['Skipped', $skipped],
                ['Total', $batchSummaries->count()],
            ]
        );

        return self::SUCCESS;
    }
}

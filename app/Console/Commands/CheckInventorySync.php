<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckInventorySync extends Command
{
    protected $signature = 'inventory:check-sync {--fix : Automatically fix mismatches}';
    
    protected $description = 'Check if stock_levels matches sum of product_batches quantities';

    public function handle()
    {
        $this->info('Checking inventory sync between stock_levels and product_batches...');
        $this->newLine();

        $mismatches = [];
        $stockLevels = DB::table('stock_levels')->get();

        foreach ($stockLevels as $stock) {
            $batchSum = (float) DB::table('product_batches')
                ->where('product_variant_id', $stock->product_variant_id)
                ->where('store_id', $stock->store_id)
                ->sum('quantity_remaining');

            $stockQty = (float) $stock->quantity;

            if (abs($stockQty - $batchSum) > 0.001) {
                $mismatches[] = [
                    'variant_id' => $stock->product_variant_id,
                    'store_id' => $stock->store_id,
                    'stock_level' => $stockQty,
                    'batch_sum' => $batchSum,
                    'difference' => $stockQty - $batchSum,
                ];
            }
        }

        if (empty($mismatches)) {
            $this->info('✅ All stock levels are in sync with batches!');
            return 0;
        }

        $this->error('⚠️  Found ' . count($mismatches) . ' mismatch(es):');
        $this->newLine();

        $headers = ['Variant ID', 'Store ID', 'Stock Level', 'Batch Sum', 'Difference'];
        $rows = array_map(function ($m) {
            return [
                $m['variant_id'],
                $m['store_id'],
                $m['stock_level'],
                $m['batch_sum'],
                ($m['difference'] > 0 ? '+' : '') . $m['difference'],
            ];
        }, $mismatches);

        $this->table($headers, $rows);

        if ($this->option('fix')) {
            $this->newLine();
            $this->warn('Fixing mismatches by updating stock_levels to match batch sums...');

            foreach ($mismatches as $mismatch) {
                DB::table('stock_levels')
                    ->where('product_variant_id', $mismatch['variant_id'])
                    ->where('store_id', $mismatch['store_id'])
                    ->update([
                        'quantity' => $mismatch['batch_sum'],
                        'last_movement_at' => now(),
                        'updated_at' => now(),
                    ]);

                $this->info("✓ Fixed variant {$mismatch['variant_id']} at store {$mismatch['store_id']}");
            }

            $this->newLine();
            $this->info('✅ All mismatches fixed!');
        } else {
            $this->newLine();
            $this->comment('Run with --fix to automatically correct these mismatches.');
        }

        return empty($mismatches) || $this->option('fix') ? 0 : 1;
    }
}

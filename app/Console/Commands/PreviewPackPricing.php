<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\PackPricing;
use Illuminate\Console\Command;

/**
 * Shows what every shelf price would become under the derived-pricing rule.
 *
 * Read-only by default. --apply writes the new prices, and is the only way
 * this command touches data.
 */
class PreviewPackPricing extends Command
{
    protected $signature = 'pricing:preview
                            {--org= : Limit to one organization id}
                            {--rises : Show only prices that would increase}
                            {--csv= : Write the full before/after list to this path}
                            {--allow-rises : Let derived prices go above today\'s price (default: keep the lower price)}
                            {--apply : Actually write the new prices (default is a dry run)}';

    protected $description = 'Preview (or apply) pack prices derived from each product\'s 1kg price';

    public function handle(): int
    {
        $products = Product::with(['variants' => fn ($q) => $q->where('active', true)])
            ->where('active', true)
            ->when($this->option('org'), fn ($q, $org) => $q->where('organization_id', $org))
            ->orderBy('name')
            ->get();

        $rows = [];
        $noReference = [];
        $allowRises = (bool) $this->option('allow-rises');

        foreach ($products as $product) {
            $markup = PackPricing::markupFor($product);

            if (PackPricing::costPerKg($product) === null) {
                if ($product->variants->isNotEmpty()) {
                    $noReference[] = $product->name;
                }

                continue;
            }

            foreach (PackPricing::previewProduct($product, $markup, $allowRises) as $entry) {
                if ($entry['derived'] === null) {
                    continue;
                }

                $rows[] = [
                    'product' => $product->name,
                    'pack' => $entry['variant']->pack_label,
                    'variant_id' => $entry['variant']->id,
                    'current' => $entry['current'],
                    'derived' => $entry['derived'],
                    'change' => $entry['current'] > 0
                        ? ($entry['derived'] - $entry['current']) / $entry['current']
                        : 0.0,
                    'locked' => $entry['locked'],
                    'capped' => $entry['capped'],
                ];
            }
        }

        $changed = array_filter($rows, fn ($r) => abs($r['derived'] - $r['current']) > 0.001);
        $rises = array_filter($changed, fn ($r) => $r['derived'] > $r['current']);
        $falls = array_filter($changed, fn ($r) => $r['derived'] < $r['current']);

        $this->newLine();
        $this->info(sprintf(
            '%d prices reviewed — %d fall, %d rise, %d unchanged, %d held at today\'s lower price',
            count($rows), count($falls), count($rises), count($rows) - count($changed),
            count(array_filter($rows, fn ($r) => $r['capped']))
        ));

        if ($noReference !== []) {
            $this->warn(count($noReference).' product(s) have no usable cost price and were skipped:');
            $this->line('  '.implode(', ', array_slice($noReference, 0, 8)).(count($noReference) > 8 ? ' …' : ''));
        }

        $show = $this->option('rises') ? $rises : $changed;
        usort($show, fn ($a, $b) => $a['change'] <=> $b['change']);

        if ($show !== []) {
            $this->newLine();
            $this->table(
                ['Product', 'Pack', 'Now', 'New', 'Change'],
                array_map(fn ($r) => [
                    \Illuminate\Support\Str::limit($r['product'], 28),
                    $r['pack'],
                    number_format($r['current']),
                    number_format($r['derived']),
                    sprintf('%+.0f%%', 100 * $r['change']),
                ], $this->option('rises') ? $show : array_merge(
                    array_slice($show, 0, 10),           // biggest falls
                    array_slice($show, -10)              // biggest rises
                ))
            );
        }

        if ($path = $this->option('csv')) {
            $fh = fopen($path, 'w');
            fputcsv($fh, ['product', 'pack', 'variant_id', 'current', 'derived', 'change_pct', 'locked']);
            foreach ($rows as $r) {
                fputcsv($fh, [$r['product'], $r['pack'], $r['variant_id'],
                    $r['current'], $r['derived'], round(100 * $r['change'], 1), $r['locked'] ? 'yes' : 'no']);
            }
            fclose($fh);
            $this->info("Full list written to {$path}");
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->comment('Dry run — nothing was written. Re-run with --apply to save these prices.');

            return self::SUCCESS;
        }

        if (! $this->confirm(sprintf('Write %d changed price(s) to the database?', count($changed)), false)) {
            $this->comment('Aborted, nothing written.');

            return self::SUCCESS;
        }

        $written = 0;
        foreach ($changed as $r) {
            $variant = \App\Models\ProductVariant::find($r['variant_id']);

            if (! $variant || $variant->manual_price_locked) {
                continue;
            }

            $variant->forceFill([
                'selling_price_nepal' => $r['derived'],
                'base_price' => $r['derived'],
                'mrp_india' => $r['derived'],
            ])->saveQuietly();

            $written++;
        }

        $this->info("Updated {$written} price(s).");

        return self::SUCCESS;
    }
}

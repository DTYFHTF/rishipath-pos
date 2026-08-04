<?php

namespace App\Console\Commands;

use App\Models\SaleItem;
use App\Services\PackPricing;
use Illuminate\Console\Command;

/**
 * What the repricing would have done to money already taken.
 *
 * Cutting small-pack prices by ~40% is only a good trade if small packs are a
 * modest share of revenue, or if the lower price moves enough extra volume to
 * cover it. Neither is knowable from the catalogue alone — it needs real sales.
 * This replays past sales at the new prices and reports the gap.
 *
 * Run it on the server, where the sales live:
 *   php artisan pricing:impact --days=90
 */
class PricingImpactReport extends Command
{
    protected $signature = 'pricing:impact
                            {--days=90 : How far back to look}
                            {--org= : Limit to one organization id}';

    protected $description = 'Replay past sales at the new derived prices and report the revenue difference';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $since = now()->subDays($days);

        $items = SaleItem::query()
            ->with(['productVariant.product.variants', 'sale'])
            ->whereHas('sale', function ($q) use ($since) {
                $q->where('created_at', '>=', $since)
                    ->where('status', 'completed')
                    // Credit sales that were never settled are not revenue.
                    ->where('payment_status', 'paid')
                    ->when($this->option('org'), fn ($q, $org) => $q->where('organization_id', $org));
            })
            ->get();

        if ($items->isEmpty()) {
            $this->warn("No paid, completed sales in the last {$days} days — nothing to measure.");
            $this->line('Run this on the production server, where the sales history lives.');

            return self::SUCCESS;
        }

        $byPack = [];
        $oldRevenue = 0.0;
        $newRevenue = 0.0;
        $cost = 0.0;
        $skipped = 0;

        foreach ($items as $item) {
            $variant = $item->productVariant;

            if (! $variant || ! $variant->product) {
                $skipped++;

                continue;
            }

            $qty = (float) $item->quantity;
            $soldAt = (float) $item->price_per_unit;

            $preview = PackPricing::previewProduct($variant->product, allowRises: true);
            $derived = $preview[$variant->id]['derived'] ?? null;

            if ($derived === null) {
                $skipped++;

                continue;
            }

            $label = $variant->pack_label ?: 'unknown';
            $byPack[$label] ??= ['units' => 0.0, 'old' => 0.0, 'new' => 0.0];
            $byPack[$label]['units'] += $qty;
            $byPack[$label]['old'] += $soldAt * $qty;
            $byPack[$label]['new'] += $derived * $qty;

            $oldRevenue += $soldAt * $qty;
            $newRevenue += $derived * $qty;
            $cost += (float) $item->cost_price * $qty;
        }

        if ($oldRevenue <= 0) {
            $this->warn('No priced sale lines found.');

            return self::SUCCESS;
        }

        // Order packs smallest first so the shape of the change is readable.
        uksort($byPack, function ($a, $b) {
            $grams = fn ($s) => str_contains(strtoupper($s), 'KG')
                ? (float) $s * 1000
                : (float) $s;

            return $grams($a) <=> $grams($b);
        });

        $this->newLine();
        $this->info("Replaying {$items->count()} sale lines from the last {$days} days at the new prices");
        $this->newLine();

        $rows = [];
        foreach ($byPack as $label => $d) {
            $share = 100 * $d['old'] / $oldRevenue;
            $delta = $d['old'] > 0 ? 100 * ($d['new'] - $d['old']) / $d['old'] : 0;
            $rows[] = [
                $label,
                number_format($d['units']),
                sprintf('%.1f%%', $share),
                number_format($d['old']),
                number_format($d['new']),
                sprintf('%+.1f%%', $delta),
            ];
        }

        $this->table(
            ['Pack', 'Units', 'Share of revenue', 'Was', 'Would be', 'Change'],
            $rows
        );

        $deltaPct = 100 * ($newRevenue - $oldRevenue) / $oldRevenue;
        $oldMargin = $oldRevenue > 0 ? 100 * ($oldRevenue - $cost) / $oldRevenue : 0;
        $newMargin = $newRevenue > 0 ? 100 * ($newRevenue - $cost) / $newRevenue : 0;

        $this->line(sprintf('  Revenue   %s  ->  %s   (%+.1f%%)',
            number_format($oldRevenue), number_format($newRevenue), $deltaPct));
        $this->line(sprintf('  Gross margin  %.1f%%  ->  %.1f%%', $oldMargin, $newMargin));

        if ($skipped > 0) {
            $this->line("  ({$skipped} line(s) skipped — variant deleted or no cost price)");
        }

        $this->newLine();

        if ($deltaPct < -5) {
            $breakeven = $newRevenue > 0 ? ($oldRevenue / $newRevenue - 1) * 100 : 0;
            $this->warn(sprintf(
                'At the same volumes this is a %.1f%% revenue cut. Unit sales would need to rise about %.0f%% to stand still.',
                abs($deltaPct), $breakeven
            ));
        } elseif ($deltaPct > 5) {
            $this->info(sprintf('This is a %.1f%% revenue increase at the same volumes.', $deltaPct));
        } else {
            $this->info(sprintf('Revenue is broadly unchanged (%+.1f%%) at the same volumes.', $deltaPct));
        }

        return self::SUCCESS;
    }
}

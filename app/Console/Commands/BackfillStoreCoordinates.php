<?php

namespace App\Console\Commands;

use App\Models\RetailStore;
use App\Services\GoogleMapsLink;
use Illuminate\Console\Command;

/**
 * Derives latitude/longitude for retail stores that only have a Google Maps
 * link. Without coordinates the Visit Planner cannot cluster stores by area or
 * build a route, so this is the bridge between how the field team captures a
 * store and what the planner needs.
 */
class BackfillStoreCoordinates extends Command
{
    protected $signature = 'stores:backfill-coordinates
                            {--org= : Limit to a single organization id}
                            {--force : Re-resolve stores that already have coordinates}
                            {--dry-run : Report what would change without saving}';

    protected $description = 'Extract latitude/longitude from retail store Google Maps links';

    public function handle(GoogleMapsLink $links): int
    {
        $query = RetailStore::query()
            ->whereNotNull('google_location_url')
            ->where('google_location_url', '!=', '')
            ->when($this->option('org'), fn ($q, $org) => $q->where('organization_id', $org))
            ->when(! $this->option('force'), fn ($q) => $q->where(
                fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude')
            ));

        $total = $query->count();

        if ($total === 0) {
            $this->info('No stores need coordinates.');

            return self::SUCCESS;
        }

        $this->info("Resolving coordinates for {$total} store(s)…");
        $bar = $this->output->createProgressBar($total);
        $dryRun = $this->option('dry-run');

        $resolved = 0;
        $failed = [];

        $query->each(function (RetailStore $store) use ($links, $dryRun, $bar, &$resolved, &$failed) {
            $coords = $links->coordinatesFor($store->google_location_url);

            if (! $coords) {
                $failed[] = $store;
                $bar->advance();

                return;
            }

            if (! $dryRun) {
                $store->forceFill([
                    'latitude' => $coords['lat'],
                    'longitude' => $coords['lng'],
                    // Keep the canonical URL when a short link was expanded so
                    // the next run does not have to hit the network again.
                    'google_location_url' => $coords['expanded_url'] ?? $store->google_location_url,
                ])->saveQuietly();
            }

            $resolved++;
            $bar->advance();
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(($dryRun ? '[dry run] ' : '')."Resolved {$resolved} of {$total} store(s).");

        if ($failed !== []) {
            $this->warn(count($failed).' store(s) had a link with no usable coordinates:');
            $this->table(
                ['ID', 'Store', 'URL'],
                collect($failed)->take(25)->map(fn (RetailStore $s) => [
                    $s->id,
                    \Illuminate\Support\Str::limit($s->store_name, 32),
                    \Illuminate\Support\Str::limit($s->google_location_url, 60),
                ])->all()
            );
            $this->line('Open each link, then use the map picker on the store to drop a pin.');
        }

        return self::SUCCESS;
    }
}

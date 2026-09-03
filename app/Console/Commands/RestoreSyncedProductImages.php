<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Points image_url back at the real photograph a product already has.
 *
 * products:sync-web-images writes the photo it pulls from shuddhidham.com to
 * both image_url and image_1. ProductImageSeeder then ran on every deploy and
 * overwrote image_url with a file matched by product name, leaving the real
 * photograph sitting untouched in image_1 while the POS and price list - which
 * read image_url - showed the older picture. 39 products on production had
 * drifted this way.
 *
 * The seeder no longer does that (see ProductImageSeeder::mayReplaceImageUrl),
 * so this repairs what it already did. Safe to re-run: it only ever copies a
 * storage-disk path from image_1 onto an image_url that is not one already.
 */
class RestoreSyncedProductImages extends Command
{
    protected $signature = 'products:restore-synced-images
                            {--dry-run : List what would change without saving}';

    protected $description = 'Point image_url back at the synced photo held in image_1';

    public function handle(): int
    {
        $products = Product::query()
            ->where('image_1', 'like', 'product-images/%')
            ->where(function ($q) {
                $q->whereNull('image_url')
                    ->orWhere('image_url', '')
                    ->orWhere('image_url', 'like', '/%');
            })
            ->orderBy('name')
            ->get();

        if ($products->isEmpty()) {
            $this->info('Every synced photo is already the product image. Nothing to restore.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            '%s %d product(s) to their synced photo…',
            $dryRun ? 'Would restore' : 'Restoring',
            $products->count(),
        ));

        $rows = [];

        foreach ($products as $product) {
            $rows[] = [$product->sku, $product->name, $product->image_url ?: '(none)', $product->image_1];

            if (! $dryRun) {
                // saveQuietly: Product::updating() regenerates the SKU when
                // name/category/type change. Nothing here touches those, but a
                // silent save keeps a catalogue repair well clear of it.
                $product->forceFill(['image_url' => $product->image_1])->saveQuietly();
            }
        }

        $this->table(['SKU', 'Product', 'Was showing', 'Now showing'], $rows);

        $this->info($dryRun
            ? 'Dry run - nothing saved.'
            : $products->count().' product(s) restored. Regenerate the price list to refresh its cache.');

        return self::SUCCESS;
    }
}

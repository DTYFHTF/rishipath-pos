<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Pulls the product photography published on shuddhidham.com into the POS.
 *
 * The website keeps its images on Cloudinary and exposes every URL through its
 * public catalogue API, so nothing here scrapes the admin panel. The two
 * catalogues share no key — POS is keyed by SKU, the site by slug — so the
 * pairing comes from database/data/web_product_images.json, decided by hand
 * once rather than guessed from names on every run.
 *
 * Files land on the public disk alongside Filament's own uploads, so a photo
 * synced here can be replaced by hand in the admin afterwards. Re-running is
 * cheap: a photo already on disk is skipped unless --force is given, and a
 * replaced photo on the website gets a new Cloudinary id, hence a new filename.
 */
class SyncWebProductImages extends Command
{
    protected $signature = 'products:sync-web-images
                            {--sku=* : Limit to specific POS SKUs}
                            {--map= : Path to the SKU-to-slug mapping file}
                            {--width=800 : Cloudinary delivery width; the originals are ~1MB each}
                            {--force : Re-download images that are already synced}
                            {--prune : Delete synced files no product or variant points at any more}
                            {--dry-run : Report what would change without downloading or saving}';

    protected $description = 'Sync product images published on shuddhidham.com into the POS';

    private const DIRECTORY = 'product-images/web';

    /** Fields an image can be written to, in priority order. */
    private const SLOTS = ['image_1', 'image_2', 'image_3'];

    private bool $dryRun = false;

    private bool $force = false;

    private int $downloaded = 0;

    private int $skipped = 0;

    /** @var list<string> */
    private array $problems = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->force = (bool) $this->option('force');

        $map = $this->loadMap();

        if ($map === null) {
            return self::FAILURE;
        }

        if ($only = $this->option('sku')) {
            $map = array_intersect_key($map, array_flip($only));

            if ($map === []) {
                $this->error('None of the given SKUs are mapped in database/data/web_product_images.json.');

                return self::FAILURE;
            }
        }

        $catalogue = $this->fetchCatalogue();

        if ($catalogue === null) {
            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s %d mapped product(s) against %d product(s) on shuddhidham.com…',
            $this->dryRun ? 'Checking' : 'Syncing',
            count($map),
            count($catalogue),
        ));

        $rows = [];

        foreach ($map as $sku => $slug) {
            $product = Product::where('sku', $sku)->first();

            if (! $product) {
                $this->problems[] = "{$sku}: no such product in the POS (stale mapping?)";

                continue;
            }

            $web = $catalogue[$slug] ?? null;

            if (! $web) {
                $this->problems[] = "{$sku}: '{$slug}' is not in the published catalogue (renamed or deactivated?)";

                continue;
            }

            if (($web['images'] ?? []) === []) {
                $this->problems[] = "{$sku}: '{$slug}' has no photo published on the website yet";
            }

            $paths = $this->syncImages($sku, $web['images'] ?? []);

            if ($paths !== []) {
                $rows[] = [$sku, $product->name, $web['slug'], count($paths)];

                if (! $this->dryRun) {
                    $product->forceFill([
                        // The POS grid and the product table read image_url; the
                        // variant fallback in EnhancedPOS reads image_1. Both get
                        // the primary shot so neither surface is left behind.
                        'image_url' => $paths[0],
                        ...array_combine(array_slice(self::SLOTS, 0, count($paths)), $paths),
                    ])->save();
                }
            }

            $this->syncVariantImages($product, $web);
        }

        if ($this->option('prune')) {
            $this->prune();
        }

        $this->newLine();

        if ($rows !== []) {
            $this->table(['SKU', 'Product', 'Website slug', 'Images'], $rows);
        }

        $this->info(sprintf(
            '%s %d image(s), %d already up to date, %d product(s) updated.',
            $this->dryRun ? 'Would download' : 'Downloaded',
            $this->downloaded,
            $this->skipped,
            count($rows),
        ));

        foreach ($this->problems as $problem) {
            $this->warn($problem);
        }

        return self::SUCCESS;
    }

    /**
     * Deletes synced files nothing points at any more. Re-pointing a SKU at a
     * different slug, or the website replacing a photo, leaves the previous
     * download behind; nothing else ever cleans those up.
     */
    private function prune(): void
    {
        // Variants have no image_url column, so read whatever slots each model
        // actually has rather than selecting a fixed column list.
        $referenced = collect([Product::query(), ProductVariant::query()])
            ->flatMap(fn ($query) => $query->get()->flatMap(
                fn ($row) => array_values($row->only(['image_url', ...self::SLOTS]))
            ))
            ->filter()
            ->unique()
            ->all();

        foreach (Storage::disk('public')->files(self::DIRECTORY) as $file) {
            if (in_array($file, $referenced, true)) {
                continue;
            }

            $this->line("  pruned {$file}");

            if (! $this->dryRun) {
                Storage::disk('public')->delete($file);
            }
        }
    }

    /**
     * @return array<string, string>|null  SKU => website slug
     */
    private function loadMap(): ?array
    {
        $path = $this->option('map') ?: database_path('data/web_product_images.json');

        if (! is_file($path)) {
            $this->error("Mapping file missing: {$path}");

            return null;
        }

        $map = json_decode((string) file_get_contents($path), true);

        if (! is_array($map) || ! isset($map['products'])) {
            $this->error("Mapping file is not valid JSON with a 'products' object: {$path}");

            return null;
        }

        return $map['products'];
    }

    /**
     * @return array<string, array<string, mixed>>|null  slug => product payload
     */
    private function fetchCatalogue(): ?array
    {
        $base = rtrim((string) config('services.shuddhidham_web.url'), '/');
        $catalogue = [];
        $page = 1;

        do {
            $response = Http::timeout(30)->retry(2, 500)
                ->get("{$base}/api/products", ['perPage' => 100, 'page' => $page]);

            if ($response->failed()) {
                $this->error("Catalogue request failed ({$response->status()}): {$base}/api/products");

                return null;
            }

            $body = $response->json();
            $rows = $body['data'] ?? $body;

            if (! is_array($rows)) {
                $this->error('Catalogue response was not in the expected shape.');

                return null;
            }

            foreach ($rows as $row) {
                $catalogue[$row['slug']] = $row;
            }

            $lastPage = $body['lastPage'] ?? $body['last_page'] ?? 1;
        } while (++$page <= (int) $lastPage);

        return $catalogue;
    }

    /**
     * Downloads a product's published images and returns their public-disk paths.
     *
     * @param  array<int, array<string, mixed>>  $images
     * @return list<string>
     */
    private function syncImages(string $sku, array $images): array
    {
        $paths = [];

        foreach (array_slice($images, 0, count(self::SLOTS)) as $index => $image) {
            $url = $image['url'] ?? null;

            if (! $url) {
                continue;
            }

            $path = $this->download($sku, $url, $image['cloudinaryId'] ?? $image['cloudinary_id'] ?? null, $index);

            if ($path) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Matches website variants to POS variants by pack size (the website labels
     * them "100g"/"1kg", the POS stores 100 GMS / 1 KG) and syncs their photos.
     *
     * @param  array<string, mixed>  $web
     */
    private function syncVariantImages(Product $product, array $web): void
    {
        $labelled = [];

        foreach ($web['variants'] ?? [] as $variant) {
            if (($variant['images'] ?? []) !== []) {
                $labelled[$this->normaliseLabel((string) ($variant['label'] ?? ''))] = $variant;
            }
        }

        if ($labelled === []) {
            return;
        }

        foreach ($product->variants as $variant) {
            $match = $labelled[$this->packLabel($variant)] ?? null;

            if (! $match) {
                continue;
            }

            $paths = $this->syncImages("{$product->sku}-{$variant->sku}", $match['images']);

            if ($paths !== [] && ! $this->dryRun) {
                $variant->forceFill(
                    array_combine(array_slice(self::SLOTS, 0, count($paths)), $paths)
                )->save();
            }
        }
    }

    private function packLabel(ProductVariant $variant): string
    {
        $size = rtrim(rtrim(number_format((float) $variant->pack_size, 2, '.', ''), '0'), '.');
        $unit = strtoupper((string) $variant->unit) === 'KG' ? 'kg' : 'g';

        return $size.$unit;
    }

    private function normaliseLabel(string $label): string
    {
        $label = strtolower(str_replace(' ', '', $label));

        return str_replace(['gms', 'gm', 'grams', 'gram'], 'g', $label);
    }

    /**
     * Fetches one image and returns its path on the public disk, or null on failure.
     */
    private function download(string $sku, string $url, ?string $cloudinaryId, int $index): ?string
    {
        // Only photographs the business actually uploaded are synced. The rest of
        // what the catalogue serves is placeholder imagery — files the website
        // serves itself (/images/…, which the POS already ships its own copy of)
        // and at least one stock photo pointing at images.unsplash.com. Putting
        // those on a product would replace a real photo with a generic one.
        if (! str_starts_with($url, 'https://res.cloudinary.com/')) {
            $this->problems[] = "{$sku}: skipped '{$url}' — not an uploaded photo";

            return null;
        }

        // The published URL delivers the full-size original (~1MB). Cloudinary
        // resizes on request, so ask for a width the POS can actually use.
        $transform = 'f_auto,q_auto,w_'.(int) $this->option('width');

        $sized = preg_replace_callback('#/image/upload/([^/]+)/#', function (array $match) use ($transform) {
            // The segment after /upload/ is either an existing transformation
            // ("f_auto,q_auto") or the first folder of the asset path. Replacing
            // a folder would break the URL, so only swap a real transformation.
            $isTransformation = (bool) preg_match('/^[a-z]{1,3}_[^\/]+$/', $match[1]);

            return $isTransformation
                ? "/image/upload/{$transform}/"
                : "/image/upload/{$transform}/{$match[1]}/";
        }, $url, 1) ?? $url;

        $slug = str($cloudinaryId ? basename($cloudinaryId) : $url)->afterLast('/')->before('.')->slug();
        $stem = str($sku)->lower()->slug().'-'.($index + 1).'-'.$slug;

        foreach (['webp', 'jpg', 'png'] as $extension) {
            $existing = self::DIRECTORY."/{$stem}.{$extension}";

            if (Storage::disk('public')->exists($existing) && ! $this->force) {
                $this->skipped++;

                return $existing;
            }
        }

        if ($this->dryRun) {
            $this->downloaded++;

            return self::DIRECTORY."/{$stem}.webp";
        }

        $response = Http::timeout(60)->retry(2, 500)
            ->withHeaders(['Accept' => 'image/webp,image/jpeg;q=0.9,*/*;q=0.8'])
            ->get($sized);

        $type = strtolower((string) $response->header('Content-Type'));

        if ($response->failed() || ! str_starts_with($type, 'image/')) {
            $this->problems[] = "{$sku}: could not download image ".($response->status()).' '.$sized;

            return null;
        }

        $extension = match (true) {
            str_contains($type, 'webp') => 'webp',
            str_contains($type, 'png') => 'png',
            default => 'jpg',
        };

        $path = self::DIRECTORY."/{$stem}.{$extension}";
        Storage::disk('public')->put($path, $response->body());
        $this->downloaded++;

        return $path;
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Services\OrganizationContext;
use App\Services\PricingService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PriceListPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static string $view = 'filament.pages.price-list';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Price List';

    protected static ?string $title = 'Product Price List';

    protected static ?int $navigationSort = 10;

    public array $priceList = [];

    public ?string $generatedAt = null;

    public bool $isStale = false;

    public bool $showInactive = false;

    public bool $showCost = false;

    public bool $showWholesale = true;

    /** The unlisted public URL, or null until someone creates the link. */
    public ?string $publicUrl = null;

    // Neutral studio backdrop shown for products that have no photo yet. Kept as a
    // UI fallback rather than being written into products.image_url so that dropping
    // a real photo into images/productv2 is all it takes - no data cleanup after.
    public const PLACEHOLDER_IMAGE = '/images/product-placeholder.webp';

    // Cache file lives in storage/app/price-lists/latest.json
    // Read by App\Http\Controllers\PublicPriceListController too, so the
    // public price list and this page are always looking at the same file.
    public const CACHE_FILE = 'price-lists/latest.json';

    // Increment this whenever the item schema gains new required keys.
    // Any cached file without a matching version is discarded automatically.
    // 9: added the resolved 'image_src' key.
    // 10: image_src now goes through Product::resolveImageUrl() - a cache
    // written by v9 has raw storage-disk paths baked in as image_src, which
    // 404 as an <img src>. Bumping forces a regenerate with the fix.
    public const CACHE_VERSION = 10;

    // Re-generate only after this many hours (unless forced)
    private const CACHE_TTL_HOURS = 24;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyPermission([
            'view_inventory_reports',
            'export_reports',
            'view_products',
        ]);
    }

    public function mount(): void
    {
        $this->loadFromCache();
        $this->refreshPublicUrl();
    }

    /**
     * The public link is not created until someone asks for it, so an
     * organization that never shares a price list never gets a live URL.
     */
    private function refreshPublicUrl(): void
    {
        $token = $this->currentOrganization()?->price_list_public_token;

        $this->publicUrl = $token ? route('public.price-list', $token) : null;
    }

    private function currentOrganization(): ?\App\Models\Organization
    {
        return \App\Models\Organization::find(OrganizationContext::getCurrentOrganizationId());
    }

    public function createPublicLink(): void
    {
        $this->currentOrganization()?->ensurePriceListToken();
        $this->refreshPublicUrl();

        Notification::make()
            ->title('Shareable link ready')
            ->body('Anyone with this link can see retail prices - cost and wholesale are never included.')
            ->success()
            ->send();
    }

    public function rotatePublicLink(): void
    {
        $this->currentOrganization()?->rotatePriceListToken();
        $this->refreshPublicUrl();

        Notification::make()
            ->title('New link generated')
            ->body('The previous link no longer works. Share the new one.')
            ->success()
            ->send();
    }

    private function loadFromCache(): void
    {
        if (! Storage::exists(self::CACHE_FILE)) {
            return;
        }

        $data = json_decode(Storage::get(self::CACHE_FILE), true);

        // Discard any cache written by an older code version (missing keys).
        if (($data['version'] ?? 0) !== self::CACHE_VERSION) {
            Storage::delete(self::CACHE_FILE);

            return;
        }

        $this->generatedAt = $data['generated_at'] ?? null;
        $this->priceList = $data['price_list'] ?? [];

        if ($this->generatedAt) {
            $age = now()->diffInHours($this->generatedAt);
            $this->isStale = $age >= self::CACHE_TTL_HOURS;
        }
    }

    public function generate(): void
    {
        $orgId = OrganizationContext::getCurrentOrganizationId();
        $previousIndex = $this->buildPreviousRowIndex($this->priceList);

        $categories = Category::where('organization_id', $orgId)
            ->where('active', true)
            ->orderBy('name')
            ->with(['products' => function ($q) {
                $q->orderBy('name');
                if (! $this->showInactive) {
                    $q->where('active', true);
                }
                $q->with(['variants' => function ($q2) {
                    $q2->orderBy('pack_size');
                    if (! $this->showInactive) {
                        $q2->where('active', true);
                    }
                }]);
            }])
            ->get();

        $priceList = [];

        foreach ($categories as $category) {
            $items = [];

            foreach ($category->products as $product) {
                $displayVariants = collect($product->variants)
                    ->unique(fn ($variant) => $this->variantSizeKey($variant))
                    ->values();

                $nameParts = array_values(array_unique(array_filter([
                    $product->name_nepali,
                    $product->name_romanized,
                    $product->name_hindi,
                ])));

                $displayName = $product->name;
                if (! empty($nameParts)) {
                    $displayName .= ' ('.implode(' / ', $nameParts).')';
                }

                $variantMeta = $displayVariants
                    ->map(function ($variant) {
                        $grams = $this->toGrams((float) $variant->pack_size, (string) $variant->unit);
                        $mrpRaw = (float) ($variant->mrp_india ?? $variant->base_price ?? 0);
                        $mrp = $this->roundUpToNearestFive($mrpRaw);

                        return [
                            'id' => $variant->id,
                            'grams' => $grams,
                            'mrp' => $mrp,
                        ];
                    })
                    ->values();

                $oneGramMrp = $variantMeta
                    ->filter(fn ($v) => $v['grams'] !== null && $v['grams'] > 0)
                    ->sortBy('grams')
                    ->map(fn ($v) => $v['mrp'] / $v['grams'])
                    ->first();

                $has500g = $variantMeta->contains(fn ($v) => $v['grams'] === 500.0);
                $has1kg = $variantMeta->contains(fn ($v) => $v['grams'] === 1000.0);
                $isWeightProduct = ($product->unit_type ?? 'weight') === 'weight';
                $missingMandatoryPacks = [];

                if ($isWeightProduct) {
                    if (! $has500g) {
                        $missingMandatoryPacks[] = '500g';
                    }
                    if (! $has1kg) {
                        $missingMandatoryPacks[] = '1kg';
                    }
                }

                $imageSlug = Str::slug($product->name);
                $imageSrc = $this->resolveImageSrc($product->image_url, $imageSlug);

                foreach ($displayVariants as $variant) {
                    $mrpRaw = (float) ($variant->mrp_india ?? $variant->base_price ?? 0);
                    $mrp = $this->roundUpToNearestFive($mrpRaw);
                    $cost = (float) ($variant->cost_price ?? 0);
                    // Shared with the POS wholesale toggle so a dealer bill always
                    // matches the rate printed on this sheet.
                    $wholesale = PricingService::getWholesalePrice($variant) ?? 0.0;
                    $packGrams = $this->toGrams((float) $variant->pack_size, (string) $variant->unit);
                    $packCode = $this->packCode($packGrams);

                    $rowKey = $product->id.':'.$variant->id;
                    $previous = $previousIndex[$rowKey] ?? null;
                    $priceChanged = $previous
                        && ((float) $previous['mrp'] !== $mrp
                        || (float) $previous['wholesale'] !== $wholesale);

                    $items[] = [
                        'row_key' => $rowKey,
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'product_name' => $displayName,
                        'product_name_english' => $product->name,
                        'product_name_nepali' => $product->name_nepali,
                        'product_name_romanized' => $product->name_romanized,
                        'product_name_hindi' => $product->name_hindi,
                        'image_slug' => $imageSlug,
                        'image_url' => $product->image_url,
                        // Null when the product genuinely has no photo on disk yet -
                        // the view swaps in the placeholder and flags it.
                        'image_src' => $imageSrc,
                        'pack_size' => $variant->pack_label,
                        'pack_size_grams' => $packGrams,
                        'pack_code' => $packCode,
                        'pack_color_class' => $this->packColorClass($packCode),
                        'wholesale' => $wholesale,
                        'mrp' => $mrp,
                        'cost_price' => $cost,
                        'price_changed' => $priceChanged,
                        'one_gram_mrp' => $oneGramMrp ? $this->roundUpToInteger($oneGramMrp) : null,
                        'missing_mandatory_packs' => $missingMandatoryPacks,
                        'is_weight_product' => $isWeightProduct,
                    ];
                }
            }

            if (! empty($items)) {
                $priceList[] = [
                    'category' => $category->name,
                    'items' => $items,
                ];
            }
        }

        $this->priceList = $priceList;
        $this->generatedAt = now()->toDateTimeString();
        $this->isStale = false;

        Storage::makeDirectory('price-lists');
        Storage::put(self::CACHE_FILE, json_encode(['version' => self::CACHE_VERSION,            'generated_at' => $this->generatedAt,
            'price_list' => $priceList,
        ]));

        Notification::make()
            ->title('Price list generated successfully')
            ->body($this->getChangedPriceCount().' variants changed since last generation')
            ->success()
            ->send();
    }

    /**
     * Rendered server-side via dompdf instead of the browser's print-to-PDF.
     *
     * window.print() rasterizes every page as a bitmap at print resolution
     * and re-embeds each product photo at its full source size regardless of
     * how small it's displayed on screen — a 28-page catalogue with ~90
     * product photos came out around 200MB. dompdf lays out real text and
     * embeds each image's actual (already-compressed webp) bytes once, which
     * is the same difference a browser gets from "print" vs "print to PDF
     * via a proper PDF library" — no manual re-compression step needed
     * afterward.
     */
    public function downloadPdf(): \Symfony\Component\HttpFoundation\Response
    {
        if (empty($this->priceList)) {
            Notification::make()
                ->title('No price list available. Please generate first.')
                ->warning()
                ->send();

            return response()->noContent();
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.price-list-pdf', [
            'priceList' => $this->priceList,
            'generatedAt' => $this->generatedAt ?? now()->toDateTimeString(),
            'uniqueProductCount' => $this->getUniqueProductCount(),
            'variantCount' => $this->getTotalProducts(),
            'changedCount' => $this->getChangedPriceCount(),
        ])
            ->setPaper('a4')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        $filename = 'price-list-'.date('Y-m-d').'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename
        );
    }

    /**
     * A print-and-hand-to-the-counter sheet: one row per product at its
     * per-kilo reference price, no photos, big type. The full PDF above is
     * the online-viewing-equivalent full catalogue (every pack size, every
     * photo); this is the physical, compact alternative for the shop floor —
     * something a non-technical reader can scan without hunting through 28
     * pages for one number.
     */
    public function downloadCompactPdf(): \Symfony\Component\HttpFoundation\Response
    {
        if (empty($this->priceList)) {
            Notification::make()
                ->title('No price list available. Please generate first.')
                ->warning()
                ->send();

            return response()->noContent();
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.price-list-compact-pdf', [
            'priceList' => $this->priceList,
            'generatedAt' => $this->generatedAt ?? now()->toDateTimeString(),
        ])
            ->setPaper('a4')
            ->setOption('isHtml5ParserEnabled', true);

        $filename = 'shop-price-sheet-'.date('Y-m-d').'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename
        );
    }

    public function getGeneratedAtForHumans(): string
    {
        if (! $this->generatedAt) {
            return 'Never';
        }

        return \Carbon\Carbon::parse($this->generatedAt)->diffForHumans();
    }

    public function getTotalProducts(): int
    {
        return array_sum(array_map(fn ($g) => count($g['items']), $this->priceList));
    }

    public function getUniqueProductCount(): int
    {
        return collect($this->priceList)
            ->flatMap(fn ($group) => $group['items'])
            ->groupBy('product_id')
            ->count();
    }

    public function getProductsWithImageCount(): int
    {
        return collect($this->priceList)
            ->flatMap(fn ($group) => $group['items'])
            ->groupBy('product_id')
            ->filter(fn ($rows) => ! empty($rows->first()['image_url'] ?? null))
            ->count();
    }

    public function getImageCoveragePercent(): int
    {
        $totalProducts = $this->getUniqueProductCount();
        if ($totalProducts === 0) {
            return 0;
        }

        return (int) round(($this->getProductsWithImageCount() / $totalProducts) * 100);
    }

    public function getChangedPriceCount(): int
    {
        return collect($this->priceList)
            ->flatMap(fn ($group) => $group['items'])
            ->filter(fn ($item) => (bool) ($item['price_changed'] ?? false))
            ->count();
    }

    public function getMandatoryPackIssueCount(): int
    {
        return collect($this->priceList)
            ->flatMap(fn ($group) => $group['items'])
            ->groupBy('product_id')
            ->filter(function ($rows) {
                $missing = $rows->first()['missing_mandatory_packs'] ?? [];

                return ! empty($missing);
            })
            ->count();
    }

    /**
     * Usable image path for a product, or null when it has no photo yet.
     *
     * Prefers products.image_url (maintained by ProductImageSeeder), then falls
     * back to the pre-webp images/products/ drop. That folder uses mixed
     * extensions - Rishipeya.jpeg, ajwain-carom-seeds.webp - so probing each one
     * finds files a bare '.jpg' guess would miss. Resolved here, at generate
     * time, so rendering never has to hit the filesystem.
     */
    private function resolveImageSrc(?string $imageUrl, string $slug): ?string
    {
        if (filled($imageUrl)) {
            // image_url holds two different kinds of path depending on how the
            // photo got there (see Product::resolveImageUrl) - returning it raw
            // left every storage-disk path (everything products:sync-web-images
            // has written since it shipped) resolving relative to this page's
            // own URL instead of /storage, so the photo 404'd and silently fell
            // back to the placeholder below.
            return \App\Models\Product::resolveImageUrl($imageUrl);
        }

        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            if (is_file(public_path("images/products/{$slug}.{$ext}"))) {
                return "/images/products/{$slug}.{$ext}";
            }
        }

        return null;
    }

    private function buildPreviousRowIndex(array $priceList): array
    {
        $index = [];

        foreach ($priceList as $group) {
            foreach (($group['items'] ?? []) as $item) {
                $rowKey = $item['row_key'] ?? null;
                if ($rowKey) {
                    $index[$rowKey] = $item;
                }
            }
        }

        return $index;
    }

    private function toGrams(float $size, string $unit): ?float
    {
        $normalized = strtoupper(trim($unit));

        if (in_array($normalized, ['G', 'GM', 'GMS', 'GRAM', 'GRAMS'], true)) {
            return $size;
        }

        if (in_array($normalized, ['KG', 'KGS', 'KILOGRAM', 'KILOGRAMS'], true)) {
            return $size * 1000;
        }

        return null;
    }

    private function roundUpToNearestFive(float $value): float
    {
        if ($value <= 0) {
            return 0;
        }

        return (float) (ceil($value / 5) * 5);
    }

    private function roundUpToInteger(float $value): float
    {
        if ($value <= 0) {
            return 0;
        }

        return (float) ceil($value);
    }

    private function variantSizeKey($variant): string
    {
        $grams = $this->toGrams((float) $variant->pack_size, (string) $variant->unit);

        if ($grams !== null) {
            return 'GRAMS:'.number_format($grams, 3, '.', '');
        }

        $normalizedUnit = strtoupper(trim((string) $variant->unit));
        $size = number_format((float) $variant->pack_size, 3, '.', '');

        return $normalizedUnit.':'.$size;
    }

    private function packCode(?float $grams): string
    {
        if ($grams === null) {
            return 'other';
        }

        $key = (int) round($grams);

        if ($key > 0 && $key < 1000) {
            return $key.'g';
        }

        if ($key > 1000) {
            return rtrim(rtrim(number_format($key / 1000, 2, '.', ''), '0'), '.').'kg';
        }

        return match ($key) {
            1 => '1g',
            20 => '20g',
            50 => '50g',
            100 => '100g',
            200 => '200g',
            500 => '500g',
            1000 => '1kg',
            default => 'other',
        };
    }

    private function packColorClass(string $packCode): string
    {
        return match ($packCode) {
            '1g' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
            '20g' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200',
            '50g' => 'bg-lime-100 text-lime-800 dark:bg-lime-900/40 dark:text-lime-200',
            '100g' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-200',
            '200g' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
            '500g' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
            '1kg' => 'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-200',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        };
    }
}

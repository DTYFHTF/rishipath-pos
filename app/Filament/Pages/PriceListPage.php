<?php

namespace App\Filament\Pages;

use App\Exports\PriceListExport;
use App\Models\Category;
use App\Services\OrganizationContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

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

    // Cache file lives in storage/app/price-lists/latest.json
    private const CACHE_FILE = 'price-lists/latest.json';

    // Increment this whenever the item schema gains new required keys.
    // Any cached file without a matching version is discarded automatically.
    private const CACHE_VERSION = 6;

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
        $this->priceList   = $data['price_list'] ?? [];

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
                $q->where('active', true)->orderBy('name')
                    ->with(['variants' => function ($q2) {
                        $q2->where('active', true)->orderBy('pack_size');
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
                    $displayName .= ' (' . implode(' / ', $nameParts) . ')';
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

                foreach ($displayVariants as $variant) {
                    $mrpRaw = (float) ($variant->mrp_india ?? $variant->base_price ?? 0);
                    $mrp = $this->roundUpToNearestFive($mrpRaw);
                    $cost = (float) ($variant->cost_price ?? 0);
                    $wholesale = $this->roundUpToInteger($cost * 1.13);
                    $packGrams = $this->toGrams((float) $variant->pack_size, (string) $variant->unit);
                    $packCode = $this->packCode($packGrams);

                    $rowKey = $product->id . ':' . $variant->id;
                    $previous = $previousIndex[$rowKey] ?? null;
                    $priceChanged = $previous
                        && ((float) $previous['mrp'] !== $mrp
                        || (float) $previous['wholesale'] !== $wholesale);

                    $items[] = [
                        'row_key' => $rowKey,
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'product_name' => $displayName,
                        'image_slug' => Str::slug($product->name),
                        'image_url' => $product->image_url,
                        'pack_size' => $this->formatPackSize((float) $variant->pack_size, (string) $variant->unit),
                        'pack_size_grams' => $packGrams,
                        'pack_code' => $packCode,
                        'pack_color_class' => $this->packColorClass($packCode),
                        'wholesale' => $wholesale,
                        'mrp' => $mrp,
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
        Storage::put(self::CACHE_FILE, json_encode([            'version'      => self::CACHE_VERSION,            'generated_at' => $this->generatedAt,
            'price_list' => $priceList,
        ]));

        Notification::make()
            ->title('Price list generated successfully')
            ->body($this->getChangedPriceCount() . ' variants changed since last generation')
            ->success()
            ->send();
    }

    public function downloadExcel(): void
    {
        if (empty($this->priceList)) {
            Notification::make()
                ->title('No price list available. Please generate first.')
                ->warning()
                ->send();

            return;
        }

        $rows = [];
        $sn = 1;

        foreach ($this->priceList as $group) {
            foreach ($group['items'] as $item) {
                $rows[] = [
                    $sn++,
                    $group['category'],
                    $item['product_name'],
                    $item['pack_size'],
                    $item['wholesale'],
                    $item['mrp'],
                ];
            }
        }

        $filename = 'price-list-' . date('Y-m-d') . '.xlsx';
        $storagePath = 'price-lists/' . $filename;

        Excel::store(new PriceListExport($rows, $this->generatedAt ?? now()->toDateTimeString()), $storagePath);

        $this->dispatch('download-price-list', url: Storage::url($storagePath));
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

    private function formatPackSize(float $size, string $unit): string
    {
        $normalizedUnit = strtoupper(trim($unit));

        $displaySize = floor($size) == $size
            ? (string) (int) $size
            : number_format($size, 3, '.', '');

        $displayUnit = match ($normalizedUnit) {
            'GMS', 'GM', 'GRAM', 'GRAMS' => 'G',
            'KGS', 'KILOGRAM', 'KILOGRAMS' => 'KG',
            default => $normalizedUnit,
        };

        return $displaySize . ' ' . $displayUnit;
    }

    private function variantSizeKey($variant): string
    {
        $grams = $this->toGrams((float) $variant->pack_size, (string) $variant->unit);

        if ($grams !== null) {
            return 'GRAMS:' . number_format($grams, 3, '.', '');
        }

        $normalizedUnit = strtoupper(trim((string) $variant->unit));
        $size = number_format((float) $variant->pack_size, 3, '.', '');

        return $normalizedUnit . ':' . $size;
    }

    private function packCode(?float $grams): string
    {
        if ($grams === null) {
            return 'other';
        }

        $key = (int) round($grams);

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

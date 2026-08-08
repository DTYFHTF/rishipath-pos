<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OrganizationContext;
use App\Services\PackPricing;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Prices that no longer match what the cost says they should be.
 *
 * Spice costs move constantly, and a purchase received at a new rate updates
 * the variant's cost price without touching its selling price. Nothing used to
 * surface that, so prices quietly drifted away from their margin until someone
 * noticed by eye. This is the queue of those drifts.
 *
 * Every row is the same arithmetic the POS and the dealer price list use, so
 * accepting a suggestion here keeps all three in agreement.
 */
class PriceReview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static string $view = 'filament.pages.price-review';

    protected static ?string $navigationGroup = 'Products';

    protected static ?string $navigationLabel = 'Price Review';

    protected static ?string $title = 'Price Review';

    protected static ?int $navigationSort = 4;

    /** Ignore rounding-level differences — only real drift is worth a decision. */
    private const MATERIAL_GAP = 0.005;

    public string $filter = 'all';

    public string $search = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('edit_product_variants') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::pendingCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::pendingCount() > 0 ? 'warning' : null;
    }

    /** Cached per request — the badge and the page body both ask. */
    protected static ?int $pendingCache = null;

    protected static function pendingCount(): int
    {
        if (static::$pendingCache !== null) {
            return static::$pendingCache;
        }

        return static::$pendingCache = collect(static::buildRows())
            ->sum(fn (array $group) => count($group['rows']));
    }

    /**
     * Every variant whose shelf price differs from the derived price.
     *
     * @return array<int, array{product: Product, cost_per_kg: float, rows: array}>
     */
    protected static function buildRows(): array
    {
        $orgId = OrganizationContext::getCurrentOrganizationId()
            ?? auth()->user()?->organization_id;

        $products = Product::query()
            ->with(['variants' => fn ($q) => $q->where('active', true)])
            ->where('organization_id', $orgId)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $groups = [];

        foreach ($products as $product) {
            $costPerKg = PackPricing::costPerKg($product);

            if ($costPerKg === null) {
                continue;
            }

            // Pass the product's own markup (null for most), not the headline
            // rate — a concrete number here would be applied flat and flatten
            // away the small-pack tier.
            $explicitMarkup = PackPricing::explicitMarkupFor($product);
            $preview = PackPricing::previewProduct($product, $explicitMarkup, allowRises: true);

            $rows = [];

            foreach ($preview as $entry) {
                $derived = $entry['derived'];
                $current = $entry['current'];

                if ($derived === null || $entry['locked'] || $current <= 0) {
                    continue;
                }

                $gap = ($derived - $current) / $current;

                if (abs($gap) < self::MATERIAL_GAP) {
                    continue;
                }

                $rows[] = [
                    'variant_id' => $entry['variant']->id,
                    'pack' => $entry['variant']->pack_label,
                    'current' => $current,
                    'derived' => $derived,
                    'gap' => $gap,
                ];
            }

            if ($rows !== []) {
                $groups[] = [
                    'product' => $product,
                    'cost_per_kg' => $costPerKg,
                    // A product on the standard tiers has two markups, not
                    // one, so a single figure would be wrong for half its
                    // packs. Null here tells the view to say so.
                    'markup' => $explicitMarkup,
                    'rows' => $rows,
                ];
            }
        }

        return $groups;
    }

    /**
     * @return Collection<int, array>
     */
    public function getGroupsProperty(): Collection
    {
        $groups = collect(static::buildRows());

        if ($this->search !== '') {
            $needle = strtolower(trim($this->search));
            $groups = $groups->filter(
                fn ($g) => str_contains(strtolower($g['product']->name), $needle)
            );
        }

        if ($this->filter === 'up') {
            $groups = $groups->map(function ($g) {
                $g['rows'] = array_values(array_filter($g['rows'], fn ($r) => $r['gap'] > 0));

                return $g;
            })->filter(fn ($g) => $g['rows'] !== []);
        } elseif ($this->filter === 'down') {
            $groups = $groups->map(function ($g) {
                $g['rows'] = array_values(array_filter($g['rows'], fn ($r) => $r['gap'] < 0));

                return $g;
            })->filter(fn ($g) => $g['rows'] !== []);
        }

        return $groups->values();
    }

    public function getSummaryProperty(): array
    {
        $groups = collect(static::buildRows());
        $rows = $groups->flatMap(fn ($g) => $g['rows']);

        return [
            'products' => $groups->count(),
            'variants' => $rows->count(),
            'up' => $rows->filter(fn ($r) => $r['gap'] > 0)->count(),
            'down' => $rows->filter(fn ($r) => $r['gap'] < 0)->count(),
        ];
    }

    /** Accept every suggestion for one product. */
    public function applyProduct(int $productId): void
    {
        $group = collect(static::buildRows())
            ->first(fn ($g) => $g['product']->id === $productId);

        if (! $group) {
            return;
        }

        $applied = $this->writePrices($group['rows']);
        static::$pendingCache = null;

        Notification::make()
            ->success()
            ->title($group['product']->name.' repriced')
            ->body($applied.' pack '.str('price')->plural($applied).' updated to match cost.')
            ->send();
    }

    /** Accept a single suggestion. */
    public function applyVariant(int $variantId): void
    {
        foreach (static::buildRows() as $group) {
            foreach ($group['rows'] as $row) {
                if ($row['variant_id'] === $variantId) {
                    $this->writePrices([$row]);
                    static::$pendingCache = null;

                    Notification::make()
                        ->success()
                        ->title('Price updated')
                        ->body($group['product']->name.' '.$row['pack'].' is now ₹'.number_format($row['derived']))
                        ->send();

                    return;
                }
            }
        }
    }

    /**
     * @param  array<int, array>  $rows
     */
    protected function writePrices(array $rows): int
    {
        $applied = 0;

        foreach ($rows as $row) {
            $variant = ProductVariant::find($row['variant_id']);

            // Re-check the lock: it may have been set since the page rendered.
            if (! $variant || $variant->manual_price_locked) {
                continue;
            }

            $variant->forceFill([
                'selling_price_nepal' => $row['derived'],
                'base_price' => $row['derived'],
                'mrp_india' => $row['derived'],
            ])->saveQuietly();

            $applied++;
        }

        return $applied;
    }

    /** Keep this price as a deliberate exception instead of repricing it. */
    public function lockVariant(int $variantId): void
    {
        $variant = ProductVariant::find($variantId);

        if (! $variant) {
            return;
        }

        $variant->forceFill(['manual_price_locked' => true])->saveQuietly();
        static::$pendingCache = null;

        Notification::make()
            ->info()
            ->title('Price locked')
            ->body('This price is now a deliberate override and will be left alone.')
            ->send();
    }
}

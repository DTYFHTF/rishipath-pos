<?php

namespace App\Filament\Pages;

use App\Exports\PriceListExport;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Services\OrganizationContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
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

    // Re-generate only after this many hours (unless forced)
    private const CACHE_TTL_HOURS = 24;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasPermission('view_reports') || $user?->hasPermission('manage_products') || false;
    }

    public function mount(): void
    {
        $this->loadFromCache();
    }

    // ─── Load / Generate ──────────────────────────────────────────────────────

    private function loadFromCache(): void
    {
        if (Storage::exists(self::CACHE_FILE)) {
            $data = json_decode(Storage::get(self::CACHE_FILE), true);
            $this->generatedAt = $data['generated_at'] ?? null;
            $this->priceList   = $data['price_list']   ?? [];

            // Flag as stale if older than TTL
            if ($this->generatedAt) {
                $age            = now()->diffInHours($this->generatedAt);
                $this->isStale  = $age >= self::CACHE_TTL_HOURS;
            }
        }
    }

    public function generate(): void
    {
        $orgId = OrganizationContext::getCurrentOrganizationId();

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
                $bilingual = $product->name;

                if ($product->name_nepali || $product->name_hindi) {
                    $parts     = array_filter([$product->name_nepali, $product->name_hindi]);
                    $bilingual = $product->name . ' (' . implode(' / ', $parts) . ')';
                }

                foreach ($product->variants as $variant) {
                    $items[] = [
                        'product_name' => $bilingual,
                        'pack_size'    => $variant->pack_size . ' ' . $variant->unit,
                        'cost_price'   => $variant->cost_price,
                        'wholesale'    => round($variant->cost_price * 1.20, 2),
                        'mrp'          => $variant->mrp_india ?? $variant->base_price,
                    ];
                }
            }

            if (! empty($items)) {
                $priceList[] = [
                    'category' => $category->name,
                    'items'    => $items,
                ];
            }
        }

        $this->priceList   = $priceList;
        $this->generatedAt = now()->toDateTimeString();
        $this->isStale     = false;

        // Persist to cache
        Storage::makeDirectory('price-lists');
        Storage::put(self::CACHE_FILE, json_encode([
            'generated_at' => $this->generatedAt,
            'price_list'   => $priceList,
        ]));

        Notification::make()
            ->title('Price list generated successfully')
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
        $sn   = 1;

        foreach ($this->priceList as $group) {
            foreach ($group['items'] as $item) {
                $rows[] = [
                    $sn++,
                    $group['category'],
                    $item['product_name'],
                    $item['pack_size'],
                    $item['cost_price'],
                    $item['wholesale'],
                    $item['mrp'],
                ];
            }
        }

        $filename  = 'price-list-' . date('Y-m-d') . '.xlsx';
        $storagePath = 'price-lists/' . $filename;

        Excel::store(new PriceListExport($rows, $this->generatedAt ?? now()->toDateTimeString()), $storagePath);

        $this->dispatch('download-price-list', url: Storage::url($storagePath));
    }

    // ─── Helpers for view ─────────────────────────────────────────────────────

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
}

<?php

namespace App\Http\Controllers;

use App\Filament\Pages\PriceListPage;
use App\Models\Organization;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The public, unlisted view of an organization's price list - the link the
 * Price List admin page hands out. Reads the same cache file the admin page
 * generates rather than querying the catalogue itself, so this never needs
 * its own "regenerate" step and can never show a price the admin page hasn't
 * also shown.
 *
 * The one thing this controller exists to guarantee: cost_price, wholesale,
 * and every internal-only flag (price_changed, missing_mandatory_packs) never
 * reach this response. That happens once, here, via an explicit allow-list,
 * rather than being a rule every future caller of the cache has to remember.
 */
class PublicPriceListController extends Controller
{
    public function show(string $token): View
    {
        $organization = Organization::where('price_list_public_token', $token)
            ->where('active', true)
            ->firstOrFail();

        [$priceList, $generatedAt] = $this->readCache();

        return view('pages.public-price-list', [
            'organization' => $organization,
            'priceList' => $priceList,
            'generatedAt' => $generatedAt,
        ]);
    }

    /**
     * @return array{0: array<int, array{category: string, items: array}>, 1: ?string}
     */
    private function readCache(): array
    {
        if (! Storage::exists(PriceListPage::CACHE_FILE)) {
            return [[], null];
        }

        $cache = json_decode(Storage::get(PriceListPage::CACHE_FILE), true);

        // Mirrors PriceListPage::loadFromCache()'s own check. That method only
        // runs (and discards a stale file) when someone opens the admin page;
        // this controller reads the same file independently, so a version
        // mismatch has to be handled here too rather than assumed away.
        if (($cache['version'] ?? 0) !== PriceListPage::CACHE_VERSION) {
            return [[], null];
        }

        $priceList = [];

        foreach ($cache['price_list'] ?? [] as $group) {
            $items = array_values(array_map(
                fn (array $item) => $this->publicFields($item),
                $group['items'] ?? [],
            ));

            if ($items !== []) {
                $priceList[] = ['category' => $group['category'], 'items' => $items];
            }
        }

        return [$priceList, $cache['generated_at'] ?? null];
    }

    /**
     * The allow-list: only fields a customer should see leave this method.
     * A new key added to the admin cache does not appear here until named
     * explicitly - the safe direction for something cost/wholesale-adjacent.
     * image_src is trusted as-is: PriceListPage::generate() already resolved
     * it through Product::resolveImageUrl() into a browser-loadable URL.
     */
    private function publicFields(array $item): array
    {
        return [
            'product_name' => $item['product_name'],
            'product_name_english' => $item['product_name_english'],
            'image_src' => $item['image_src'] ?? null,
            'pack_size' => $item['pack_size'],
            'pack_size_grams' => $item['pack_size_grams'],
            'mrp' => $item['mrp'],
        ];
    }
}

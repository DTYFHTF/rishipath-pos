<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Change what a product costs per kilo, and let every pack follow.
 *
 * Cost used to be a per-variant field, so a rate change meant opening each
 * pack in turn and typing a number — six or seven edits per product, on 155
 * products — and then repeating the exercise on the selling prices, by hand,
 * with the arithmetic done in someone's head. In practice that meant the kilo
 * got updated when a purchase came in at a new rate and the 20 g pack did not,
 * which is how packs of the same product ended up disagreeing about what the
 * product cost (Bay Leaf's kilo says Rs300/kg while its 25 g pack says
 * Rs1,200/kg).
 *
 * One number in, whole product out: each pack's cost becomes its share of the
 * new rate, and its shelf price is re-derived through PackPricing so the tiers,
 * the packet fee, the staple hold and the manual locks all still apply.
 */
class CostRepricer
{
    /**
     * What setting this cost per kilo would do, without touching anything.
     *
     * @return array<int, array{
     *     variant: ProductVariant,
     *     pack: string,
     *     cost_now: float,
     *     cost_new: float,
     *     price_now: float,
     *     price_new: ?float,
     *     locked: bool,
     *     held: bool,
     * }>
     */
    public static function preview(Product $product, float $costPerKg): array
    {
        if ($costPerKg <= 0) {
            return [];
        }

        $derived = PackPricing::previewProduct(
            $product,
            PackPricing::explicitMarkupFor($product),
            allowRises: true,
            costPerKgOverride: $costPerKg,
        );

        $rows = [];

        foreach ($product->variants as $variant) {
            if (! $variant->active || ! ($variant->comparable_size > 0)) {
                continue;
            }

            $entry = $derived[$variant->id] ?? null;

            if ($entry === null) {
                continue;
            }

            $rows[] = [
                'variant' => $variant,
                'pack' => $variant->pack_label,
                'cost_now' => (float) $variant->cost_price,
                'cost_new' => self::costForPack($costPerKg, (float) $variant->comparable_size),
                'price_now' => $entry['current'],
                'price_new' => $entry['derived'],
                'locked' => $entry['locked'],
                'held' => $entry['capped'],
            ];
        }

        // Largest pack first — that is the one the admin knows the rate for,
        // so it reads as "the kilo is Rs320, and here is what that means".
        usort($rows, fn ($a, $b) => $b['variant']->comparable_size <=> $a['variant']->comparable_size);

        return $rows;
    }

    /**
     * Write the new cost and the prices it implies.
     *
     * Costs are written even for a pack whose price is locked or held: a lock
     * is a decision about the SELLING price, and refusing to record what the
     * pack actually costs would leave the margin reports wrong and hide the
     * loss from Price Review.
     *
     * @return array{costs: int, prices: int}
     */
    public static function apply(Product $product, float $costPerKg): array
    {
        $rows = self::preview($product, $costPerKg);

        $costs = 0;
        $prices = 0;

        foreach ($rows as $row) {
            /** @var ProductVariant $variant */
            $variant = $row['variant'];
            $changes = [];

            if (abs($row['cost_new'] - $row['cost_now']) >= 0.005) {
                $changes['cost_price'] = $row['cost_new'];
                $costs++;
            }

            $newPrice = $row['price_new'];

            if ($newPrice !== null && ! $row['locked'] && abs($newPrice - $row['price_now']) >= 0.005) {
                $changes['selling_price_nepal'] = $newPrice;
                $changes['base_price'] = $newPrice;
                $changes['mrp_india'] = $newPrice;
                $prices++;
            }

            if ($changes !== []) {
                // saveQuietly: an observer recalculating prices from the cost
                // we just wrote would fight the values in the same array.
                $variant->forceFill($changes)->saveQuietly();
            }
        }

        return ['costs' => $costs, 'prices' => $prices];
    }

    /** A pack's share of the per-kilo rate, to the paisa. */
    public static function costForPack(float $costPerKg, float $packGrams): float
    {
        return round($costPerKg * ($packGrams / PackPricing::REFERENCE_GRAMS), 2);
    }
}

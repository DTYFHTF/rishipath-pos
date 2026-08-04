<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Derives every pack price from the product's 1 kg price.
 *
 * The rule, in the words a shopkeeper can repeat:
 *
 *   Take the share of the kilo price, add Rs5 for the packet — or the value of
 *   the goods if that is less than Rs5 — then round up to the next Rs5.
 *
 * So for a Rs400/kg item: 500 g = 200 + 5 = Rs205, 100 g = 40 + 5 = Rs45,
 * 20 g = 8 + 5 = Rs15 (rounded up from 13).
 *
 * Why it replaced the old scheme: prices used to come from stepped markup
 * tiers applied to cost (65% under 50 g, 25% at 1 kg, …). Because the steps
 * were per-pack and cost varied per product, the same pack size carried
 * anywhere from a 1.00x to a 2.83x per-gram premium depending on the product —
 * the kilo price told you nothing about the 100 g price. Worse, the smallest
 * packs carried the highest markup (median 159% over cost at 20 g), so the
 * customers buying 20 g packets paid the most per gram.
 *
 * Capping the fee at the value of the goods matters: without it a Rs5 fee on a
 * 20 g packet of gud (about Rs1.25 of product) rounds the price from Rs5 to
 * Rs10 — doubling the cheapest staple in the catalogue.
 */
class PackPricing
{
    /**
     * Retail markup over cost, applied once at the 1 kg reference pack.
     *
     * Every other pack inherits it, so this single number sets the margin on
     * the whole catalogue. Blends carry their own higher markup for the
     * processing work — see BLEND_MARKUP.
     */
    public const RETAIL_MARKUP = 1.30;

    /** Blended products cover the processing labour on top of ingredients. */
    public const BLEND_MARKUP = 1.43;

    /** Flat packet charge added to every pack below 1 kg. */
    public const PACKING_FEE = 5.0;

    /**
     * Packs at or below this price are never raised by a repricing sweep.
     *
     * On a Rs5 packet of gud or salt the Rs5-rounding step is the entire
     * price, so the formula would double it. These are the staples the
     * lowest-income customers buy, and the point of the exercise is to stop
     * the smallest packs costing the most per gram — not to reverse it.
     */
    public const PROTECT_AT_OR_BELOW = 20.0;

    /** All shelf prices are a multiple of this. */
    public const PRICE_STEP = 5.0;

    /** Reference pack: the price every other pack is derived from. */
    public const REFERENCE_GRAMS = 1000.0;

    /**
     * Shelf price for a pack, given the product's 1 kg price.
     *
     * @param  float  $kilogramPrice  MRP of the 1 kg pack
     * @param  float  $packGrams  size of the pack being priced
     */
    public static function packPrice(float $kilogramPrice, float $packGrams): ?float
    {
        if ($kilogramPrice <= 0 || $packGrams <= 0) {
            return null;
        }

        $goods = $kilogramPrice * ($packGrams / self::REFERENCE_GRAMS);

        // The 1 kg pack is the reference and carries no packet charge.
        $fee = $packGrams < self::REFERENCE_GRAMS
            ? min(self::PACKING_FEE, $goods)
            : 0.0;

        return self::roundToStep($goods + $fee);
    }

    /**
     * The markup a product is priced at.
     *
     * Read from the product itself so a rename cannot silently change a price;
     * products with nothing set sit on the standard retail markup.
     */
    public static function markupFor(Product $product): float
    {
        $markup = $product->retail_markup !== null ? (float) $product->retail_markup : 0.0;

        return $markup > 0 ? $markup : self::RETAIL_MARKUP;
    }

    /**
     * The 1 kg shelf price for a product, from its cost per kilo.
     *
     * This is the anchor: change the markup here and the whole catalogue moves
     * together, instead of 483 hand-entered prices drifting apart.
     */
    public static function kilogramPrice(float $costPerKg, float $markup = self::RETAIL_MARKUP): ?float
    {
        if ($costPerKg <= 0) {
            return null;
        }

        return self::roundToStep($costPerKg * $markup);
    }

    /**
     * Cost per kilo for a product, taken from any pack that can be converted.
     *
     * Cost is already stored per pack and is consistent per gram across a
     * product's variants, so any convertible pack gives the same answer; the
     * largest is used because it carries the least rounding error.
     */
    public static function costPerKg(Product $product): ?float
    {
        $variant = $product->variants
            ->filter(fn (ProductVariant $v) => $v->comparable_size > 0 && (float) $v->cost_price > 0)
            ->sortByDesc(fn (ProductVariant $v) => $v->comparable_size)
            ->first();

        if (! $variant) {
            return null;
        }

        return (float) $variant->cost_price / ($variant->comparable_size / self::REFERENCE_GRAMS);
    }

    /**
     * Round up to the next sellable price point, never below one step.
     */
    public static function roundToStep(float $value): float
    {
        return max(self::PRICE_STEP, ceil($value / self::PRICE_STEP) * self::PRICE_STEP);
    }

    /**
     * The 1 kg variant a product's prices derive from.
     */
    public static function referenceVariant(Product $product): ?ProductVariant
    {
        return $product->variants
            ->first(fn (ProductVariant $v) => $v->comparable_size !== null
                && abs($v->comparable_size - self::REFERENCE_GRAMS) < 0.01);
    }

    /**
     * Price a single variant from its product's 1 kg pack.
     *
     * Returns null when the product has no 1 kg pack to anchor to, or when the
     * variant is measured in units we cannot convert (pcs, packets).
     */
    public static function priceFor(ProductVariant $variant, ?Product $product = null): ?float
    {
        $product = $product ?? $variant->product;

        if (! $product) {
            return null;
        }

        $reference = self::referenceVariant($product);

        if (! $reference || $variant->comparable_size === null) {
            return null;
        }

        $kilogramPrice = (float) ($reference->selling_price_nepal ?? $reference->base_price ?? 0);

        return self::packPrice($kilogramPrice, $variant->comparable_size);
    }

    /**
     * Derived prices for a whole product, keyed by variant id.
     *
     * The 1 kg anchor is recomputed from cost x markup, then every pack is
     * derived from it. Variants with manual_price_locked keep their current
     * price — an override is a deliberate decision and must survive a
     * recalculation.
     *
     * @param  bool  $allowRises  when false, a derived price above today's is
     *                            discarded in favour of the cheaper current price
     * @return array<int, array{variant: ProductVariant, current: float, derived: ?float, locked: bool, capped: bool}>
     */
    public static function previewProduct(Product $product, ?float $markup = null, bool $allowRises = false): array
    {
        $markup = $markup ?? self::markupFor($product);
        $costPerKg = self::costPerKg($product);
        $kilogramPrice = $costPerKg !== null ? self::kilogramPrice($costPerKg, $markup) : null;

        $out = [];

        foreach ($product->variants as $variant) {
            $current = (float) ($variant->selling_price_nepal ?? $variant->base_price ?? 0);

            $derived = ($kilogramPrice !== null && $variant->comparable_size !== null)
                ? self::packPrice($kilogramPrice, $variant->comparable_size)
                : null;

            $capped = false;

            $wouldRise = $derived !== null && $current > 0 && $derived > $current;

            if ($variant->manual_price_locked) {
                $derived = $current;
            } elseif ($wouldRise && ! $allowRises) {
                $derived = $current;
                $capped = true;
            } elseif ($wouldRise && $current <= self::PROTECT_AT_OR_BELOW) {
                // Cheap staples are held even when rises are allowed.
                $derived = $current;
                $capped = true;
            }

            $out[$variant->id] = [
                'variant' => $variant,
                'current' => $current,
                'derived' => $derived,
                'locked' => (bool) $variant->manual_price_locked,
                'capped' => $capped,
            ];
        }

        return $out;
    }
}

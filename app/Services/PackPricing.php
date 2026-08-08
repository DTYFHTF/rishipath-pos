<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Derives every pack price from the product's cost per kilo.
 *
 * The rule, in the words a shopkeeper can repeat:
 *
 *   Take the pack's share of the cost, add 25% on half-kilo-and-up or 30%
 *   below that, add Rs5 for the packet — or the value of the goods if that is
 *   less than Rs5 — then round up to the next Rs5.
 *
 * So for a Rs320/kg item: 1 kg = 320 x 1.25 = Rs400, 500 g = 200 + 5 = Rs205,
 * 100 g = 41.60 + 5 = Rs50, 20 g = 8.32 + 5 = Rs15.
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
     * Retail markup for packs of half a kilo and up.
     *
     * Bulk buyers are the most price-sensitive and the most likely to compare
     * against a wholesaler, so the larger packs carry the leaner margin.
     */
    public const RETAIL_MARKUP_BULK = 1.25;

    /**
     * Retail markup for packs under half a kilo.
     *
     * The extra five points covers the handling a small packet needs that a
     * kilo bag does not; the flat PACKING_FEE covers the packet itself.
     */
    public const RETAIL_MARKUP_SMALL = 1.30;

    /** Packs at or above this size use the leaner bulk markup. */
    public const BULK_THRESHOLD_GRAMS = 500.0;

    /**
     * Headline markup, used where a single number has to stand for the
     * product (the kilo rate). Pricing itself always goes through
     * markupForPack() so the tier is applied per pack.
     */
    public const RETAIL_MARKUP = self::RETAIL_MARKUP_BULK;

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
     * Shelf price for a pack, given an already-marked-up kilo price.
     *
     * Applies NO size tier — it cannot, because by this point the markup is
     * already baked into $kilogramPrice and is no longer visible. Correct only
     * where the markup really is flat across pack sizes: a blend or premium
     * line priced to a deliberate Rs/kg target. For an ordinary product use
     * packPriceFromCost(), which knows the tier.
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
     * A product's own markup, or null when it sits on the standard tiers.
     *
     * Read from the product itself so a rename cannot silently change a price.
     * Null is meaningful: it is what tells the pricing functions to apply the
     * size tiers rather than one flat number.
     */
    public static function explicitMarkupFor(Product $product): ?float
    {
        $markup = $product->retail_markup !== null ? (float) $product->retail_markup : 0.0;

        return $markup > 0 ? $markup : null;
    }

    /**
     * Headline markup for a product — the rate its kilo pack is priced at.
     *
     * For display only. Pricing goes through markupForPack(), which applies
     * the size tier; using this to price would flatten the tier away.
     */
    public static function markupFor(Product $product): float
    {
        return self::explicitMarkupFor($product) ?? self::RETAIL_MARKUP_BULK;
    }

    /**
     * The markup a given pack size is priced at.
     *
     * An explicit per-product markup wins for every pack: it exists to hit a
     * deliberate Rs/kg target (a blend, a premium line), and applying a size
     * tier on top would push the product past the number it was set to.
     */
    public static function markupForPack(float $packGrams, ?float $explicitMarkup = null): float
    {
        if ($explicitMarkup !== null && $explicitMarkup > 0) {
            return $explicitMarkup;
        }

        return $packGrams >= self::BULK_THRESHOLD_GRAMS
            ? self::RETAIL_MARKUP_BULK
            : self::RETAIL_MARKUP_SMALL;
    }

    /**
     * Shelf price for a pack, from the product's cost per kilo.
     *
     * This is the real entry point now that markup depends on pack size — a
     * 100 g pack is no longer a plain fraction of the kilo price, because the
     * two sit on different tiers.
     */
    public static function packPriceFromCost(float $costPerKg, float $packGrams, ?float $explicitMarkup = null): ?float
    {
        if ($costPerKg <= 0 || $packGrams <= 0) {
            return null;
        }

        $markup = self::markupForPack($packGrams, $explicitMarkup);
        $goods = $costPerKg * ($packGrams / self::REFERENCE_GRAMS) * $markup;

        // The 1 kg pack is the reference and carries no packet charge.
        $fee = $packGrams < self::REFERENCE_GRAMS
            ? min(self::PACKING_FEE, $goods)
            : 0.0;

        return self::roundToStep($goods + $fee);
    }

    /**
     * The 1 kg shelf price for a product, from its cost per kilo.
     *
     * This is the anchor: change the markup here and the whole catalogue moves
     * together, instead of 483 hand-entered prices drifting apart.
     */
    public static function kilogramPrice(float $costPerKg, ?float $markup = null): ?float
    {
        return self::packPriceFromCost($costPerKg, self::REFERENCE_GRAMS, $markup);
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
     * Derived prices for a whole product, keyed by variant id.
     *
     * Each pack is priced from the product's cost per kilo at its own size
     * tier. Variants with manual_price_locked keep their current price — an
     * override is a deliberate decision and must survive a recalculation.
     *
     * @param  ?float  $markup  an explicit markup to apply flat across every
     *                          pack; null reads the product's own, and falls
     *                          back to the size tiers when it has none
     * @param  bool  $allowRises  when false, a derived price above today's is
     *                            discarded in favour of the cheaper current price
     * @return array<int, array{variant: ProductVariant, current: float, derived: ?float, locked: bool, capped: bool}>
     */
    public static function previewProduct(Product $product, ?float $markup = null, bool $allowRises = false): array
    {
        $explicitMarkup = $markup ?? self::explicitMarkupFor($product);
        $costPerKg = self::costPerKg($product);

        $out = [];

        foreach ($product->variants as $variant) {
            $current = (float) ($variant->selling_price_nepal ?? $variant->base_price ?? 0);

            $derived = ($costPerKg !== null && $variant->comparable_size !== null)
                ? self::packPriceFromCost($costPerKg, $variant->comparable_size, $explicitMarkup)
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

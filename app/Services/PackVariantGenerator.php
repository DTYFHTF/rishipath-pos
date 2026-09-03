<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Creates a product's pack variants in one go from a cost per kilo.
 *
 * Adding a product by hand meant creating six near-identical variants one at a
 * time — the same four fields, six times, for every new product. Nothing about
 * those six rows is a judgement call: the pack sizes come from a standard set,
 * the cost of a pack is its share of the kilo cost, and ProductVariant's own
 * hooks already derive the SKU and every price from there. So the whole job is
 * "which packs, and what does a kilo cost".
 *
 * This mirrors what ProductCatalogSeeder does for the seeded catalogue, so a
 * product added in the admin ends up indistinguishable from a seeded one.
 */
class PackVariantGenerator
{
    /**
     * The pack sizes (in grams) ticked by default when generating variants.
     * 20/50/100/200/500/1000 is what most of the catalogue is built on; 250
     * is included too since it comes up often enough to default on rather
     * than ask for every time.
     */
    public const STANDARD_PACKS = [20, 50, 100, 200, 250, 500, 1000];

    /** Every pack size offered in the admin, including the less common ones. */
    public const AVAILABLE_PACKS = [10, 20, 25, 50, 100, 200, 250, 500, 1000];

    /**
     * @param  list<int>  $packGrams
     * @return array{created: int, reactivated: int, skipped: int}
     */
    public static function generate(Product $product, array $packGrams, float $costPerKg): array
    {
        $result = ['created' => 0, 'reactivated' => 0, 'skipped' => 0];

        foreach (array_unique($packGrams) as $grams) {
            $grams = (int) $grams;

            if ($grams <= 0) {
                continue;
            }

            [$packSize, $unit] = self::packSizeAndUnit($grams);

            // Match on the pack itself rather than a generated SKU string: the
            // SKU is derived from the product's own SKU, which changes when a
            // product is renamed, and matching on it would create a duplicate
            // 100g variant instead of finding the existing one.
            $variant = ProductVariant::firstOrNew([
                'product_id' => $product->id,
                'pack_size' => $packSize,
                'unit' => $unit,
            ]);

            if ($variant->exists) {
                if ($variant->active) {
                    // Leave a live variant alone — its price may have been
                    // negotiated or locked, and this is not a repricing tool.
                    $result['skipped']++;

                    continue;
                }

                $variant->active = true;
                $variant->save();
                $result['reactivated']++;

                continue;
            }

            $variant->cost_price = self::costForPack($costPerKg, $grams);
            $variant->active = true;
            // SKU and all three prices are filled by ProductVariant::booted().
            $variant->save();
            $result['created']++;
        }

        if ($result['created'] > 0 || $result['reactivated'] > 0) {
            $product->forceFill(['has_variants' => true])->saveQuietly();
        }

        return $result;
    }

    /**
     * A kilo is stored as 1 KG, never as 1000 GMS. Both spellings exist in the
     * live data (7 variants use 1000 GMS) and they do not compare equal, so a
     * product with the GMS spelling shows two "1kg" packs in the POS.
     *
     * @return array{0: float, 1: string}
     */
    public static function packSizeAndUnit(int $grams): array
    {
        return $grams === 1000 ? [1.000, 'KG'] : [(float) $grams, 'GMS'];
    }

    /** The pack's share of the kilo cost, to the paisa. */
    public static function costForPack(float $costPerKg, int $grams): float
    {
        return round($costPerKg * $grams / 1000, 2);
    }

    /** "20 g" / "1 kg" — how a pack is labelled in the admin. */
    public static function label(int $grams): string
    {
        return $grams === 1000 ? '1 kg' : "{$grams} g";
    }

    /** @return array<int, string> */
    public static function options(): array
    {
        return array_combine(
            self::AVAILABLE_PACKS,
            array_map(self::label(...), self::AVAILABLE_PACKS),
        );
    }
}

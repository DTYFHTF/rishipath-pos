<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PricingService;
use Illuminate\Database\Seeder;

class MandatoryPackVariantSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()
            ->where('active', true)
            ->where('unit_type', 'weight')
            ->with(['variants' => function ($query) {
                $query->where('active', true);
            }])
            ->get();

        $created = 0;

        foreach ($products as $product) {
            $variants = $product->variants;

            if ($variants->isEmpty()) {
                continue;
            }

            $variantMeta = $variants->map(function (ProductVariant $variant) {
                $grams = $this->toGrams((float) $variant->pack_size, (string) $variant->unit);

                return [
                    'variant' => $variant,
                    'grams' => $grams,
                ];
            })->filter(fn ($row) => $row['grams'] !== null && $row['grams'] > 0)->values();

            if ($variantMeta->isEmpty()) {
                continue;
            }

            $has500g = $variantMeta->contains(fn ($row) => abs($row['grams'] - 500.0) < 0.001);
            $has1kg = $variantMeta->contains(fn ($row) => abs($row['grams'] - 1000.0) < 0.001);

            if (! $has500g) {
                $created += $this->createMissingVariant($product, $variantMeta, 500.0, 500, 'GMS');
            }

            if (! $has1kg) {
                $created += $this->createMissingVariant($product, $variantMeta, 1000.0, 1, 'KG');
            }
        }

        $this->command?->info("MandatoryPackVariantSeeder complete. Created {$created} variant(s).");
    }

    private function createMissingVariant(Product $product, $variantMeta, float $targetGrams, float|int $packSize, string $unit): int
    {
        $existing = $product->variants()
            ->where('pack_size', $packSize)
            ->where('unit', $unit)
            ->exists();

        if ($existing) {
            return 0;
        }

        $referenceRow = $variantMeta
            ->sortBy(fn ($row) => abs($row['grams'] - $targetGrams))
            ->first();

        if (! $referenceRow) {
            return 0;
        }

        /** @var ProductVariant $reference */
        $reference = $referenceRow['variant'];
        $referenceGrams = (float) $referenceRow['grams'];

        if ($referenceGrams <= 0) {
            return 0;
        }

        $costPrice = $this->scalePrice($reference->cost_price, $referenceGrams, $targetGrams);

        if ($costPrice === null) {
            return 0;
        }

        $suggestedPrices = PricingService::suggestVariantPrices($costPrice, $packSize, $unit);

        $sku = $this->generateUniqueSku($product, $packSize, $unit);

        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku,
            'pack_size' => $packSize,
            'unit' => $unit,
            'base_price' => $suggestedPrices['base_price'],
            'mrp_india' => $suggestedPrices['mrp_india'],
            'selling_price_nepal' => $suggestedPrices['selling_price_nepal'],
            'cost_price' => $costPrice,
            'hsn_code' => $reference->hsn_code,
            'weight' => round($targetGrams / 1000, 3),
            'active' => true,
        ]);

        $this->command?->line("  Added {$product->name} {$packSize}{$unit} ({$sku})");

        return 1;
    }

    private function scalePrice($sourcePrice, float $sourceGrams, float $targetGrams): ?float
    {
        if ($sourcePrice === null) {
            return null;
        }

        $pricePerGram = ((float) $sourcePrice) / $sourceGrams;

        return round($pricePerGram * $targetGrams, 2);
    }

    private function generateUniqueSku(Product $product, float|int $packSize, string $unit): string
    {
        $base = strtoupper(($product->sku ?? 'PROD') . '-' . $packSize . $unit);
        $candidate = $base;
        $suffix = 1;

        while (ProductVariant::query()->where('sku', $candidate)->exists()) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
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
}

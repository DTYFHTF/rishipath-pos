<?php

namespace Database\Seeders;

use App\Models\ProductVariant;
use App\Services\PricingService;
use Illuminate\Database\Seeder;

class SyncWeightVariantPricingSeeder extends Seeder
{
    public function run(): void
    {
        $variants = ProductVariant::query()
            ->whereHas('product', fn ($query) => $query
                ->where('active', true)
                ->where('unit_type', 'weight'))
            ->where('active', true)
            ->whereNotNull('cost_price')
            ->get();

        $updated = 0;

        foreach ($variants as $variant) {
            $suggested = PricingService::suggestVariantPrices(
                (float) $variant->cost_price,
                (float) $variant->pack_size,
                (string) $variant->unit,
            );

            $variant->forceFill([
                'base_price' => $suggested['base_price'],
                'mrp_india' => $suggested['mrp_india'],
                'selling_price_nepal' => $suggested['selling_price_nepal'],
            ])->save();

            $updated++;
        }

        $this->command?->info("SyncWeightVariantPricingSeeder complete. Updated {$updated} variant(s).");
    }
}

<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseItemFactory extends Factory
{
    public function definition(): array
    {
        $qtyOrdered = fake()->numberBetween(10, 200);
        $unitCost = fake()->randomFloat(2, 10, 300);
        $taxRate = fake()->randomElement([0, 5, 12, 18]);
        $discount = fake()->optional()->randomFloat(2, 0, $unitCost * $qtyOrdered * 0.1);
        
        $lineTotal = ($qtyOrdered * $unitCost) + (($taxRate / 100) * $qtyOrdered * $unitCost) - ($discount ?? 0);

        return [
            'purchase_id' => Purchase::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'product_name' => fake()->words(3, true),
            'product_sku' => fake()->bothify('SKU-#####'),
            'quantity_ordered' => $qtyOrdered,
            'quantity_received' => 0,
            'unit_cost' => $unitCost,
            'tax_rate' => $taxRate,
            'discount_amount' => $discount ?? 0,
            'unit' => fake()->randomElement(['pcs', 'box', 'pack']),
            'expiry_date' => fake()->optional()->dateTimeBetween('+1 year', '+3 years'),
            'batch_id' => null,
            'notes' => fake()->optional()->sentence(),
            'tax_amount' => round((($taxRate / 100) * $qtyOrdered * $unitCost), 2),
            'line_total' => round($lineTotal, 2),
        ];
    }
}

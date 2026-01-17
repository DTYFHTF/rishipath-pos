<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        $packSizes = [10, 20, 30, 50, 60, 100, 200, 250, 500];
        $units = ['tab', 'cap', 'ml', 'gm'];
        
        $packSize = fake()->randomElement($packSizes);
        $unit = fake()->randomElement($units);
        
        $basePrice = fake()->randomFloat(2, 50, 500);
        
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->bothify('???####')),
            'barcode' => fake()->optional()->ean13(),
            'pack_size' => $packSize,
            'unit' => $unit,
            'base_price' => $basePrice,
            'cost_price' => $basePrice * 0.6,
            'selling_price_nepal' => $basePrice,
            'mrp_india' => $basePrice * 1.2,
            'active' => true,
        ];
    }
}

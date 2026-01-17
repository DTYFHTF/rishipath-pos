<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'store_id' => Store::factory(),
            'supplier_id' => Supplier::factory(),
            'purchase_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'expected_delivery_date' => fake()->optional()->dateTimeBetween('now', '+7 days'),
            'supplier_invoice_number' => fake()->optional()->numerify('INV-#####'),
            'status' => fake()->randomElement(['draft', 'ordered', 'received']),
            'total' => 0, // Calculated from items
            'tax_amount' => 0,
            'discount_amount' => 0,
            'shipping_cost' => fake()->randomFloat(2, 0, 500),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

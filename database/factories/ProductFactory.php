<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $ayurvedic = ['Ashwagandha', 'Brahmi', 'Triphala', 'Turmeric', 'Neem', 'Tulsi', 'Amla', 'Shatavari'];
        $forms = ['Tablet', 'Capsule', 'Powder', 'Syrup', 'Oil', 'Churna'];
        
        return [
            'organization_id' => Organization::factory(),
            'category_id' => null,
            'sku' => strtoupper(fake()->bothify('PRD-???####')),
            'name' => fake()->randomElement($ayurvedic) . ' ' . fake()->randomElement($forms),
            'name_nepali' => null,
            'name_hindi' => null,
            'name_sanskrit' => null,
            'description' => fake()->sentence(12),
            'product_type' => fake()->randomElement(['ayurvedic', 'herbal', 'supplement']),
            'unit_type' => fake()->randomElement(['weight', 'volume', 'piece']),
            'has_variants' => true,
            'tax_category' => 'standard',
            'requires_batch' => true,
            'requires_expiry' => true,
            'shelf_life_months' => fake()->optional()->numberBetween(12, 60),
            'is_prescription_required' => fake()->boolean(10),
            'ingredients' => null,
            'usage_instructions' => fake()->optional()->sentence(),
            'image_url' => null,
            'active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'supplier_code' => fake()->unique()->bothify('SUP-####'),
            'name' => fake()->company(),
            'contact_person' => fake()->optional()->name(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->address(),
            'city' => fake()->optional()->city(),
            'state' => fake()->optional()->state(),
            'country_code' => 'NP',
            'tax_number' => fake()->optional()->numerify('TAX########'),
            'notes' => fake()->optional()->sentence(),
            'active' => true,
        ];
    }
}

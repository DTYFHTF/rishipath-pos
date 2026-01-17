<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement(['Main Store', 'Branch A', 'Branch B', 'Warehouse']),
            'code' => strtoupper(fake()->lexify('???')),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country_code' => 'NP',
            'postal_code' => fake()->postcode(),
            'active' => true,
        ];
    }
}

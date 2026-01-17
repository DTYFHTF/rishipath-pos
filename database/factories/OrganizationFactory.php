<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(),
            'name' => fake()->company(),
            'legal_name' => fake()->optional()->company(),
            'country_code' => 'NP',
            'currency' => 'NPR',
            'timezone' => 'Asia/Kathmandu',
            'locale' => 'en',
            'config' => [
                'branding' => [
                    'logo_url' => null,
                    'primary_color' => '#10b981',
                ],
                'features' => [
                    'offline_mode' => true,
                    'multi_currency' => false,
                ],
            ],
            'active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $dob = $this->faker->dateTimeBetween('-70 years', '-18 years');

        return [
            'organization_id' => 1,
            'name' => $this->faker->name(),
            'phone' => $this->faker->unique()->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'date_of_birth' => $dob->format('Y-m-d'),
            'birthday' => $dob->format('Y-m-d'),
            'total_purchases' => 0,
            'total_spent' => 0,
            'loyalty_points' => 0,
            'loyalty_tier_id' => null,
            'last_birthday_bonus_at' => null,
            'loyalty_enrolled_at' => null,
            'notes' => null,
            'active' => true,
        ];
    }
}

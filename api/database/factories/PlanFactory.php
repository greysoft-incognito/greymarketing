<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->word());

        return [
            'name' => $name,
            'slug' => str($name)->slug() . '-' . $this->faker->unique()->numberBetween(1, 99999),
            'price' => 5000,
            'currency' => 'NGN',
            'interval' => 'month',
            'contacts_limit' => 1000,
            'emails_limit' => 1000,
            'sms_limit' => 1000,
        ];
    }

    public function free(): static
    {
        return $this->state(['price' => 0]);
    }
}

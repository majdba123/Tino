<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'slug' => $this->faker->slug,
            'description' => $this->faker->sentence,
            'price' => $this->faker->randomFloat(2, 10, 500),
            'calls_per_month' => $this->faker->numberBetween(1, 10),
            'visits_per_month' => $this->faker->numberBetween(0, 5),
            'visit_discount' => $this->faker->numberBetween(0, 50),
            'is_active' => $this->faker->boolean(90)
        ];
    }
}

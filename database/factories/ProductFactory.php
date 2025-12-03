<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(), 
            'price' => $this->faker->randomFloat(2, 10, 500),
            'stock_level' => $this->faker->numberBetween(1, 200),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Hold;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hold_id' => Hold::factory(),
            'product_id' => Product::factory(),
            'qty' => $this->faker->numberBetween(1, 10),
            'total_price' => 99.99, 
            'status' => 'pending_payment',
        ];
    }

    public function paid()
    {
        return $this->state(fn() => ['status' => 'paid']);
    }

    public function cancelled()
    {
        return $this->state(fn() => ['status' => 'cancelled']);
    }
}

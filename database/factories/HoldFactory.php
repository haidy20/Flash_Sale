<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class HoldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'qty' => $this->faker->numberBetween(1, 10),
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes(5),
        ];
    }

    public function expired()
    {
        return $this->state(fn() => [
            'status' => 'expired',
            'expires_at' => Carbon::now()->subMinutes(5),
        ]);
    }

    public function used()
    {
        return $this->state(fn() => [
            'status' => 'used'
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class WebhookTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'idempotency_key' => $this->faker->uuid(),
            'order_id' => Order::factory(),
            'processed_at' => Carbon::now(),
            'is_successful' => true,
        ];
    }

    public function failed()
    {
        return $this->state(fn() => ['is_successful' => false]);
    }
}

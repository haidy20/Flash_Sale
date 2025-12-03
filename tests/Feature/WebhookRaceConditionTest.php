<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\WebhookTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;


class WebhookRaceConditionTest extends TestCase
{
    use RefreshDatabase;
    #[Test]

    public function webhook_is_ignored_if_order_is_not_in_pending_payment_state()
    {
        $statusesToTest = ['cancelled', 'paid'];

        foreach ($statusesToTest as $status) {
            $order = Order::factory()->create(['status' => $status]);
            $idempotencyKey = 'out-of-order-key-' . $status;

            $response = $this->postJson('/api/payments/webhook', [
                'idempotency_key' => $idempotencyKey,
                'order_id' => $order->id,
                'status' => 'successful',
            ]);

            $response->assertOk()
                ->assertJsonPath('status', 'ignored')
                ->assertJsonPath('message', 'Order is not in pending_payment state.');

            $order->refresh();
            $this->assertEquals($status, $order->status, 'Order status should not be modified.');

            $this->assertDatabaseHas('webhook_transactions', [
                'idempotency_key' => $idempotencyKey,
                'order_id' => $order->id,
                'is_successful' => true,
            ]);
        }
    }
}

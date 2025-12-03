<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\WebhookTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;


class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function webhook_with_same_idempotency_key_is_ignored_on_second_attempt()
    {
        $order = Order::factory()->create(['status' => 'pending_payment']);
        $idempotencyKey = 'unique-payment-id-12345';

        $response1 = $this->postJson('/api/payments/webhook', [
            'idempotency_key' => $idempotencyKey,
            'order_id' => $order->id,
            'status' => 'successful',
        ]);

        $response1->assertOk()
                  ->assertJsonPath('status', 'success');
        
        $order->refresh();
        $this->assertEquals('paid', $order->status);
        
        $this->assertDatabaseHas('webhook_transactions', [
            'idempotency_key' => $idempotencyKey,
            'order_id' => $order->id,
        ]);


        // المحاولة الثانية (يجب أن تتجاهل لانها بالفعل تسجلت من قبل 
        $response2 = $this->postJson('/api/payments/webhook', [
            'idempotency_key' => $idempotencyKey,
            'order_id' => $order->id,
            'status' => 'successful',
        ]);
        
        $response2->assertOk()
                  ->assertJsonPath('status', 'ignored')
                  ->assertJsonPath('message', 'Webhook already has been processed using this idempotency key.');

        $order->refresh();
        $this->assertEquals('paid', $order->status, 'Order status should not be changed by duplicate webhook.');
        
        // التأكد من أن key لم يتم تسجيله مرتين
        $this->assertEquals(1, WebhookTransaction::where('idempotency_key', $idempotencyKey)->count());
    }
}

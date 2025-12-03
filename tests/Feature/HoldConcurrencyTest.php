<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Product;
use App\Models\Hold;
use PHPUnit\Framework\Attributes\Test;

class HoldConcurrencyTest extends TestCase
{
    use RefreshDatabase;
    #[Test]

    public function it_prevents_overselling_on_parallel_hold_attempts_at_stock_boundary()
    {
        $product = Product::factory()->create(['stock_level' => 10]);
        $concurrentAttempts = 15;

        $promises = [];
        for ($i = 0; $i < $concurrentAttempts; $i++) {
            $promises[] = $this->postJson('/api/holds', [
                'product_id' => $product->id,
                'qty' => 1,
            ]);
        }

        $responses = array_map(function ($promise) {
            return $promise->baseResponse;
        }, $promises);

        $successfulHolds = 0;
        $failedHolds = 0;

        foreach ($responses as $response) {
            $data = json_decode($response->getContent(), true);
            if ($response->getStatusCode() === 201 && $data['status'] === 'success') {
                $successfulHolds++;
            } else {
                $failedHolds++;
            }
        }

        $this->assertEquals(10, $successfulHolds, 'Only 10 holds should succeed.');
        $this->assertEquals(5, $failedHolds, '5 holds should fail due to insufficient stock.');

        $product->refresh();
        $totalHeldQty = Hold::where('product_id', $product->id)->where('status', 'active')->sum('qty');
        $this->assertEquals(10, $totalHeldQty, 'Total active held quantity must match stock level.');
        $this->assertEquals(10, $product->stock_level, 'Stock level must remain the initial 10, as no order was created yet.');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Hold;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class HoldExpiryTest extends TestCase
{
    use RefreshDatabase;
    #[Test]

    public function expired_hold_quantity_is_returned_to_available_stock()
    {
        $product = Product::factory()->create(['stock_level' => 100]);
        $hold = Hold::factory()->create([
            'product_id' => $product->id,
            'qty' => 50,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinute(),
        ]);

        $responseBefore = $this->getJson("/api/products/{$product->id}");
        $responseBefore->assertJsonPath('data.available_stock', 50);

        $hold->update([
            'status' => 'expired',
            'expires_at' => Carbon::now()->subMinute()
        ]);
        Cache::forget("product:{$product->id}:held_qty");
        $responseAfter = $this->getJson("/api/products/{$product->id}");

        $responseAfter->assertOk()
            ->assertJsonPath('data.available_stock', 100);
    }
}

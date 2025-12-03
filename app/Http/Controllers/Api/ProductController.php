<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function show(string $id)
    {
        Log::info('Product Details: Received request.', ['product_id' => $id]);
        try {
            $product = Cache::remember("product:{$id}", Carbon::now()->addSeconds(10), function () use ($id) {
                return Product::find($id);
            });
            if (!$product) {
                Log::warning('Product Details: Product not found.', ['product_id' => $id]);
                return response()->json([
                    'success' => false,
                    'status'  => 404,
                    'message' => 'Product not found',
                    'data'    => null
                ], 404);
            }
            $held_qty = Cache::remember("product:{$id}:held_qty", 1, function () use ($product) {
                return $product->holds()
                    ->where('status', 'active')
                    ->sum('qty');
            });
            Log::info("Product Details: Held quantity retrieved" , ['product_id' => $id, 'held_qty' => (int) $held_qty]);
            $available_stock = $product->stock_level - $held_qty;

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Product data retrieved successfully',
                'data'    => [
                    'id'              => $product->id,
                    'name'            => $product->name,
                    'price'           => $product->price,
                    'total_stock'     => $product->stock_level,
                    'held_stock'      => (int) $held_qty,
                    'available_stock' => (int) $available_stock,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Product Details: An internal error occurred.', [
                'product_id' => $id,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'An internal error occurred',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}

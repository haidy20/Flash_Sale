<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Hold;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HoldController extends Controller
{
    public function store(Request $request)
    {
        Log::info('Hold Creation: Received request to reserve stock.', [
            'product_id' => $request->get('product_id'),
            'qty' => $request->get('qty')
        ]);
        try {
            $request->validate([
                'product_id' => ['required', 'exists:products,id'],
                'qty' => ['required', 'integer', 'min:1'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        $productId = $request->product_id;
        $qty = $request->qty;

        try {
            $hold = DB::transaction(function () use ($productId, $qty) {
                $product = Product::lockForUpdate()->findOrFail($productId);
                $heldQty = $product->holds()
                    ->where('status', 'active')
                    ->sum('qty');

                $availableStock = $product->stock_level - $heldQty;
                if ($availableStock < $qty) {
                    throw new \Exception('Stock is insufficient. Only ' . max(0, $availableStock) . ' units available.', 400);
                }

                $expiresAt = Carbon::now()->addMinutes(2);

                $hold = Hold::create([
                    'product_id' => $productId,
                    'qty' => $qty,
                    'status' => 'active',
                    'expires_at' => $expiresAt,
                ]);

                Log::info('Hold Creation: Hold created successfully.', [
                    'hold_id' => $hold->id,
                    'product_id' => $productId,
                    'qty' => $qty,
                    'expires_at' => $expiresAt->toDateTimeString()
                ]);
                return $hold;
            });
            return response()->json([
                'status' => 'success',
                'message' => 'Hold created successfully.',
                'data' => [
                    'hold_id' => $hold->id,
                    'product_id' => $hold->product_id,
                    'qty' => $hold->qty,
                    'expires_at' => $hold->expires_at->toDateTimeString(),
                ]
            ], 201);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() === 400 ? 400 : 500;
            Log::error('Hold Creation: Transaction failed.', [
                'product_id' => $productId,
                'status_code' => $statusCode,
                'exception' => $e->getMessage()
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ],
                $statusCode,
            );
        }
    }
}

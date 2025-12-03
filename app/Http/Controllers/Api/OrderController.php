<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hold;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {

        try {
            $request->validate([
                'hold_id' => ['required', 'exists:holds,id'],
            ]);
        } catch (ValidationException $e) {
            Log::warning('Order Creation: Validation failed.', [
                'errors' => $e->errors(),
                'data' => $request->all()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        $holdId = $request->hold_id;

        try {
            $order = DB::transaction(function () use ($holdId) {

                $hold = Hold::lockForUpdate()->findOrFail($holdId);

                Log::debug('Order Creation: Starting transaction for hold.', ['hold_id' => $holdId, 'product_id' => $hold->product_id]);
                if ($hold->status !== 'active') {
                    Log::warning('Order Creation: Hold status not active.', ['hold_id' => $holdId, 'status' => $hold->status]);
                    throw new \Exception('Hold is not active or has already been used. Status: ' . $hold->status, 400);
                }

                if ($hold->expires_at < Carbon::now()) {
                    Log::warning('Order Creation: Hold expired.', ['hold_id' => $holdId, 'expires_at' => $hold->expires_at]);
                    throw new \Exception('Hold has expired and cannot be converted to an order.', 400);
                }
                $product = $hold->product()->lockForUpdate()->first();
                $qty = $hold->qty;

                if ($product->stock_level < $qty) {
                    Log::error('Order Creation: Stock insufficient during transaction.', ['product_id' => $product->id, 'stock_level' => $product->stock_level, 'required_qty' => $qty]);
                    throw new \Exception('Stock has become insufficient during transaction.', 400);
                }


                $product->stock_level -= $qty;
                $product->save();

                $price = $hold->product->price ?? 100.00;
                $totalPrice = $price * $qty;

                $order = Order::create([
                    'hold_id' => $hold->id,
                    'product_id' => $hold->product_id,
                    'qty' => $qty,
                    'total_price' => $totalPrice,
                    'status' => 'pending_payment',
                ]);

                $hold->update(['status' => 'used']);
                Log::info('Order Creation: Order and Hold status updated successfully.', [
                    'order_id' => $order->id,
                    'hold_id' => $hold->id,
                    'new_hold_status' => 'used'
                ]);

                return $order;
            });

            $qty = $order->qty;
            $unitPriceCalculated = ($qty > 0) ? ($order->total_price / $qty) : 0.00;

            return response()->json([
                'status' => 'success',
                'message' => 'Order created successfully from hold.',
                'data' => [
                    'order_id' => $order->id,
                    'order_status' => $order->status,
                    'product_id' => $order->product_id,
                    'qty' => $qty,
                    'unit_price' => round($unitPriceCalculated, 2),
                    'total_amount' => $order->total_price,
                ]
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Hold not found.', 'code' => 404], 404);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() === 400 ? 400 : 500;

            Log::error('Order Creation: Transaction failed or guard triggered.', [
                'hold_id' => $holdId,
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

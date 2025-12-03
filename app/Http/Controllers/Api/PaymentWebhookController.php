<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\WebhookTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $isDuplicate = false;
        $ignoreReason = null;

        Log::info('Payment Webhook: Received request.', [
            'payload' => $request->all()
        ]);

        try {
            $request->validate([
                'idempotency_key' => ['required', 'string', 'max:255'],
                'order_id' => ['required', 'exists:orders,id'],
                'status' => ['required', 'in:successful,failed'],
            ]);
        } catch (ValidationException $e) {
            Log::warning('Payment Webhook: Validation failed.', [
                'errors' => $e->errors(),
                'data' => $request->all()
            ]);
            return response()->json(['status' => 'error', 'message' => 'Validation failed.'], 422);
        }
        $idempotencyKey = $request->idempotency_key;
        $orderId = $request->order_id;
        $paymentStatus = $request->status;
        $isSuccessful = $paymentStatus === 'successful';

        try {
            DB::transaction(function () use (&$isDuplicate, &$ignoreReason, $idempotencyKey, $orderId, $isSuccessful, $request) {

                if (WebhookTransaction::where('idempotency_key', $idempotencyKey)->exists()) {
                    $isDuplicate = true;
                    Log::info('Payment Webhook: Duplicate key detected.', ['key' => $idempotencyKey]);
                    return;
                }

                WebhookTransaction::create([
                    'idempotency_key' => $idempotencyKey,
                    'order_id' => $orderId,
                    'processed_at' => now(),
                    'is_successful' => $isSuccessful,
                ]);

                $order = Order::lockForUpdate()->findOrFail($orderId);
                $product = Product::find($order->product_id)->lockForUpdate()->first();

                if ($order->status !== 'pending_payment') {
                    $ignoreReason = 'Order is not in pending_payment state.';
                    Log::warning('Payment Webhook: Order state mismatch.', [
                        'order_id' => $orderId,
                        'current_status' => $order->status,
                        'expected' => 'pending_payment'
                    ]);
                    return;
                }

                if ($isSuccessful) {
                    $order->update(['status' => 'paid']);
                    Log::info('Payment Webhook: Order marked as paid.', ['order_id' => $orderId]);
                } else {
                    $order->update(['status' => 'cancelled']);
                    $product->stock_level += $order->qty;

                    $product->save();

                    Log::info('Payment Webhook: Order cancelled and stock released.', [
                        'order_id' => $orderId,
                        'released_qty' => $order->qty,
                        'product_id' => $product->id
                    ]);
                }
            });

            if ($isDuplicate) {
                return response()->json([
                    'status' => 'ignored',
                    'message' => 'Webhook already has been processed using this idempotency key.',
                    'order_id' => $orderId,
                    'idempotency_key' => $idempotencyKey
                ], 200);
            }

            if ($ignoreReason) {
                return response()->json([
                    'status' => 'ignored',
                    'message' => $ignoreReason,
                    'order_id' => $orderId,
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => "Webhook processed successfully to {$paymentStatus}.",
                'order_id' => $orderId,
            ], 200);
        } catch (\Exception $e) {

            Log::error('Payment Webhook: Internal error during processing.', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'order_id' => $orderId ?? 'unknown'
            ]);
            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'An internal error occurred during webhook processing.',
                    'details' => $e->getMessage()
                ],
                500,
            );
        }
    }
}

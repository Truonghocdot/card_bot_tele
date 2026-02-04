<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Handle payment webhook from payment gateway
     */
    public function handle(Request $request)
    {
        try {
            $data = $request->all();

            Log::channel('payment')->info('Payment webhook received', [
                'data' => $data,
            ]);

            // Extract webhook data
            $txHash = $data['tx_hash'] ?? $data['transaction_hash'] ?? null;
            $amount = $data['amount'] ?? null;
            $address = $data['address'] ?? $data['payment_address'] ?? null;
            $paymentId = $data['payment_id'] ?? $data['order_id'] ?? null;
            $status = $data['status'] ?? 'pending';

            // Validate required fields
            if (!$txHash || !$amount) {
                Log::channel('payment')->warning('Invalid webhook data', [
                    'data' => $data,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid webhook data'
                ], 400);
            }

            // Find payment record
            $payment = null;
            if ($paymentId) {
                $payment = Payment::find($paymentId);
            } elseif ($address) {
                $payment = Payment::where('payment_address', $address)
                    ->where('status', Payment::STATUS_PENDING)
                    ->where('amount', $amount)
                    ->first();
            }

            if (!$payment) {
                Log::channel('payment')->warning('Payment not found', [
                    'payment_id' => $paymentId,
                    'address' => $address,
                    'amount' => $amount,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found'
                ], 404);
            }

            // Validate amount matches
            if (abs($payment->amount - $amount) > 0.01) {
                Log::channel('payment')->warning('Amount mismatch', [
                    'payment_id' => $payment->id,
                    'expected' => $payment->amount,
                    'received' => $amount,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Amount mismatch'
                ], 400);
            }

            // Only process if status is confirmed/completed
            if (in_array(strtolower($status), ['confirmed', 'completed', 'success'])) {
                // Dispatch job to process payment confirmation
                // TODO: Create ProcessPaymentConfirmation job in Step 6
                // dispatch(new ProcessPaymentConfirmation($payment, $txHash));

                // For now, confirm payment directly
                $this->paymentService->confirmPayment($payment, $txHash);
            }

            Log::channel('payment')->info('Payment webhook processed', [
                'payment_id' => $payment->id,
                'tx_hash' => $txHash,
                'status' => $status,
            ]);

            return response()->json([
                'status' => 'ok',
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            Log::channel('payment')->error('Payment webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error'
            ], 500);
        }
    }
}

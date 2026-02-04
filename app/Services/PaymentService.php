<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PaymentService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $walletAddress;

    public function __construct()
    {
        $this->apiUrl = config('payment.api_url');
        $this->apiKey = config('payment.api_key');
        $this->walletAddress = config('payment.wallet_address');
    }

    /**
     * Generate payment request for a transaction
     * 
     * @param Transaction $transaction
     * @return array ['address' => '...', 'amount' => 10.00, 'payment_id' => '...']
     */
    public function generatePaymentRequest(Transaction $transaction): array
    {
        try {
            // Create payment record
            $payment = Payment::create([
                'customer_id' => $transaction->customer_id,
                'transaction_id' => $transaction->id,
                'payment_address' => $this->walletAddress,
                'amount' => $transaction->amount,
                'status' => Payment::STATUS_PENDING,
            ]);

            // TODO: Call payment gateway API to create payment session
            // For now, we'll use a simple approach with fixed wallet address
            // In production, you would call the payment gateway API here:
            /*
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->apiUrl . '/create-payment', [
                'amount' => $transaction->amount,
                'currency' => 'USDT',
                'network' => 'TRC20',
                'order_id' => $transaction->id,
                'callback_url' => route('payment.webhook'),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $payment->update([
                    'payment_address' => $data['address'],
                ]);
            }
            */

            Log::channel('payment')->info('Payment request generated', [
                'payment_id' => $payment->id,
                'transaction_id' => $transaction->id,
                'amount' => $payment->amount,
            ]);

            return [
                'address' => $payment->payment_address,
                'amount' => $payment->amount,
                'payment_id' => $payment->id,
            ];
        } catch (\Exception $e) {
            Log::channel('payment')->error('Failed to generate payment request', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Check payment status via API
     * 
     * @param Payment $payment
     * @return string 'pending', 'confirmed', 'failed'
     */
    public function checkPaymentStatus(Payment $payment): string
    {
        try {
            // TODO: Query payment gateway API
            /*
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->apiUrl . '/check-payment/' . $payment->id);

            if ($response->successful()) {
                $data = $response->json();
                return $data['status']; // 'pending', 'confirmed', 'failed'
            }
            */

            // For now, return current status
            return $payment->status;
        } catch (\Exception $e) {
            Log::channel('payment')->error('Failed to check payment status', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return Payment::STATUS_PENDING;
        }
    }

    /**
     * Confirm payment and update customer balance
     * 
     * @param Payment $payment
     * @param string $txHash
     * @return void
     */
    public function confirmPayment(Payment $payment, string $txHash): void
    {
        try {
            // Update payment status
            $payment->update([
                'status' => Payment::STATUS_CONFIRMED,
                'tx_hash' => $txHash,
                'confirmed_at' => now(),
            ]);

            // Get customer
            $customer = $payment->customer;

            // Add to customer balance
            $customer->increment('balance', $payment->amount);

            // Update related transaction if exists
            if ($payment->transaction_id) {
                $transaction = $payment->transaction;
                $transaction->update([
                    'status' => Transaction::STATUS_PAYMENT_CONFIRMED,
                    'usdt_tx_hash' => $txHash,
                    'paid_at' => now(),
                ]);

                // Update to admin_review status
                $transaction->update([
                    'status' => Transaction::STATUS_ADMIN_REVIEW,
                ]);

                Log::channel('transaction')->info('Transaction status updated to admin_review', [
                    'transaction_id' => $transaction->id,
                ]);

                // TODO: Dispatch NotifyAdminForApproval job (Step 6)
                // dispatch(new NotifyAdminForApproval($transaction));
            }

            // Send notification to client
            $telegramService = app(TelegramClientService::class);
            $telegramService->sendTransactionUpdate(
                $customer->telegram_chat_id,
                'payment_confirmed',
                [
                    'amount' => $payment->amount,
                    'tx_hash' => $txHash,
                    'balance' => $customer->balance,
                ]
            );

            Log::channel('payment')->info('Payment confirmed', [
                'payment_id' => $payment->id,
                'customer_id' => $customer->id,
                'amount' => $payment->amount,
                'tx_hash' => $txHash,
            ]);
        } catch (\Exception $e) {
            Log::channel('payment')->error('Failed to confirm payment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

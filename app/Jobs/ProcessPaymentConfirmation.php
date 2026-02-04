<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessPaymentConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Payment $payment,
        public string $txHash
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PaymentService $paymentService): void
    {
        try {
            // Confirm payment using PaymentService
            $paymentService->confirmPayment($this->payment, $this->txHash);

            // Dispatch NotifyAdminForApproval if transaction exists
            if ($this->payment->transaction_id) {
                $transaction = $this->payment->transaction;

                if ($transaction && $transaction->status === \App\Models\Transaction::STATUS_ADMIN_REVIEW) {
                    dispatch(new NotifyAdminForApproval($transaction));
                }
            }

            Log::channel('payment')->info('Payment confirmation processed', [
                'payment_id' => $this->payment->id,
                'tx_hash' => $this->txHash,
            ]);
        } catch (\Exception $e) {
            Log::channel('payment')->error('Failed to process payment confirmation', [
                'payment_id' => $this->payment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

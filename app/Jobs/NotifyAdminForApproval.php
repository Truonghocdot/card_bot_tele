<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\TelegramAdminService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifyAdminForApproval implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Transaction $transaction
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TelegramAdminService $telegram): void
    {
        try {
            // Load transaction with customer relationship
            $this->transaction->load('customer');
            $customer = $this->transaction->customer;

            // Get customer statistics
            $totalTransactions = $customer->transactions()->count();
            $approvedCount = $customer->transactions()
                ->where('status', Transaction::STATUS_APPROVED)
                ->count();
            $rejectedCount = $customer->transactions()
                ->where('status', Transaction::STATUS_REJECTED)
                ->count();

            // Prepare transaction data for admin
            $transactionData = [
                'id' => $this->transaction->id,
                'code' => $this->transaction->code,
                'amount' => $this->transaction->amount,
                'balance' => $customer->balance,
                'username' => $customer->telegram_username ?? 'N/A',
                'first_name' => $customer->telegram_first_name ?? 'Unknown',
                'created_at' => $this->transaction->created_at->format('d/m/Y H:i'),
                'total_transactions' => $totalTransactions,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
            ];

            // Send approval request to admin
            $messageId = $telegram->sendApprovalRequest($transactionData);

            if ($messageId) {
                Log::channel('telegram')->info('Admin approval request sent', [
                    'transaction_id' => $this->transaction->id,
                    'message_id' => $messageId,
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('telegram')->error('Failed to notify admin for approval', [
                'transaction_id' => $this->transaction->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

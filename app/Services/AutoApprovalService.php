<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\AdminAction;
use App\Jobs\SendDataToClient;
use Illuminate\Support\Facades\Log;

class AutoApprovalService
{
    /**
     * Check if customer should be auto-approved
     * 
     * Conditions:
     * - Has at least 3 approved transactions
     * - No rejected transactions in last 30 days
     * - Balance >= transaction amount
     * - No other pending transactions
     * 
     * @param Customer $customer
     * @param Transaction $transaction
     * @return bool
     */
    public function shouldAutoApprove(Customer $customer, Transaction $transaction): bool
    {
        // Check if customer has at least 3 approved transactions
        $approvedCount = $customer->transactions()
            ->where('status', Transaction::STATUS_APPROVED)
            ->count();

        if ($approvedCount < 3) {
            Log::channel('transaction')->info('Auto-approval denied: insufficient approved transactions', [
                'customer_id' => $customer->id,
                'approved_count' => $approvedCount,
            ]);
            return false;
        }

        // Check for recent rejections (last 30 days)
        $hasRecentRejections = $customer->hasRecentRejections(30);

        if ($hasRecentRejections) {
            Log::channel('transaction')->info('Auto-approval denied: recent rejections', [
                'customer_id' => $customer->id,
            ]);
            return false;
        }

        // Check if customer has sufficient balance
        if ($customer->balance < $transaction->amount) {
            Log::channel('transaction')->info('Auto-approval denied: insufficient balance', [
                'customer_id' => $customer->id,
                'balance' => $customer->balance,
                'required' => $transaction->amount,
            ]);
            return false;
        }

        // Check for other pending transactions
        $hasPendingTransactions = $customer->transactions()
            ->whereIn('status', [
                Transaction::STATUS_PENDING,
                Transaction::STATUS_PAYMENT_REQUIRED,
                Transaction::STATUS_PAYMENT_CONFIRMED,
                Transaction::STATUS_ADMIN_REVIEW,
            ])
            ->where('id', '!=', $transaction->id)
            ->exists();

        if ($hasPendingTransactions) {
            Log::channel('transaction')->info('Auto-approval denied: has other pending transactions', [
                'customer_id' => $customer->id,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Auto-approve a transaction
     * 
     * @param Transaction $transaction
     * @return void
     */
    public function autoApprove(Transaction $transaction): void
    {
        try {
            $customer = $transaction->customer;

            // Deduct customer balance
            $customer->decrement('balance', $transaction->amount);

            // Update transaction status
            $transaction->update([
                'status' => Transaction::STATUS_APPROVED,
                'approved_at' => now(),
            ]);

            // Create admin action log with system note
            AdminAction::create([
                'admin_telegram_id' => 'SYSTEM',
                'transaction_id' => $transaction->id,
                'action' => AdminAction::ACTION_APPROVED,
                'note' => 'Auto-approved by system - Customer has good history',
                'created_at' => now(),
            ]);

            // Dispatch job to send data to client
            dispatch(new SendDataToClient($transaction));

            // Send auto-approval notification to admin
            $telegramAdmin = app(TelegramAdminService::class);
            $telegramAdmin->sendAutoApprovalNotification([
                'username' => $customer->telegram_username ?? 'N/A',
                'code' => $transaction->code,
                'amount' => $transaction->amount,
                'time' => now()->format('d/m/Y H:i'),
            ]);

            Log::channel('transaction')->info('Transaction auto-approved', [
                'transaction_id' => $transaction->id,
                'customer_id' => $customer->id,
                'amount' => $transaction->amount,
                'new_balance' => $customer->balance,
            ]);
        } catch (\Exception $e) {
            Log::channel('transaction')->error('Failed to auto-approve transaction', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

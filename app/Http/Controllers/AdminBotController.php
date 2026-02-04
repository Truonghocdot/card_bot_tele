<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\AdminAction;
use App\Services\TelegramAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminBotController extends Controller
{
    protected TelegramAdminService $telegram;

    public function __construct(TelegramAdminService $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Handle incoming webhook from Telegram
     */
    public function webhook(Request $request)
    {
        try {
            $update = $request->all();

            Log::channel('telegram')->info('Admin webhook received', [
                'update_id' => $update['update_id'] ?? null,
            ]);

            // Handle callback query (button clicks)
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);
                return response()->json(['status' => 'ok']);
            }

            // Handle commands
            $message = $update['message'] ?? null;
            if ($message && isset($message['text'])) {
                $this->handleCommand($message['chat']['id'], $message['text']);
            }

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::channel('telegram')->error('Admin webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Handle callback query (button clicks)
     */
    protected function handleCallbackQuery(array $callbackQuery): void
    {
        $callbackData = $callbackQuery['data'] ?? '';
        $callbackQueryId = $callbackQuery['id'] ?? '';
        $messageId = $callbackQuery['message']['message_id'] ?? null;
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $adminTelegramId = $callbackQuery['from']['id'] ?? '';
        $adminName = $callbackQuery['from']['first_name'] ?? 'Admin';

        Log::channel('telegram')->info('Callback query received', [
            'callback_data' => $callbackData,
            'admin_id' => $adminTelegramId,
        ]);

        // Parse callback data: "approve_{transaction_id}" or "reject_{transaction_id}"
        if (preg_match('/^(approve|reject)_(\d+)$/', $callbackData, $matches)) {
            $action = $matches[1];
            $transactionId = (int) $matches[2];

            $transaction = Transaction::find($transactionId);

            if (!$transaction) {
                $this->telegram->answerCallbackQuery(
                    $callbackQueryId,
                    '❌ Giao dịch không tồn tại'
                );
                return;
            }

            if ($action === 'approve') {
                $this->approveTransaction($transaction, $chatId, $messageId, $adminTelegramId, $adminName);
                $this->telegram->answerCallbackQuery($callbackQueryId, '✅ Đã duyệt');
            } else {
                $this->rejectTransaction($transaction, $chatId, $messageId, $adminTelegramId, $adminName);
                $this->telegram->answerCallbackQuery($callbackQueryId, '❌ Đã từ chối');
            }
        }
    }

    /**
     * Approve transaction
     */
    protected function approveTransaction(
        Transaction $transaction,
        string $chatId,
        int $messageId,
        string $adminTelegramId,
        string $adminName
    ): void {
        try {
            // Validate transaction status
            if ($transaction->status !== Transaction::STATUS_ADMIN_REVIEW) {
                Log::channel('transaction')->warning('Invalid transaction status for approval', [
                    'transaction_id' => $transaction->id,
                    'current_status' => $transaction->status,
                ]);
                return;
            }

            $customer = $transaction->customer;

            // Check if customer has sufficient balance
            if ($customer->balance < $transaction->amount) {
                $this->telegram->answerCallbackQuery(
                    '',
                    '❌ Khách hàng không đủ số dư'
                );
                return;
            }

            // Deduct customer balance
            $customer->decrement('balance', $transaction->amount);

            // Update transaction
            $transaction->update([
                'status' => Transaction::STATUS_APPROVED,
                'approved_at' => now(),
            ]);

            // Create admin action log
            AdminAction::create([
                'admin_telegram_id' => $adminTelegramId,
                'transaction_id' => $transaction->id,
                'action' => AdminAction::ACTION_APPROVED,
                'note' => 'Approved via Telegram bot',
                'created_at' => now(),
            ]);

            // Update admin message
            $this->telegram->updateApprovalMessage($messageId, 'approved', [
                'code' => $transaction->code,
                'username' => $customer->telegram_username ?? 'N/A',
                'amount' => $transaction->amount,
                'action_time' => now()->format('d/m/Y H:i'),
                'admin_name' => $adminName,
            ]);

            // TODO: Dispatch SendDataToClient job (Step 6)
            // dispatch(new SendDataToClient($transaction));

            // For now, send notification directly
            $telegramClient = app(\App\Services\TelegramClientService::class);
            $telegramClient->sendTransactionUpdate(
                $customer->telegram_chat_id,
                'approved',
                [
                    'code' => $transaction->code,
                    'amount' => $transaction->amount,
                    'balance' => $customer->balance,
                    'approved_at' => $transaction->approved_at->format('d/m/Y H:i'),
                    'transaction_data' => $transaction->data ?? 'Đang xử lý...',
                ]
            );

            Log::channel('transaction')->info('Transaction approved', [
                'transaction_id' => $transaction->id,
                'admin_id' => $adminTelegramId,
                'amount' => $transaction->amount,
            ]);
        } catch (\Exception $e) {
            Log::channel('transaction')->error('Failed to approve transaction', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reject transaction
     */
    protected function rejectTransaction(
        Transaction $transaction,
        string $chatId,
        int $messageId,
        string $adminTelegramId,
        string $adminName
    ): void {
        try {
            // Update transaction
            $transaction->update([
                'status' => Transaction::STATUS_REJECTED,
                'rejected_at' => now(),
            ]);

            // Create admin action log
            AdminAction::create([
                'admin_telegram_id' => $adminTelegramId,
                'transaction_id' => $transaction->id,
                'action' => AdminAction::ACTION_REJECTED,
                'note' => 'Rejected via Telegram bot',
                'created_at' => now(),
            ]);

            $customer = $transaction->customer;

            // Update admin message
            $this->telegram->updateApprovalMessage($messageId, 'rejected', [
                'code' => $transaction->code,
                'username' => $customer->telegram_username ?? 'N/A',
                'amount' => $transaction->amount,
                'action_time' => now()->format('d/m/Y H:i'),
                'admin_name' => $adminName,
            ]);

            // Send notification to client
            $telegramClient = app(\App\Services\TelegramClientService::class);
            $telegramClient->sendTransactionUpdate(
                $customer->telegram_chat_id,
                'rejected',
                [
                    'code' => $transaction->code,
                    'rejected_at' => $transaction->rejected_at->format('d/m/Y H:i'),
                ]
            );

            Log::channel('transaction')->info('Transaction rejected', [
                'transaction_id' => $transaction->id,
                'admin_id' => $adminTelegramId,
            ]);
        } catch (\Exception $e) {
            Log::channel('transaction')->error('Failed to reject transaction', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle admin commands
     */
    protected function handleCommand(string $chatId, string $command): void
    {
        $commandName = strtolower(explode(' ', $command)[0]);

        match ($commandName) {
            '/stats' => $this->handleStatsCommand($chatId),
            '/pending' => $this->handlePendingCommand($chatId),
            default => null,
        };
    }

    /**
     * Handle /stats command
     */
    protected function handleStatsCommand(string $chatId): void
    {
        $today = now()->startOfDay();

        $todayTransactions = Transaction::where('created_at', '>=', $today)->count();
        $todayRevenue = Transaction::where('status', Transaction::STATUS_APPROVED)
            ->where('approved_at', '>=', $today)
            ->sum('amount');

        $pendingCount = Transaction::where('status', Transaction::STATUS_ADMIN_REVIEW)->count();

        $activeCustomers = Transaction::where('created_at', '>=', now()->subDays(7))
            ->distinct('customer_id')
            ->count();

        $text = "📊 <b>THỐNG KÊ HỆ THỐNG</b>\n\n";
        $text .= "📅 Hôm nay:\n";
        $text .= "  • Giao dịch: {$todayTransactions}\n";
        $text .= "  • Doanh thu: {$todayRevenue} USDT\n\n";
        $text .= "⏳ Đang chờ duyệt: <b>{$pendingCount}</b>\n";
        $text .= "👥 Khách hàng active (7 ngày): {$activeCustomers}";

        $this->telegram->sendNotification($text);
    }

    /**
     * Handle /pending command
     */
    protected function handlePendingCommand(string $chatId): void
    {
        $pending = Transaction::where('status', Transaction::STATUS_ADMIN_REVIEW)
            ->with('customer')
            ->orderBy('created_at', 'asc')
            ->limit(10)
            ->get();

        if ($pending->isEmpty()) {
            $this->telegram->sendNotification("✅ Không có giao dịch nào đang chờ duyệt.");
            return;
        }

        $text = "⏳ <b>GIAO DỊCH ĐANG CHỜ DUYỆT</b>\n\n";

        foreach ($pending as $transaction) {
            $customer = $transaction->customer;
            $text .= "#{$transaction->id} - <code>{$transaction->code}</code>\n";
            $text .= "  👤 @{$customer->telegram_username}\n";
            $text .= "  💰 {$transaction->amount} USDT\n";
            $text .= "  ⏰ {$transaction->created_at->format('d/m H:i')}\n\n";
        }

        $this->telegram->sendNotification($text);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Transaction;
use App\Services\TelegramClientService;
use App\Services\PaymentService;
use App\Services\AutoApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ClientBotController extends Controller
{
    protected TelegramClientService $telegram;
    protected PaymentService $paymentService;
    protected AutoApprovalService $autoApprovalService;

    public function __construct(
        TelegramClientService $telegram,
        PaymentService $paymentService,
        AutoApprovalService $autoApprovalService
    ) {
        $this->telegram = $telegram;
        $this->paymentService = $paymentService;
        $this->autoApprovalService = $autoApprovalService;
    }

    /**
     * Handle incoming webhook from Telegram
     */
    public function webhook(Request $request)
    {
        try {
            $update = $request->all();

            Log::channel('telegram')->info('Client webhook received', [
                'update_id' => $update['update_id'] ?? null,
            ]);

            // Extract message data
            $message = $update['message'] ?? null;
            if (!$message) {
                return response()->json(['status' => 'ok']);
            }

            $chatId = $message['chat']['id'] ?? null;
            $text = $message['text'] ?? null;
            $from = $message['from'] ?? [];

            if (!$chatId || !$text) {
                return response()->json(['status' => 'ok']);
            }

            // Handle commands
            if (str_starts_with($text, '/')) {
                $this->handleCommand($chatId, $text, $from);
            } else {
                // Handle code input
                $this->handleCodeInput($chatId, $text, $from);
            }

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::channel('telegram')->error('Client webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Handle bot commands
     */
    protected function handleCommand(string $chatId, string $command, array $from): void
    {
        $commandName = strtolower(explode(' ', $command)[0]);

        match ($commandName) {
            '/start' => $this->handleStartCommand($chatId, $from),
            '/balance' => $this->handleBalanceCommand($chatId, $from),
            '/history' => $this->handleHistoryCommand($chatId, $from),
            default => $this->telegram->sendMessage(
                $chatId,
                "❓ Lệnh không hợp lệ.\n\n" .
                    "Các lệnh khả dụng:\n" .
                    "/start - Bắt đầu\n" .
                    "/balance - Xem số dư\n" .
                    "/history - Lịch sử giao dịch"
            ),
        };
    }

    /**
     * Handle /start command
     */
    protected function handleStartCommand(string $chatId, array $from): void
    {
        // Create or get customer
        $customer = Customer::firstOrCreate(
            ['telegram_chat_id' => (string)$chatId],
            [
                'telegram_username' => $from['username'] ?? null,
                'telegram_first_name' => $from['first_name'] ?? null,
                'telegram_last_name' => $from['last_name'] ?? null,
            ]
        );

        // Update customer info if changed
        if (!$customer->wasRecentlyCreated) {
            $customer->update([
                'telegram_username' => $from['username'] ?? $customer->telegram_username,
                'telegram_first_name' => $from['first_name'] ?? $customer->telegram_first_name,
                'telegram_last_name' => $from['last_name'] ?? $customer->telegram_last_name,
            ]);
        }

        $name = $customer->telegram_first_name ?? $customer->telegram_username ?? 'bạn';

        $text = "👋 <b>Chào mừng {$name}!</b>\n\n";
        $text .= "Vui lòng nhập mã để sử dụng dịch vụ.\n";
        $text .= "Ví dụ: <code>ABC123</code>\n\n";
        $text .= "💰 Số dư hiện tại: <b>{$customer->balance} USDT</b>\n\n";
        $text .= "📋 Các lệnh khả dụng:\n";
        $text .= "/balance - Xem số dư\n";
        $text .= "/history - Lịch sử giao dịch";

        $this->telegram->sendMessage($chatId, $text);

        Log::channel('telegram')->info('Customer started bot', [
            'customer_id' => $customer->id,
            'chat_id' => $chatId,
            'was_new' => $customer->wasRecentlyCreated,
        ]);
    }

    /**
     * Handle /balance command
     */
    protected function handleBalanceCommand(string $chatId, array $from): void
    {
        $customer = Customer::where('telegram_chat_id', (string)$chatId)->first();

        if (!$customer) {
            $this->telegram->sendMessage($chatId, "❌ Vui lòng sử dụng lệnh /start trước.");
            return;
        }

        $completedCount = $customer->transactions()
            ->where('status', Transaction::STATUS_APPROVED)
            ->count();

        $text = "💰 <b>SỐ DƯ CỦA BẠN</b>\n\n";
        $text .= "Số dư: <b>{$customer->balance} USDT</b>\n";
        $text .= "Giao dịch hoàn thành: <b>{$completedCount}</b>";

        $this->telegram->sendMessage($chatId, $text);
    }

    /**
     * Handle /history command
     */
    protected function handleHistoryCommand(string $chatId, array $from): void
    {
        $customer = Customer::where('telegram_chat_id', (string)$chatId)->first();

        if (!$customer) {
            $this->telegram->sendMessage($chatId, "❌ Vui lòng sử dụng lệnh /start trước.");
            return;
        }

        $transactions = $customer->transactions()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($transactions->isEmpty()) {
            $this->telegram->sendMessage($chatId, "📋 Bạn chưa có giao dịch nào.");
            return;
        }

        $text = "📋 <b>LỊCH SỬ GIAO DỊCH</b>\n\n";

        foreach ($transactions as $transaction) {
            $statusEmoji = match ($transaction->status) {
                Transaction::STATUS_APPROVED => '✅',
                Transaction::STATUS_REJECTED => '❌',
                Transaction::STATUS_ADMIN_REVIEW => '⏳',
                Transaction::STATUS_PAYMENT_CONFIRMED => '💳',
                Transaction::STATUS_PAYMENT_REQUIRED => '💰',
                default => '⏺',
            };

            $statusText = match ($transaction->status) {
                Transaction::STATUS_APPROVED => 'Đã duyệt',
                Transaction::STATUS_REJECTED => 'Từ chối',
                Transaction::STATUS_ADMIN_REVIEW => 'Chờ duyệt',
                Transaction::STATUS_PAYMENT_CONFIRMED => 'Đã thanh toán',
                Transaction::STATUS_PAYMENT_REQUIRED => 'Chờ thanh toán',
                default => 'Đang xử lý',
            };

            $text .= "{$statusEmoji} <code>{$transaction->code}</code>\n";
            $text .= "   Trạng thái: {$statusText}\n";
            $text .= "   Số tiền: {$transaction->amount} USDT\n";
            $text .= "   Thời gian: {$transaction->created_at->format('d/m/Y H:i')}\n\n";
        }

        $this->telegram->sendMessage($chatId, $text);
    }

    /**
     * Handle code input from user
     */
    protected function handleCodeInput(string $chatId, string $code, array $from): void
    {
        // Get or create customer
        $customer = Customer::firstOrCreate(
            ['telegram_chat_id' => (string)$chatId],
            [
                'telegram_username' => $from['username'] ?? null,
                'telegram_first_name' => $from['first_name'] ?? null,
                'telegram_last_name' => $from['last_name'] ?? null,
            ]
        );

        // Validate code
        $validator = Validator::make(['code' => $code], [
            'code' => 'required|alpha_num|min:6|max:20',
        ]);

        if ($validator->fails()) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ Mã không hợp lệ.\n\n" .
                    "Mã phải là chữ và số, từ 6-20 ký tự."
            );
            return;
        }

        // Check for duplicate pending code
        $existingTransaction = Transaction::where('code', strtoupper($code))
            ->whereIn('status', [
                Transaction::STATUS_PENDING,
                Transaction::STATUS_PAYMENT_REQUIRED,
                Transaction::STATUS_PAYMENT_CONFIRMED,
                Transaction::STATUS_ADMIN_REVIEW,
            ])
            ->first();

        if ($existingTransaction) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ Mã này đang được xử lý.\n\n" .
                    "Vui lòng sử dụng mã khác hoặc đợi mã hiện tại được xử lý xong."
            );
            return;
        }

        // Check if customer has previous approved transactions
        $previousApprovedTransaction = $customer->transactions()
            ->where('status', Transaction::STATUS_APPROVED)
            ->exists();

        // Create new transaction
        $transaction = Transaction::create([
            'customer_id' => $customer->id,
            'code' => strtoupper($code),
            'status' => $previousApprovedTransaction
                ? Transaction::STATUS_ADMIN_REVIEW
                : Transaction::STATUS_PAYMENT_REQUIRED,
            'amount' => 10.00, // Default amount
        ]);

        Log::channel('transaction')->info('Transaction created', [
            'transaction_id' => $transaction->id,
            'customer_id' => $customer->id,
            'code' => $transaction->code,
            'is_new_customer' => !$previousApprovedTransaction,
        ]);

        if ($previousApprovedTransaction) {
            // Existing customer - send to admin review
            $this->handleExistingCustomer($chatId, $customer, $transaction);
        } else {
            // New customer - require payment
            $this->handleNewCustomer($chatId, $customer, $transaction);
        }
    }

    /**
     * Handle new customer (requires payment)
     */
    protected function handleNewCustomer(string $chatId, Customer $customer, Transaction $transaction): void
    {
        try {
            // Generate payment request using PaymentService
            $paymentData = $this->paymentService->generatePaymentRequest($transaction);

            $this->telegram->sendPaymentRequest(
                $chatId,
                (float) $paymentData['amount'],
                $paymentData['address']
            );

            Log::channel('transaction')->info('Payment required for new customer', [
                'transaction_id' => $transaction->id,
                'customer_id' => $customer->id,
                'payment_id' => $paymentData['payment_id'],
            ]);
        } catch (\Exception $e) {
            Log::channel('transaction')->error('Failed to generate payment request', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            $this->telegram->sendMessage(
                $chatId,
                "❌ Có lỗi xảy ra khi tạo yêu cầu thanh toán.\n\n" .
                    "Vui lòng thử lại sau hoặc liên hệ admin."
            );
        }
    }

    /**
     * Handle existing customer (send to admin review or auto-approve)
     */
    protected function handleExistingCustomer(string $chatId, Customer $customer, Transaction $transaction): void
    {
        // Check if customer qualifies for auto-approval
        if ($this->autoApprovalService->shouldAutoApprove($customer, $transaction)) {
            // Auto-approve the transaction
            $this->autoApprovalService->autoApprove($transaction);

            $this->telegram->sendTransactionUpdate($chatId, 'auto_approved', [
                'code' => $transaction->code,
                'amount' => $transaction->amount,
                'balance' => $customer->fresh()->balance,
            ]);

            Log::channel('transaction')->info('Transaction auto-approved for customer', [
                'transaction_id' => $transaction->id,
                'customer_id' => $customer->id,
            ]);
        } else {
            // Send to admin review
            dispatch(new \App\Jobs\NotifyAdminForApproval($transaction));

            $this->telegram->sendMessage(
                $chatId,
                "✅ Đã nhận mã: <code>{$transaction->code}</code>\n\n" .
                    "⏳ Đang chờ admin xác nhận...\n" .
                    "💰 Số dư: {$customer->balance} USDT"
            );

            Log::channel('transaction')->info('Transaction sent to admin review', [
                'transaction_id' => $transaction->id,
                'customer_id' => $customer->id,
            ]);
        }
    }
}

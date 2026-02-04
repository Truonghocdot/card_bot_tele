<?php

namespace App\Services;

use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Illuminate\Support\Facades\Log;

class TelegramAdminService
{
    protected Api $telegram;
    protected string $adminChatId;

    public function __construct()
    {
        $this->telegram = new Api(config('telegram.bots.admin.token'));
        $this->adminChatId = config('telegram.bots.admin.chat_id');
    }

    /**
     * Send approval request to admin with inline keyboard
     */
    public function sendApprovalRequest(array $transactionData): ?int
    {
        try {
            $text = "🔔 <b>YÊU CẦU MỚI #{$transactionData['id']}</b>\n\n";
            $text .= "👤 Khách hàng: @{$transactionData['username']} ({$transactionData['first_name']})\n";
            $text .= "📝 Mã: <code>{$transactionData['code']}</code>\n";
            $text .= "💰 Số tiền: <b>{$transactionData['amount']} USDT</b>\n";
            $text .= "💳 Số dư hiện tại: {$transactionData['balance']} USDT\n";
            $text .= "⏰ Thời gian: {$transactionData['created_at']}\n\n";
            $text .= "📊 Lịch sử:\n";
            $text .= "- Tổng giao dịch: {$transactionData['total_transactions']}\n";
            $text .= "- Đã approved: {$transactionData['approved_count']}\n";
            $text .= "- Đã từ chối: {$transactionData['rejected_count']}";

            $keyboard = Keyboard::make()
                ->inline()
                ->row([
                    Keyboard::inlineButton([
                        'text' => '✅ Duyệt',
                        'callback_data' => "approve_{$transactionData['id']}"
                    ]),
                    Keyboard::inlineButton([
                        'text' => '❌ Từ chối',
                        'callback_data' => "reject_{$transactionData['id']}"
                    ]),
                ]);

            $response = $this->telegram->sendMessage([
                'chat_id' => $this->adminChatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => $keyboard,
            ]);

            Log::channel('telegram')->info('Admin approval request sent', [
                'transaction_id' => $transactionData['id'],
            ]);

            return $response->getMessageId();
        } catch (TelegramSDKException $e) {
            Log::channel('telegram')->error('Failed to send admin approval request', [
                'transaction_id' => $transactionData['id'],
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Update approval message after admin action
     */
    public function updateApprovalMessage(int $messageId, string $action, array $data): void
    {
        try {
            $text = $action === 'approved'
                ? "✅ <b>ĐÃ DUYỆT</b>\n\n"
                : "❌ <b>ĐÃ TỪ CHỐI</b>\n\n";

            $text .= "Mã: <code>{$data['code']}</code>\n";
            $text .= "Khách hàng: @{$data['username']}\n";
            $text .= "💰 Số tiền: {$data['amount']} USDT\n";
            $text .= "⏰ Thời gian {$action}: {$data['action_time']}\n";
            $text .= "👤 Admin: {$data['admin_name']}";

            $this->telegram->editMessageText([
                'chat_id' => $this->adminChatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);

            Log::channel('telegram')->info('Admin message updated', [
                'message_id' => $messageId,
                'action' => $action,
            ]);
        } catch (TelegramSDKException $e) {
            Log::channel('telegram')->error('Failed to update admin message', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send info notification to admin (no buttons)
     */
    public function sendNotification(string $text): void
    {
        try {
            $this->telegram->sendMessage([
                'chat_id' => $this->adminChatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);

            Log::channel('telegram')->info('Admin notification sent');
        } catch (TelegramSDKException $e) {
            Log::channel('telegram')->error('Failed to send admin notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send auto-approval notification
     */
    public function sendAutoApprovalNotification(array $data): void
    {
        $text = "ℹ️ <b>TỰ ĐỘNG DUYỆT</b>\n\n";
        $text .= "👤 Khách hàng: @{$data['username']}\n";
        $text .= "📝 Mã: <code>{$data['code']}</code>\n";
        $text .= "💰 Số tiền: {$data['amount']} USDT\n";
        $text .= "⏰ Thời gian: {$data['time']}\n";
        $text .= "✅ Lý do: Khách hàng có lịch sử tốt";

        $this->sendNotification($text);
    }

    /**
     * Check bot connection
     */
    public function checkConnection(): bool
    {
        try {
            $this->telegram->getMe();
            return true;
        } catch (TelegramSDKException $e) {
            Log::channel('telegram')->error('Admin bot connection failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Set webhook
     */
    public function setWebhook(string $url, ?string $secretToken = null): bool
    {
        try {
            $params = ['url' => $url];
            if ($secretToken) {
                $params['secret_token'] = $secretToken;
            }

            $this->telegram->setWebhook($params);

            Log::channel('telegram')->info('Admin webhook set', ['url' => $url]);
            return true;
        } catch (TelegramSDKException $e) {
            Log::channel('telegram')->error('Failed to set admin webhook', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get Telegram API instance
     */
    public function getApi(): Api
    {
        return $this->telegram;
    }

    /**
     * Answer callback query
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): void
    {
        try {
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
            ]);
        } catch (TelegramSDKException $e) {
            Log::channel('telegram')->error('Failed to answer callback query', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

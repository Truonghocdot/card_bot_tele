<?php

namespace App\Services;

use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Illuminate\Support\Facades\Log;

class TelegramClientService
{
    protected Api $telegram;

    public function __construct()
    {
        $this->telegram = new Api(config('telegram.bots.client.token'));
    }

    /**
     * Send a message to a chat
     */
    public function sendMessage(string $chatId, string $text, array $options = []): ?array
    {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ], $options);

            $response = $this->telegram->sendMessage($params);

            Log::channel('telegram')->info('Client message sent', [
                'chat_id' => $chatId,
                'text_length' => strlen($text),
            ]);

            return $response->toArray();
        } catch (TelegramSDKException $e) {
            Log::channel('telegram')->error('Failed to send client message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Send payment request message
     */
    public function sendPaymentRequest(string $chatId, float $amount, string $address): void
    {
        $text = "💰 <b>YÊU CẦU NẠP TIỀN</b>\n\n";
        $text .= "Số tiền: <b>{$amount} USDT (TRC20)</b>\n";
        $text .= "Địa chỉ ví: <code>{$address}</code>\n\n";
        $text .= "⚠️ Chỉ chuyển USDT TRC20!\n";
        $text .= "⏳ Hệ thống sẽ tự động xác nhận sau 1-2 phút.";

        $this->sendMessage($chatId, $text);
    }

    /**
     * Send transaction update notification
     */
    public function sendTransactionUpdate(string $chatId, string $status, array $data = []): void
    {
        $text = match ($status) {
            'payment_confirmed' => "✅ <b>Nạp tiền thành công!</b>\n\n" .
                "Số tiền: {$data['amount']} USDT\n" .
                "TX Hash: <code>{$data['tx_hash']}</code>\n" .
                "💰 Số dư mới: {$data['balance']} USDT\n\n" .
                "⏳ Đang chờ admin xác nhận yêu cầu của bạn...",

            'approved' => "✅ <b>YÊU CẦU ĐÃ ĐƯỢC DUYỆT</b>\n\n" .
                "📝 Mã: {$data['code']}\n" .
                "💵 Đã trừ: {$data['amount']} USDT\n" .
                "💰 Số dư còn lại: {$data['balance']} USDT\n" .
                "⏰ Thời gian: {$data['approved_at']}\n\n" .
                "📊 DỮ LIỆU CỦA BẠN:\n{$data['transaction_data']}",

            'rejected' => "❌ <b>YÊU CẦU BỊ TỪ CHỐI</b>\n\n" .
                "📝 Mã: {$data['code']}\n" .
                "⏰ Thời gian: {$data['rejected_at']}\n\n" .
                "💬 Vui lòng liên hệ admin để biết thêm chi tiết.",

            'auto_approved' => "✅ <b>Đã tự động xử lý yêu cầu của bạn!</b>\n\n" .
                "📝 Mã: {$data['code']}\n" .
                "💵 Đã trừ: {$data['amount']} USDT\n" .
                "💰 Số dư: {$data['balance']} USDT\n\n" .
                "📊 Dữ liệu đang được xử lý...",

            default => "ℹ️ Cập nhật trạng thái: {$status}",
        };

        $this->sendMessage($chatId, $text);
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
            Log::channel('telegram')->error('Client bot connection failed', [
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

            Log::channel('telegram')->info('Client webhook set', ['url' => $url]);
            return true;
        } catch (TelegramSDKException $e) {
            Log::channel('telegram')->error('Failed to set client webhook', [
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
}

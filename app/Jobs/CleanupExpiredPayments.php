<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\TelegramClientService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CleanupExpiredPayments implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 120;

    /**
     * Execute the job.
     */
    public function handle(TelegramClientService $telegram): void
    {
        try {
            // Find pending payments older than 24 hours
            $expiredPayments = Payment::where('status', Payment::STATUS_PENDING)
                ->where('created_at', '<', now()->subHours(24))
                ->get();

            $count = 0;

            foreach ($expiredPayments as $payment) {
                // Update payment status to failed
                $payment->update([
                    'status' => Payment::STATUS_FAILED,
                ]);

                // Notify customer
                $customer = $payment->customer;
                if ($customer) {
                    $telegram->sendMessage(
                        $customer->telegram_chat_id,
                        "⏰ <b>THANH TOÁN HẾT HẠN</b>\n\n" .
                            "Yêu cầu thanh toán của bạn đã hết hạn.\n" .
                            "Số tiền: {$payment->amount} USDT\n\n" .
                            "Vui lòng tạo yêu cầu mới nếu bạn vẫn muốn sử dụng dịch vụ."
                    );
                }

                $count++;
            }

            Log::channel('payment')->info('Expired payments cleaned up', [
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            Log::channel('payment')->error('Failed to cleanup expired payments', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

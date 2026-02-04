<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\TelegramClientService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendDataToClient implements ShouldQueue
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
    public function handle(TelegramClientService $telegram): void
    {
        try {
            // Load transaction with customer
            $this->transaction->load('customer');
            $customer = $this->transaction->customer;

            // Prepare data to send
            $transactionData = $this->transaction->data
                ? json_decode($this->transaction->data, true)
                : [];

            // Format transaction data for display
            $formattedData = $this->formatTransactionData($transactionData);

            // Send transaction update to client
            $telegram->sendTransactionUpdate(
                $customer->telegram_chat_id,
                'approved',
                [
                    'code' => $this->transaction->code,
                    'amount' => $this->transaction->amount,
                    'balance' => $customer->balance,
                    'approved_at' => $this->transaction->approved_at->format('d/m/Y H:i'),
                    'transaction_data' => $formattedData,
                ]
            );

            // TODO: If there are files/documents in the data, send them separately
            // Example:
            // if (isset($transactionData['file_url'])) {
            //     $telegram->getApi()->sendDocument([
            //         'chat_id' => $customer->telegram_chat_id,
            //         'document' => $transactionData['file_url'],
            //     ]);
            // }

            Log::channel('transaction')->info('Data sent to client', [
                'transaction_id' => $this->transaction->id,
                'customer_id' => $customer->id,
            ]);
        } catch (\Exception $e) {
            Log::channel('transaction')->error('Failed to send data to client', [
                'transaction_id' => $this->transaction->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Format transaction data for display
     */
    protected function formatTransactionData(array $data): string
    {
        if (empty($data)) {
            return 'Không có dữ liệu';
        }

        $formatted = '';
        foreach ($data as $key => $value) {
            $formatted .= "• " . ucfirst(str_replace('_', ' ', $key)) . ": {$value}\n";
        }

        return $formatted;
    }
}

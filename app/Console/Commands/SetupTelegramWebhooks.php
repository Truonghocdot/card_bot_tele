<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class SetupTelegramWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:setup-webhooks 
                            {--remove : Remove webhooks instead of setting them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup or remove Telegram webhooks for both bots';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $remove = $this->option('remove');

        if ($remove) {
            $this->removeWebhooks();
        } else {
            $this->setupWebhooks();
        }

        return 0;
    }

    /**
     * Setup webhooks for both bots
     */
    protected function setupWebhooks(): void
    {
        $this->info('Setting up Telegram webhooks...');

        $appUrl = config('app.url');

        if (!$appUrl || $appUrl === 'http://localhost') {
            $this->error('APP_URL is not configured properly in .env file');
            $this->error('Please set APP_URL to your public domain');
            return;
        }

        // Setup Client Bot Webhook
        try {
            $clientWebhookUrl = $appUrl . '/api/telegram/client/webhook';
            $clientToken = config('telegram.bots.client.token');

            Telegram::bot('client')->setWebhook([
                'url' => $clientWebhookUrl,
                'secret_token' => hash('sha256', $clientToken),
            ]);

            $this->info("✅ Client bot webhook set to: {$clientWebhookUrl}");
        } catch (\Exception $e) {
            $this->error("❌ Failed to set client bot webhook: {$e->getMessage()}");
        }

        // Setup Admin Bot Webhook
        try {
            $adminWebhookUrl = $appUrl . '/api/telegram/admin/webhook';
            $adminToken = config('telegram.bots.admin.token');

            Telegram::bot('admin')->setWebhook([
                'url' => $adminWebhookUrl,
                'secret_token' => hash('sha256', $adminToken),
            ]);

            $this->info("✅ Admin bot webhook set to: {$adminWebhookUrl}");
        } catch (\Exception $e) {
            $this->error("❌ Failed to set admin bot webhook: {$e->getMessage()}");
        }

        $this->newLine();
        $this->info('Webhook setup completed!');
    }

    /**
     * Remove webhooks for both bots
     */
    protected function removeWebhooks(): void
    {
        $this->info('Removing Telegram webhooks...');

        // Remove Client Bot Webhook
        try {
            Telegram::bot('client')->removeWebhook();
            $this->info('✅ Client bot webhook removed');
        } catch (\Exception $e) {
            $this->error("❌ Failed to remove client bot webhook: {$e->getMessage()}");
        }

        // Remove Admin Bot Webhook
        try {
            Telegram::bot('admin')->removeWebhook();
            $this->info('✅ Admin bot webhook removed');
        } catch (\Exception $e) {
            $this->error("❌ Failed to remove admin bot webhook: {$e->getMessage()}");
        }

        $this->newLine();
        $this->info('Webhook removal completed!');
    }
}

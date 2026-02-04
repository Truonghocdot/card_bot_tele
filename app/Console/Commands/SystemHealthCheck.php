<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class SystemHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:health-check 
                            {--detailed : Show detailed information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check system health and display status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 System Health Check');
        $this->newLine();

        $detailed = $this->option('detailed');
        $allHealthy = true;

        // Check Database
        $dbStatus = $this->checkDatabase();
        $this->displayStatus('Database', $dbStatus);
        if (!$dbStatus) $allHealthy = false;

        // Check Redis
        $redisStatus = $this->checkRedis();
        $this->displayStatus('Redis', $redisStatus);
        if (!$redisStatus) $allHealthy = false;

        // Check Queue
        $queueStatus = $this->checkQueue();
        $this->displayStatus('Queue', $queueStatus);
        if (!$queueStatus) $allHealthy = false;

        // Check Telegram Bots
        $clientBotStatus = $this->checkTelegramBot('client');
        $this->displayStatus('Client Bot', $clientBotStatus);
        if (!$clientBotStatus) $allHealthy = false;

        $adminBotStatus = $this->checkTelegramBot('admin');
        $this->displayStatus('Admin Bot', $adminBotStatus);
        if (!$adminBotStatus) $allHealthy = false;

        if ($detailed) {
            $this->newLine();
            $this->displayDetailedStats();
        }

        $this->newLine();
        if ($allHealthy) {
            $this->info('✅ All systems operational');
        } else {
            $this->error('❌ Some systems are not operational');
        }

        return $allHealthy ? 0 : 1;
    }

    /**
     * Check database connection
     */
    protected function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check Redis connection
     */
    protected function checkRedis(): bool
    {
        try {
            Redis::connection()->ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check queue configuration
     */
    protected function checkQueue(): bool
    {
        try {
            return config('queue.default') === 'redis';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check Telegram bot connection
     */
    protected function checkTelegramBot(string $bot): bool
    {
        try {
            $telegram = app(\Telegram\Bot\Api::class);
            $telegram->setAccessToken(config("telegram.bots.{$bot}.token"));
            $me = $telegram->getMe();
            return isset($me['id']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Display status with icon
     */
    protected function displayStatus(string $service, bool $status): void
    {
        $icon = $status ? '✅' : '❌';
        $statusText = $status ? 'OK' : 'FAILED';
        $this->line("{$icon} {$service}: {$statusText}");
    }

    /**
     * Display detailed statistics
     */
    protected function displayDetailedStats(): void
    {
        $this->info('📊 System Statistics:');
        $this->newLine();

        // Total customers
        $totalCustomers = Customer::count();
        $this->line("Total Customers: {$totalCustomers}");

        // Total transactions
        $totalTransactions = Transaction::count();
        $this->line("Total Transactions: {$totalTransactions}");

        // Pending transactions
        $pendingTransactions = Transaction::where('status', Transaction::STATUS_ADMIN_REVIEW)->count();
        $this->line("Pending Approvals: {$pendingTransactions}");

        // Today's transactions
        $todayTransactions = Transaction::whereDate('created_at', today())->count();
        $this->line("Today's Transactions: {$todayTransactions}");

        // Today's revenue
        $todayRevenue = Transaction::where('status', Transaction::STATUS_APPROVED)
            ->whereDate('approved_at', today())
            ->sum('amount');
        $this->line("Today's Revenue: {$todayRevenue} USDT");

        // Pending payments
        $pendingPayments = Payment::where('status', Payment::STATUS_PENDING)->count();
        $this->line("Pending Payments: {$pendingPayments}");
    }
}

<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Telegram Webhook Routes
Route::prefix('telegram')->group(function () {
    Route::post('/client/webhook', [App\Http\Controllers\ClientBotController::class, 'webhook'])
        ->middleware(['verify.telegram:client', 'rate.limit.api:60'])
        ->name('telegram.client.webhook');

    Route::post('/admin/webhook', [App\Http\Controllers\AdminBotController::class, 'webhook'])
        ->middleware(['verify.telegram:admin', 'rate.limit.api:60'])
        ->name('telegram.admin.webhook');
});

// Payment Webhook Route
Route::post('/payment/webhook', [App\Http\Controllers\PaymentWebhookController::class, 'handle'])
    ->middleware(['verify.payment.signature', 'rate.limit.api:60'])
    ->name('payment.webhook');

// Health Check Endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'services' => [
            'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
            'redis' => Cache::getStore() instanceof \Illuminate\Cache\RedisStore ? 'connected' : 'disconnected',
        ],
    ]);
})->middleware('rate.limit.api:30')->name('health');

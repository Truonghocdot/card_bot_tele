<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bots Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Telegram bots here. You can have multiple bots.
    |
    */

    'bots' => [
        'client' => [
            'token' => env('TELEGRAM_CLIENT_BOT_TOKEN'),
            'webhook_url' => env('APP_URL') . '/api/telegram/client/webhook',
        ],
        'admin' => [
            'token' => env('TELEGRAM_ADMIN_BOT_TOKEN'),
            'webhook_url' => env('APP_URL') . '/api/telegram/admin/webhook',
            'chat_id' => env('TELEGRAM_ADMIN_CHAT_ID'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Bot
    |--------------------------------------------------------------------------
    */
    'default' => 'client',

    /*
    |--------------------------------------------------------------------------
    | Async Requests
    |--------------------------------------------------------------------------
    */
    'async_requests' => env('TELEGRAM_ASYNC_REQUESTS', false),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Handler
    |--------------------------------------------------------------------------
    */
    'http_client_handler' => null,
];

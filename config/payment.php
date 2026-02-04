<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    */

    'api_url' => env('USDT_PAYMENT_API_URL', 'https://api.payment-gateway.com'),
    'api_key' => env('USDT_PAYMENT_API_KEY'),
    'webhook_secret' => env('USDT_PAYMENT_WEBHOOK_SECRET'),
    'wallet_address' => env('USDT_WALLET_ADDRESS', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),
];

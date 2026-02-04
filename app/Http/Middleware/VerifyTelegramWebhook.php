<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $botType): Response
    {
        // Get the appropriate bot token based on bot type
        $token = $botType === 'client'
            ? config('telegram.bots.client.token')
            : config('telegram.bots.admin.token');

        // Verify secret token header
        $secretToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
        $expectedToken = hash('sha256', $token);

        if (!hash_equals($expectedToken, $secretToken ?? '')) {
            Log::channel('telegram')->warning('Invalid Telegram webhook attempt', [
                'ip' => $request->ip(),
                'bot_type' => $botType,
            ]);

            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}

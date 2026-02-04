<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyPaymentSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Webhook-Signature');
        $secret = config('payment.webhook_secret');
        $payload = $request->getContent();

        // Calculate expected signature
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        // Verify signature
        if (!hash_equals($expectedSignature, $signature ?? '')) {
            Log::channel('payment')->warning('Invalid payment webhook signature', [
                'ip' => $request->ip(),
                'signature_provided' => $signature,
            ]);

            abort(403, 'Invalid signature');
        }

        return $next($request);
    }
}

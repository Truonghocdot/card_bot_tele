<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test client webhook requires valid signature
     */
    public function test_client_webhook_requires_valid_signature(): void
    {
        $response = $this->postJson('/api/telegram/client/webhook', [
            'update_id' => 123456,
            'message' => [
                'chat' => ['id' => 123],
                'text' => '/start',
                'from' => ['id' => 123, 'first_name' => 'Test'],
            ],
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test admin webhook requires valid signature
     */
    public function test_admin_webhook_requires_valid_signature(): void
    {
        $response = $this->postJson('/api/telegram/admin/webhook', [
            'update_id' => 123456,
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test payment webhook requires valid signature
     */
    public function test_payment_webhook_requires_valid_signature(): void
    {
        $response = $this->postJson('/api/payment/webhook', [
            'tx_hash' => 'test123',
            'amount' => 10.00,
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test health check endpoint
     */
    public function test_health_check_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
            ])
            ->assertJsonStructure([
                'status',
                'timestamp',
                'services' => [
                    'database',
                    'redis',
                ],
            ]);
    }

    /**
     * Test rate limiting on health check
     */
    public function test_rate_limiting_works(): void
    {
        // Make 31 requests (limit is 30)
        for ($i = 0; $i < 31; $i++) {
            $response = $this->getJson('/api/health');

            if ($i < 30) {
                $response->assertStatus(200);
            } else {
                $response->assertStatus(429); // Too Many Requests
            }
        }
    }
}

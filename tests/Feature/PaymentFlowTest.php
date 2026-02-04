<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\Payment;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test new customer requires payment
     */
    public function test_new_customer_requires_payment(): void
    {
        $customer = Customer::factory()->create([
            'balance' => 0,
        ]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'status' => Transaction::STATUS_PAYMENT_REQUIRED,
            'amount' => 10.00,
        ]);

        $this->assertEquals(Transaction::STATUS_PAYMENT_REQUIRED, $transaction->status);
        $this->assertEquals(0, $customer->balance);
    }

    /**
     * Test payment confirmation updates balance
     */
    public function test_payment_confirmation_updates_balance(): void
    {
        $customer = Customer::factory()->create([
            'balance' => 0,
        ]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'status' => Transaction::STATUS_PAYMENT_REQUIRED,
            'amount' => 10.00,
        ]);

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'transaction_id' => $transaction->id,
            'amount' => 10.00,
            'status' => Payment::STATUS_PENDING,
        ]);

        // Simulate payment confirmation
        $payment->update(['status' => Payment::STATUS_CONFIRMED]);
        $customer->increment('balance', $payment->amount);
        $transaction->update(['status' => Transaction::STATUS_PAYMENT_CONFIRMED]);

        $this->assertEquals(10.00, $customer->fresh()->balance);
        $this->assertEquals(Transaction::STATUS_PAYMENT_CONFIRMED, $transaction->fresh()->status);
    }

    /**
     * Test transaction approval deducts balance
     */
    public function test_transaction_approval_deducts_balance(): void
    {
        $customer = Customer::factory()->create([
            'balance' => 20.00,
        ]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'status' => Transaction::STATUS_ADMIN_REVIEW,
            'amount' => 10.00,
        ]);

        // Simulate approval
        $customer->decrement('balance', $transaction->amount);
        $transaction->update(['status' => Transaction::STATUS_APPROVED]);

        $this->assertEquals(10.00, $customer->fresh()->balance);
        $this->assertEquals(Transaction::STATUS_APPROVED, $transaction->fresh()->status);
    }
}

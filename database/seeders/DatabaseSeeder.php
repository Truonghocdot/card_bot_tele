<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\AdminAction;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create 5 test customers
        $customers = [];
        for ($i = 1; $i <= 5; $i++) {
            $customers[] = Customer::create([
                'telegram_chat_id' => '100000000' . $i,
                'telegram_username' => 'testuser' . $i,
                'telegram_first_name' => 'Test',
                'telegram_last_name' => 'User ' . $i,
                'balance' => rand(0, 100),
                'is_verified' => $i <= 3, // First 3 are verified
            ]);
        }

        // Create 10 transactions with various statuses
        $statuses = [
            Transaction::STATUS_PENDING,
            Transaction::STATUS_PAYMENT_REQUIRED,
            Transaction::STATUS_PAYMENT_CONFIRMED,
            Transaction::STATUS_ADMIN_REVIEW,
            Transaction::STATUS_APPROVED,
            Transaction::STATUS_REJECTED,
        ];

        for ($i = 1; $i <= 10; $i++) {
            $customer = $customers[array_rand($customers)];
            $status = $statuses[array_rand($statuses)];

            $transaction = Transaction::create([
                'customer_id' => $customer->id,
                'code' => 'TEST' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'status' => $status,
                'amount' => 10.00,
                'data' => json_encode(['test_data' => 'Sample data for transaction ' . $i]),
                'approved_at' => $status === Transaction::STATUS_APPROVED ? now() : null,
                'rejected_at' => $status === Transaction::STATUS_REJECTED ? now() : null,
            ]);

            // Create admin action for approved/rejected transactions
            if (in_array($status, [Transaction::STATUS_APPROVED, Transaction::STATUS_REJECTED])) {
                AdminAction::create([
                    'admin_telegram_id' => '999999999',
                    'transaction_id' => $transaction->id,
                    'action' => $status === Transaction::STATUS_APPROVED ? 'approved' : 'rejected',
                    'note' => 'Test ' . $status,
                    'created_at' => now(),
                ]);
            }
        }

        // Create 5 payments
        for ($i = 1; $i <= 5; $i++) {
            $customer = $customers[array_rand($customers)];
            $status = $i <= 3 ? Payment::STATUS_CONFIRMED : Payment::STATUS_PENDING;

            Payment::create([
                'customer_id' => $customer->id,
                'payment_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                'amount' => 10.00,
                'tx_hash' => $status === Payment::STATUS_CONFIRMED ? 'test_hash_' . $i : null,
                'status' => $status,
                'confirmed_at' => $status === Payment::STATUS_CONFIRMED ? now() : null,
            ]);
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Created: 5 customers, 10 transactions, 5 payments');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('code')->unique();
            $table->enum('status', [
                'pending',
                'payment_required',
                'payment_confirmed',
                'admin_review',
                'approved',
                'rejected'
            ])->default('pending');
            $table->decimal('amount', 10, 2);
            $table->string('usdt_tx_hash')->nullable();
            $table->text('data')->nullable(); // JSON data to return to client
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

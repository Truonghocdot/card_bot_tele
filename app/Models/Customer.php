<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'telegram_chat_id',
        'telegram_username',
        'telegram_first_name',
        'telegram_last_name',
        'balance',
        'is_verified',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_verified' => 'boolean',
    ];

    /**
     * Get all transactions for the customer
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all payments for the customer
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get approved transactions count
     */
    public function approvedTransactionsCount(): int
    {
        return $this->transactions()->where('status', 'approved')->count();
    }

    /**
     * Check if customer has any rejected transactions in last N days
     */
    public function hasRecentRejections(int $days = 30): bool
    {
        return $this->transactions()
            ->where('status', 'rejected')
            ->where('rejected_at', '>=', now()->subDays($days))
            ->exists();
    }
}

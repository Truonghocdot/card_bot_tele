<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'customer_id',
        'code',
        'status',
        'amount',
        'usdt_tx_hash',
        'data',
        'paid_at',
        'approved_at',
        'rejected_at',
        'admin_note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PAYMENT_REQUIRED = 'payment_required';
    const STATUS_PAYMENT_CONFIRMED = 'payment_confirmed';
    const STATUS_ADMIN_REVIEW = 'admin_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Get the customer that owns the transaction
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the payment for this transaction
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Get all admin actions for this transaction
     */
    public function adminActions(): HasMany
    {
        return $this->hasMany(AdminAction::class);
    }

    /**
     * Check if transaction is pending approval
     */
    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_ADMIN_REVIEW;
    }

    /**
     * Check if transaction is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if transaction is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}

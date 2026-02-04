<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'admin_telegram_id',
        'transaction_id',
        'action',
        'note',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Action constants
     */
    const ACTION_APPROVED = 'approved';
    const ACTION_REJECTED = 'rejected';

    /**
     * Get the transaction associated with this admin action
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}

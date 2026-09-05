<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bayarcash payment attempt log, one row per payment intent. */
class BayarcashTransaction extends Model
{
    protected $table = 'bayarcash_transactions';

    public const STATUS_CANCELLED = -1;

    public const STATUS_NEW = 0;

    public const STATUS_PENDING = 1;

    public const STATUS_FAILED = 2;

    public const STATUS_SUCCESSFUL = 3;

    public const CHANNEL_FPX = 1;

    public const CHANNEL_DUITNOW_QR = 2;

    public const CHANNEL_DUITNOW_ONLINE = 3;

    public const CHANNEL_CREDIT_CARD = 4;

    public const CHANNEL_SPAYLATER = 5;

    protected $fillable = [
        'order_id', 'order_number', 'payment_intent_id', 'transaction_id',
        'payment_channel', 'amount', 'status', 'callback_payload',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'payment_channel' => 'integer',
            'amount' => 'decimal:2',
            'status' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function isSuccessful(): bool
    {
        return (int) $this->status === self::STATUS_SUCCESSFUL;
    }
}

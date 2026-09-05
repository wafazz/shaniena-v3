<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stock reservation held while a customer is inside the payment flow.
 *
 * Bound to `cart_lock_senangpay`, which is the table the source CartLock model
 * actually used; the identically-shaped `cart_lock` table is dead — it appears
 * in the schema but in no code path.
 */
class CartLock extends Model
{
    use SoftDeletes;

    protected $table = 'cart_lock_senangpay';

    public const STATUS_NEW = 0;

    public const STATUS_CONFIRM = 1;

    public const STATUS_RETURN = 2;

    public const STATUS_CANCEL = 3;

    public const STATUS_ABANDONED = 4;

    protected $fillable = [
        'cart_id', 'session_id', 'p_id', 'pv_id', 'quantity', 'price',
        'weight', 'total_weight', 'currency_sign', 'country_id',
        'locked_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'cart_id' => 'integer',
            'p_id' => 'integer',
            'pv_id' => 'integer',
            'country_id' => 'integer',
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'weight' => 'integer',
            'total_weight' => 'integer',
            'status' => 'integer',
            'locked_date' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'p_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'pv_id');
    }

    /** Live locks for a checkout session — soft-deleted rows are released. */
    public function scopeActiveForSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cart extends Model
{
    use SoftDeletes;

    protected $table = 'cart';

    /** Verified against model/Cart.php in the source project. */
    public const STATUS_UNPAID = 0;
    public const STATUS_PAID = 1;
    public const STATUS_REMOVED = 4;

    /** The source treats 0 and 1 together as the live basket. */
    public const STATUS_ACTIVE = [self::STATUS_UNPAID, self::STATUS_PAID];

    protected $fillable = [
        'session_id', 'p_id', 'pv_id', 'quantity', 'price',
        'weight', 'total_weight', 'currency_sign', 'country_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'weight' => 'integer',
            'total_weight' => 'integer',
            'status' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'p_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'pv_id');
    }

    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    /** Mirrors the source's `status IN (0,1)` active-basket condition. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::STATUS_ACTIVE);
    }

    public function lineTotal(): float
    {
        return (float) $this->price * (int) $this->quantity;
    }
}

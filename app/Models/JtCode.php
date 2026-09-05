<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** J&T's own reference code for a booked order — printed on the label. */
class JtCode extends Model
{
    protected $table = 'jt_code';

    public $timestamps = false;

    protected $fillable = ['order_id', 'awb', 'jt_code'];

    protected function casts(): array
    {
        return ['order_id' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public static function forOrder(int $orderId): ?self
    {
        return static::query()->where('order_id', $orderId)->orderByDesc('id')->first();
    }
}

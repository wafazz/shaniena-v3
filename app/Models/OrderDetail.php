<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDetail extends Model
{
    protected $table = 'order_details';

    public $timestamps = false;

    protected $fillable = ['order_id', 'hash_code', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * The order this hash belongs to.
     *
     * `order_id` is a bigint holding customer_orders.id. The earlier model
     * documented it as the session id and hung a hasMany off it, which the
     * column type does not allow.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function getRouteKeyName(): string
    {
        return 'hash_code';
    }
}

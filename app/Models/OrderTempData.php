<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Checkout form state held between the address step and the payment callback,
 * keyed by session_id. Promoted into customer_orders once payment succeeds.
 */
class OrderTempData extends Model
{
    use SoftDeletes;

    protected $table = 'order_temp_data';

    protected $fillable = [
        'session_id', 'first_name', 'last_name', 'add_1', 'add_2', 'city',
        'state', 'postcode', 'country_name', 'country_id', 'phone', 'email',
        'remark', 'method', 'currency_sign', 'amount', 'shipping_cost', 'status',
    ];

    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
            'amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'status' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(ListCountry::class, 'country_id');
    }

    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }
}

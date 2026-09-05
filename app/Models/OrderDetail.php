<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderDetail extends Model
{
    protected $table = 'order_details';

    public $timestamps = false;

    protected $fillable = ['order_id', 'hash_code', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** Order rows share the session_id stored here as order_id. */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'session_id', 'order_id');
    }

    public function getRouteKeyName(): string
    {
        return 'hash_code';
    }
}

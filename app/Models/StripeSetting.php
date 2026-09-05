<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Stripe keys — single row, id = 1. */
class StripeSetting extends Model
{
    protected $table = 'stripe_setting';

    protected $fillable = ['publish_key', 'secret_key', 'webhook_secret'];

    protected $hidden = ['secret_key', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->find(1);
    }
}

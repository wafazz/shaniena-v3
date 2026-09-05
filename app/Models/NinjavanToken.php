<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cached NinjaVan OAuth token, keyed by mode. Mirrors ninjaVanToken(). */
class NinjavanToken extends Model
{
    protected $table = 'ninjavan_token';

    public const UPDATED_AT = null;

    public const MODE_SANDBOX = 'sandbox';

    public const MODE_PRODUCTION = 'production';

    protected $fillable = ['mode', 'access_token', 'token_type', 'expires_in', 'created_at', 'expired_at'];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'expires_in' => 'integer',
            'created_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expired_at === null || $this->expired_at->isPast();
    }

    public static function activeFor(string $mode): ?self
    {
        return static::query()
            ->where('mode', $mode)
            ->where('expired_at', '>', now())
            ->orderByDesc('id')
            ->first();
    }
}

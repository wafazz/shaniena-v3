<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cached DHL OAuth token. Mirrors dhlToken() / tokenDHLOnSaveSetting(). */
class DhlToken extends Model
{
    protected $table = 'dhl_token';

    public const UPDATED_AT = null;

    protected $fillable = ['token', 'token_type', 'expires_in_seconds', 'created_at', 'expired_at'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'expires_in_seconds' => 'integer',
            'created_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expired_at === null || $this->expired_at->isPast();
    }

    /** The newest token that has not expired yet, if any. */
    public static function active(): ?self
    {
        return static::query()
            ->where('expired_at', '>', now())
            ->orderByDesc('id')
            ->first();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Storefront customer — the `web` auth guard.
 */
class Member extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $table = 'members';

    /**
     * `status` and `verification_status` are string enums in the schema, not
     * integers. Casting them to int made every comparison read 0, so
     * scopeActive() matched nothing and isVerified() was always false.
     */
    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_BANNED = 'banned';

    public const UNVERIFIED = 'unconfirm';

    public const VERIFIED = 'confirm';

    protected $fillable = [
        'email', 'password', 'name', 'phone', 'address_1', 'address_2',
        'city', 'postcode', 'state', 'verification_code', 'verification_status', 'status',
    ];

    protected $hidden = ['password', 'verification_code', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isBanned(): bool
    {
        return $this->status === self::STATUS_BANNED;
    }

    public function isVerified(): bool
    {
        return $this->verification_status === self::VERIFIED;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_email', 'email');
    }
}

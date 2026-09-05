<?php

namespace App\Models;

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

    public const STATUS_ACTIVE = 1;

    protected $fillable = [
        'email', 'password', 'name', 'phone', 'address_1', 'address_2',
        'city', 'postcode', 'state', 'verification_code', 'verification_status', 'status',
    ];

    protected $hidden = ['password', 'verification_code', 'remember_token'];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'verification_status' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isVerified(): bool
    {
        return (int) $this->verification_status === 1;
    }
}

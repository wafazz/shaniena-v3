<?php

namespace App\Models;

use App\Notifications\AdminResetPassword;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Admin / staff user — the `admin` auth guard.
 */
class MemberHq extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $table = 'member_hq';

    public const STATUS_ACTIVE = 1;

    /** Roles, from the designation select in view/Admin/staff-details.php. */
    public const ROLE_SUPER_ADMIN = 1;

    public const ROLE_ACCOUNT = 2;

    public const ROLE_STAFF_ADMIN = 3;

    public const ROLE_STAFF_SALES = 4;

    public const ROLE_STAFF_LOGISTIC = 5;

    public const ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
        self::ROLE_ACCOUNT => 'Account',
        self::ROLE_STAFF_ADMIN => 'Staff Admin',
        self::ROLE_STAFF_SALES => 'Staff Sales',
        self::ROLE_STAFF_LOGISTIC => 'Staff Logistic',
    ];

    protected $fillable = [
        'email', 'password', 'sec_pin', 'f_name', 'l_name', 'phone', 'role', 'status',
    ];

    protected $hidden = ['password', 'sec_pin', 'remember_token'];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'role' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'user_id');
    }

    /**
     * Reset links must land on the admin routes. The stock notification builds
     * its URL from route('password.reset'), which is the customer flow.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new AdminResetPassword($token));
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->f_name} {$this->l_name}");
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? 'Unknown';
    }

    public function isSuperAdmin(): bool
    {
        return (int) $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isActive(): bool
    {
        return (int) $this->status === self::STATUS_ACTIVE;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Support-desk staff login. Legacy: the admin panel authenticates against
 * member_hq (the `admin` guard); this table is only read for reply attribution.
 */
class CsStaffUser extends Model
{
    protected $table = 'cs_staff_users';

    public const UPDATED_AT = null;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_STAFF = 'staff';

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'created_at' => 'datetime',
        ];
    }
}

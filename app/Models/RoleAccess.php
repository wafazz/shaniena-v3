<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One permission row: a page (or button) slug plus the admin user ids allowed
 * to reach it. `allowed_user` is a bracket-delimited id list — "[1][7][12]" —
 * which the source matched with `LIKE '%[id]%'` (roleVerify(),
 * config/function.php:54). The bracket format is kept so the admin matrix
 * editor stays byte-compatible with existing rows.
 *
 * Note: role_access_button has the same shape but is referenced nowhere in the
 * source; button slugs live in role_access alongside page slugs.
 */
class RoleAccess extends Model
{
    protected $table = 'role_access';

    public $timestamps = false;

    protected $fillable = ['page_url', 'name', 'allowed_user', 'sort'];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    public function scopeSorted($query)
    {
        return $query->orderBy('sort');
    }

    /** @return list<int> */
    public function allowedUserIds(): array
    {
        preg_match_all('/\[(\d+)\]/', (string) $this->allowed_user, $matches);

        return array_map('intval', $matches[1]);
    }

    public function allows(int $userId): bool
    {
        return in_array($userId, $this->allowedUserIds(), true);
    }

    /** @param  iterable<int>  $userIds */
    public function setAllowedUserIds(iterable $userIds): void
    {
        $encoded = '';
        foreach ($userIds as $id) {
            $encoded .= '['.(int) $id.']';
        }

        $this->allowed_user = $encoded;
    }
}

<?php

namespace App\Services;

use App\Models\MemberHq;
use App\Models\RoleAccess;
use Illuminate\Support\Facades\Cache;

/**
 * The admin permission matrix, replacing roleVerify() (config/function.php:54).
 *
 * A slug is allowed only when a role_access row exists for it AND that row's
 * bracket list contains the admin's id. Unknown slugs are denied — the source
 * behaved the same way (its LIKE query simply matched nothing), and staying
 * fail-closed is what Phase 7.4 needs. There is deliberately no super-admin
 * bypass: the source grants role 1 nothing implicitly, so adding one here
 * would widen access beyond the migrated data.
 *
 * Slugs cover both pages ("dashboard", "support/tickets") and buttons
 * ("button-delete-product"); the source stored both in role_access.
 */
class PageAccess
{
    /** Source TTL: cache_remember("role:{id}:{md5(url)}", 300, ...). */
    public const CACHE_TTL = 300;

    public function allows(MemberHq $user, string $slug): bool
    {
        $userId = (int) $user->getKey();

        return Cache::remember(
            $this->cacheKey($userId, $slug),
            self::CACHE_TTL,
            fn () => RoleAccess::query()
                ->where('page_url', $slug)
                ->where('allowed_user', 'like', '%['.$userId.']%')
                ->exists(),
        );
    }

    /**
     * Filter a slug list down to the ones this admin may see — used to build
     * the sidebar without a query per menu item.
     *
     * @param  iterable<string>  $slugs
     * @return list<string>
     */
    public function filter(MemberHq $user, iterable $slugs): array
    {
        $allowed = [];
        foreach ($slugs as $slug) {
            if ($this->allows($user, $slug)) {
                $allowed[] = $slug;
            }
        }

        return $allowed;
    }

    /**
     * Drop cached decisions after the matrix editor saves. The cache store is
     * tagless, so keys carry a version stamp that this bumps instead.
     * Pass null to invalidate every admin at once.
     */
    public function flushFor(?int $userId = null): void
    {
        $key = $this->versionKey($userId);
        Cache::forever($key, (int) Cache::get($key, 0) + 1);
    }

    private function cacheKey(int $userId, string $slug): string
    {
        $global = (int) Cache::get($this->versionKey(null), 0);
        $mine = (int) Cache::get($this->versionKey($userId), 0);

        return "role:{$global}.{$mine}:{$userId}:".md5($slug);
    }

    private function versionKey(?int $userId): string
    {
        return $userId === null ? 'role:version:all' : "role:version:{$userId}";
    }
}

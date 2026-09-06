<?php

namespace Database\Seeders;

use App\Models\MemberHq;
use App\Models\RoleAccess;
use App\Services\AdminNavigation;
use App\Services\PageAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * One admin account that can actually use the console.
 *
 * Creating the row is the easy half. There is deliberately no super-admin
 * bypass in PageAccess — the source granted role 1 nothing implicitly, and
 * adding a bypass here would widen access beyond the migrated data — so an
 * admin with no `role_access` rows signs in successfully and then sees an
 * empty sidebar and a 403 on every screen. This grants every slug as well.
 *
 * The slugs are read from the routes themselves rather than from a list kept
 * by hand: every admin route carries `page:<slug>` (Phase 7.4), so a screen
 * added tomorrow is granted by re-running this, and a list that quietly went
 * stale cannot leave the account locked out of one screen.
 *
 *   php artisan db:seed --class=AdminUserSeeder
 *
 * Not called from DatabaseSeeder on purpose: `db:seed` on a server would then
 * create an admin with a known password.
 */
class AdminUserSeeder extends Seeder
{
    private const DEFAULT_EMAIL = 'admin@shaniena.com';

    private const DEFAULT_PASSWORD = 'admin1234';

    public function run(): void
    {
        $email = (string) env('ADMIN_SEED_EMAIL', self::DEFAULT_EMAIL);
        $password = (string) env('ADMIN_SEED_PASSWORD', self::DEFAULT_PASSWORD);

        // A development convenience must not become a production account with
        // a password that is written down in this repository.
        if (app()->isProduction() && $password === self::DEFAULT_PASSWORD) {
            throw new RuntimeException(
                'Refusing to seed the default admin password in production. '
                .'Set ADMIN_SEED_PASSWORD to something real, or create the account from the console.'
            );
        }

        $admin = MemberHq::withTrashed()->firstOrNew(['email' => $email]);
        $existed = $admin->exists;

        $admin->fill([
            'f_name' => (string) env('ADMIN_SEED_FIRST_NAME', 'Shaniena'),
            'l_name' => (string) env('ADMIN_SEED_LAST_NAME', 'Admin'),
            'phone' => (string) env('ADMIN_SEED_PHONE', '60389123807'),
            'role' => MemberHq::ROLE_SUPER_ADMIN,
            'status' => MemberHq::STATUS_ACTIVE,
            // Bcrypt via the model's `hashed` cast — never the source's
            // unsalted SHA-256, which is what member_hq rows still carry until
            // their owner next signs in.
            'password' => $password,
        ]);

        // The source asks for this on a few destructive actions; it is not a
        // second password and is not what signs anyone in.
        $admin->sec_pin = (string) env('ADMIN_SEED_PIN', '1234');

        // An admin who was soft-deleted and is being re-seeded should come
        // back, rather than silently staying deleted with a fresh password.
        $admin->deleted_at = null;
        $admin->save();

        $granted = $this->grantEverything($admin);

        // Grants are cached for five minutes per user and slug; without this
        // a re-run looks like it did nothing for the rest of that window.
        app(PageAccess::class)->flushFor((int) $admin->id);

        $this->command?->info(($existed ? 'Updated' : 'Created')." admin {$email} (id {$admin->id}) with {$granted} permission slugs.");
        $this->command?->line('  Sign in at /admin/login'.($password === self::DEFAULT_PASSWORD ? " with the password {$password}" : ' with the password from ADMIN_SEED_PASSWORD'));
    }

    /**
     * Add this admin to every permission slug the application knows about,
     * creating the rows that do not exist yet.
     *
     * Other admins' grants are preserved: the id is appended to the bracket
     * list, never written over it.
     */
    private function grantEverything(MemberHq $admin): int
    {
        $id = (int) $admin->id;
        $sort = (int) RoleAccess::max('sort');

        foreach ($this->slugs() as $slug) {
            $row = RoleAccess::firstOrNew(['page_url' => $slug]);

            if (! $row->exists) {
                $row->name = Str::headline(str_replace(['/', '-'], ' ', $slug));
                $row->sort = ++$sort;
            }

            if ($row->allows($id)) {
                continue;
            }

            $row->setAllowedUserIds([...$row->allowedUserIds(), $id]);
            $row->save();
        }

        return count($this->slugs());
    }

    /**
     * Every slug the console gates on: the `page:` middleware on the admin
     * routes, plus anything the sidebar links to.
     *
     * @return list<string>
     */
    private function slugs(): array
    {
        $fromRoutes = collect(Route::getRoutes())
            ->flatMap(fn ($route) => $route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'page:'))
            ->map(fn ($middleware) => Str::after($middleware, 'page:'));

        // The sidebar filters itself through the same matrix, so a menu entry
        // whose route is not gated would otherwise never appear.
        $fromNav = collect(app(AdminNavigation::class)->definition())
            ->flatMap(fn (array $entry) => match ($entry['type'] ?? 'item') {
                'group' => collect($entry['items'] ?? [])->pluck('slug')->all(),
                'item' => [$entry['slug'] ?? null],
                default => [],
            })
            ->filter();

        return $fromRoutes->merge($fromNav)->unique()->sort()->values()->all();
    }
}

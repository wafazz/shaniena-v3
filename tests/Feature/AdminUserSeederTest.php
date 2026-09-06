<?php

use App\Models\MemberHq;
use App\Models\RoleAccess;
use App\Services\PageAccess;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * The admin seeder.
 *
 * Creating the row is the easy half — the account is useless without grants,
 * because PageAccess has no super-admin bypass. These tests are mostly about
 * the half that is easy to get wrong: every slug granted, nobody else's grants
 * trampled, and the default password unable to reach production.
 */
function seedAdmin(): MemberHq
{
    (new AdminUserSeeder)->run();

    return MemberHq::where('email', 'admin@shaniena.com')->firstOrFail();
}

/** @return list<string> Every slug an admin route gates on. */
function gatedSlugs(): array
{
    return collect(Route::getRoutes())
        ->flatMap(fn ($route) => $route->gatherMiddleware())
        ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'page:'))
        ->map(fn ($m) => Str::after($m, 'page:'))
        ->unique()
        ->values()
        ->all();
}

it('creates an active super admin whose password is bcrypt, never the source’s SHA-256', function () {
    $admin = seedAdmin();

    expect($admin->status)->toBe(MemberHq::STATUS_ACTIVE)
        ->and($admin->role)->toBe(MemberHq::ROLE_SUPER_ADMIN)
        ->and(Hash::check('admin1234', $admin->password))->toBeTrue()
        ->and($admin->password)->not->toBe(hash('sha256', 'admin1234'))
        ->and(str_starts_with($admin->password, '$2y$'))->toBeTrue();
});

it('signs that admin in and lands them on the dashboard', function () {
    seedAdmin();

    $this->post('/admin/login', ['email' => 'admin@shaniena.com', 'password' => 'admin1234'])
        ->assertRedirect(route('admin.dashboard'));

    expect(auth('admin')->check())->toBeTrue();

    $this->get('/admin/dashboard')->assertOk();
});

it('grants every slug the console gates on, so no screen 403s', function () {
    $admin = seedAdmin();
    $access = app(PageAccess::class);

    $denied = array_values(array_filter(gatedSlugs(), fn ($slug) => ! $access->allows($admin, $slug)));

    expect($denied)->toBe([]);
});

it('can be run again without duplicating a row or an id', function () {
    seedAdmin();
    $rowsAfterFirst = RoleAccess::count();

    $admin = seedAdmin();

    expect(RoleAccess::count())->toBe($rowsAfterFirst)
        ->and(MemberHq::where('email', 'admin@shaniena.com')->count())->toBe(1);

    // The id is appended to a bracket list; appending it twice would still
    // "work" and would quietly corrupt the matrix editor.
    $row = RoleAccess::where('page_url', 'dashboard')->firstOrFail();

    expect($row->allowedUserIds())->toBe([(int) $admin->id]);
});

it('leaves other staff members’ grants alone', function () {
    // An operator who already has exactly one grant.
    $existing = adminWith(['dashboard']);

    $admin = seedAdmin();
    $row = RoleAccess::where('page_url', 'dashboard')->firstOrFail();

    expect($row->allowedUserIds())->toContain((int) $existing->id)
        ->and($row->allowedUserIds())->toContain((int) $admin->id);
});

it('brings back an admin who had been soft-deleted rather than leaving them gone', function () {
    $admin = seedAdmin();
    $admin->delete();

    $reseeded = seedAdmin();

    expect($reseeded->id)->toBe($admin->id)
        ->and($reseeded->trashed())->toBeFalse();
});

it('refuses to seed the known password into production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new AdminUserSeeder)->run())
        ->toThrow(RuntimeException::class, 'Refusing to seed the default admin password in production');

    expect(MemberHq::where('email', 'admin@shaniena.com')->exists())->toBeFalse();
});

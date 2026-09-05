<?php

use App\Models\MemberHq;
use App\Models\RoleAccess;
use App\Services\PageAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function admin(int $status = MemberHq::STATUS_ACTIVE): MemberHq
{
    $id = DB::table('member_hq')->insertGetId([
        'email' => 'admin'.uniqid().'@example.test',
        'password' => bcrypt('secret'),
        'sec_pin' => '1234',
        'f_name' => 'Test',
        'l_name' => 'Admin',
        'phone' => '0100000000',
        'role' => MemberHq::ROLE_SUPER_ADMIN,
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return MemberHq::findOrFail($id);
}

it('parses the bracket-delimited allowed_user list', function () {
    $row = new RoleAccess(['allowed_user' => '[1][12][7]']);

    expect($row->allowedUserIds())->toBe([1, 12, 7])
        ->and($row->allows(12))->toBeTrue()
        ->and($row->allows(2))->toBeFalse();
});

it('does not confuse an id with a longer id that contains it', function () {
    $row = new RoleAccess(['allowed_user' => '[12]']);

    expect($row->allows(1))->toBeFalse()
        ->and($row->allows(2))->toBeFalse()
        ->and($row->allows(12))->toBeTrue();
});

it('round-trips ids back into the source bracket format', function () {
    $row = new RoleAccess;
    $row->setAllowedUserIds([3, 9]);

    expect($row->allowed_user)->toBe('[3][9]');
});

it('grants a slug listed for the admin', function () {
    $user = admin();
    RoleAccess::create([
        'page_url' => 'dashboard',
        'name' => 'Dashboard',
        'allowed_user' => '[999]['.$user->id.']',
        'sort' => 1,
    ]);

    expect($user->can('access', 'dashboard'))->toBeTrue();
});

it('denies a slug the admin is not listed for', function () {
    $user = admin();
    RoleAccess::create([
        'page_url' => 'dashboard',
        'name' => 'Dashboard',
        'allowed_user' => '[999]',
        'sort' => 1,
    ]);

    expect($user->can('access', 'dashboard'))->toBeFalse();
});

it('denies a slug that has no role_access row at all', function () {
    expect(admin()->can('access', 'nonexistent-page'))->toBeFalse();
});

it('denies an inactive admin a slug they are listed for', function () {
    $user = admin(status: 0);
    RoleAccess::create([
        'page_url' => 'dashboard',
        'name' => 'Dashboard',
        'allowed_user' => '['.$user->id.']',
        'sort' => 1,
    ]);

    expect($user->can('access', 'dashboard'))->toBeFalse();
});

it('stops serving a stale decision once the matrix is flushed', function () {
    $user = admin();
    $row = RoleAccess::create([
        'page_url' => 'orders',
        'name' => 'Orders',
        'allowed_user' => '['.$user->id.']',
        'sort' => 1,
    ]);

    expect($user->can('access', 'orders'))->toBeTrue();

    $row->update(['allowed_user' => '']);
    app(PageAccess::class)->flushFor($user->id);

    expect($user->can('access', 'orders'))->toBeFalse();
});

it('blocks a guarded admin route for an admin without the slug', function () {
    Route::middleware(['web', 'auth:admin', 'page:reports'])
        ->get('/_test/reports', fn () => response('ok'));

    $user = admin();

    $this->actingAs($user, 'admin')->get('/_test/reports')->assertForbidden();

    RoleAccess::create([
        'page_url' => 'reports',
        'name' => 'Reports',
        'allowed_user' => '['.$user->id.']',
        'sort' => 1,
    ]);
    app(PageAccess::class)->flushFor($user->id);

    $this->actingAs($user, 'admin')->get('/_test/reports')->assertOk();
});

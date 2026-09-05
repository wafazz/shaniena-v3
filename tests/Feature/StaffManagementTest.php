<?php

use App\Models\MemberHq;
use App\Models\RoleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function otherStaff(): MemberHq
{
    $id = DB::table('member_hq')->insertGetId([
        'email' => 'other'.uniqid().'@example.test',
        'password' => bcrypt('secret'),
        'sec_pin' => '',
        'f_name' => 'Siti',
        'l_name' => 'Nurhaliza',
        'phone' => '0199999999',
        'role' => MemberHq::ROLE_STAFF_SALES,
        'status' => MemberHq::STATUS_ACTIVE,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return MemberHq::findOrFail($id);
}

it('lists staff with their designation and status', function () {
    $admin = queueAdmin('hq-staff');
    otherStaff();

    $this->actingAs($admin, 'admin')->get('/admin/hq-staff')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Staff/Index')->has('staff', 2));
});

it('stores a new account with a bcrypt hash, never sha256', function () {
    $admin = queueAdmin('hq-staff');

    $this->actingAs($admin, 'admin')->post('/admin/hq-staff', [
        'f_name' => 'Farah', 'l_name' => 'Aziz',
        'email' => 'farah@example.test', 'phone' => '0123334444',
        'role' => MemberHq::ROLE_STAFF_LOGISTIC,
        'password' => 'Str0ng-Pass!', 'password_confirmation' => 'Str0ng-Pass!',
    ])->assertRedirect();

    $stored = MemberHq::where('email', 'farah@example.test')->value('password');

    expect(password_get_info($stored)['algoName'])->not->toBe('unknown')
        ->and($stored)->not->toBe(hash('sha256', 'Str0ng-Pass!'));
});

it('enforces the password rules server-side, not just in the browser', function () {
    $admin = queueAdmin('hq-staff');

    $this->actingAs($admin, 'admin')->post('/admin/hq-staff', [
        'f_name' => 'Weak', 'l_name' => 'Password',
        'email' => 'weak@example.test', 'phone' => '01',
        'role' => MemberHq::ROLE_STAFF_SALES,
        'password' => 'abc', 'password_confirmation' => 'abc',
    ])->assertSessionHasErrors('password');

    expect(MemberHq::where('email', 'weak@example.test')->exists())->toBeFalse();
});

it('will not let an admin assign the HQ/Owner role', function () {
    $admin = queueAdmin('hq-staff');

    $this->actingAs($admin, 'admin')->post('/admin/hq-staff', [
        'f_name' => 'Sneaky', 'l_name' => 'Escalation',
        'email' => 'sneaky@example.test', 'phone' => '011',
        'role' => MemberHq::ROLE_SUPER_ADMIN,
        'password' => 'Str0ng-Pass!', 'password_confirmation' => 'Str0ng-Pass!',
    ])->assertSessionHasErrors('role');
});

it('refuses to let anyone edit their own access', function () {
    $admin = queueAdmin('hq-staff');

    $this->actingAs($admin, 'admin')->get("/admin/hq-staff/{$admin->id}")->assertForbidden();
});

it('grants and revokes a permission, and the gate follows immediately', function () {
    $admin = queueAdmin('hq-staff');
    $target = otherStaff();
    RoleAccess::create(['page_url' => 'stock-control', 'name' => 'Stock Control', 'allowed_user' => '', 'sort' => 1]);

    expect($target->can('access', 'stock-control'))->toBeFalse();

    $this->actingAs($admin, 'admin')
        ->post("/admin/hq-staff/{$target->id}/permissions", ['slug' => 'stock-control', 'granted' => true])
        ->assertRedirect();

    expect($target->fresh()->can('access', 'stock-control'))->toBeTrue();

    $this->actingAs($admin, 'admin')
        ->post("/admin/hq-staff/{$target->id}/permissions", ['slug' => 'stock-control', 'granted' => false]);

    expect($target->fresh()->can('access', 'stock-control'))->toBeFalse();
});

it('keeps the bracket format so imported rows stay readable', function () {
    $admin = queueAdmin('hq-staff');
    $target = otherStaff();
    RoleAccess::create(['page_url' => 'dashboard', 'name' => 'Dashboard', 'allowed_user' => '[99]', 'sort' => 1]);

    $this->actingAs($admin, 'admin')
        ->post("/admin/hq-staff/{$target->id}/permissions", ['slug' => 'dashboard', 'granted' => true]);

    expect(RoleAccess::where('page_url', 'dashboard')->value('allowed_user'))->toBe("[99][{$target->id}]");
});

it('logs every permission change', function () {
    $admin = queueAdmin('hq-staff');
    $target = otherStaff();
    RoleAccess::create(['page_url' => 'dashboard', 'name' => 'Dashboard', 'allowed_user' => '', 'sort' => 1]);

    $this->actingAs($admin, 'admin')
        ->post("/admin/hq-staff/{$target->id}/permissions", ['slug' => 'dashboard', 'granted' => true]);

    expect(DB::table('activities')->where('activities', 'permission_activity')->count())->toBe(1);
});

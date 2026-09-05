<?php

use App\Models\MemberHq;
use App\Models\RoleAccess;
use App\Services\PageAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

function loginAdmin(int $status = MemberHq::STATUS_ACTIVE, string $password = 'correct-horse'): MemberHq
{
    $id = DB::table('member_hq')->insertGetId([
        'email' => 'ops@example.test',
        'password' => bcrypt($password),
        'sec_pin' => '1234',
        'f_name' => 'Ops',
        'l_name' => 'Staff',
        'phone' => '0100000000',
        'role' => MemberHq::ROLE_STAFF_ADMIN,
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return MemberHq::findOrFail($id);
}

function grantDashboard(MemberHq $user): void
{
    RoleAccess::create([
        'page_url' => 'dashboard',
        'name' => 'Dashboard',
        'allowed_user' => '['.$user->id.']',
        'sort' => 1,
    ]);

    app(PageAccess::class)->flushFor($user->id);
}

beforeEach(fn () => RateLimiter::clear('ops@example.test|127.0.0.1'));

it('renders the sign-in screen', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Auth/Login'));
});

it('signs a valid admin in and sends them to the dashboard', function () {
    $admin = loginAdmin();
    grantDashboard($admin);

    $this->post('/admin/login', ['email' => $admin->email, 'password' => 'correct-horse'])
        ->assertRedirect('/admin/dashboard');

    $this->assertAuthenticatedAs($admin, 'admin');
});

it('gives the same message for a wrong password as for an unknown email', function () {
    loginAdmin();

    $wrongPassword = $this->post('/admin/login', ['email' => 'ops@example.test', 'password' => 'nope']);
    RateLimiter::clear('ops@example.test|127.0.0.1');
    $unknownEmail = $this->post('/admin/login', ['email' => 'ops@example.test', 'password' => 'nope']);

    $wrongPassword->assertSessionHasErrors(['email' => "Those details don't match an account."]);
    $unknownEmail->assertSessionHasErrors(['email' => "Those details don't match an account."]);
    $this->assertGuest('admin');
});

it('refuses an inactive account and does not leave it signed in', function () {
    $admin = loginAdmin(status: 0);

    $this->post('/admin/login', ['email' => $admin->email, 'password' => 'correct-horse'])
        ->assertSessionHasErrors(['email' => 'This account is inactive. Ask HQ to reactivate it.']);

    $this->assertGuest('admin');
});

it('throttles after five failed attempts', function () {
    loginAdmin();

    foreach (range(1, 5) as $ignored) {
        $this->post('/admin/login', ['email' => 'ops@example.test', 'password' => 'nope']);
    }

    $this->post('/admin/login', ['email' => 'ops@example.test', 'password' => 'correct-horse'])
        ->assertSessionHasErrorsIn('default', ['email']);

    $this->assertGuest('admin');
});

it('sends a guest who asks for the dashboard to the sign-in screen', function () {
    $this->get('/admin/dashboard')->assertRedirect('/admin/login');
});

it('403s an authenticated admin who lacks the dashboard slug', function () {
    $admin = loginAdmin();

    $this->actingAs($admin, 'admin')->get('/admin/dashboard')->assertForbidden();
});

it('shares the nav and the signed-in admin with every page', function () {
    $admin = loginAdmin();
    grantDashboard($admin);

    $this->actingAs($admin, 'admin')->get('/admin/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('auth.admin.email', $admin->email)
            ->where('auth.admin.role', 'Staff Admin')
            ->has('nav'));
});

it('signs out and clears the session', function () {
    $admin = loginAdmin();

    $this->actingAs($admin, 'admin')->post('/admin/logout')->assertRedirect('/admin/login');
    $this->assertGuest('admin');
});

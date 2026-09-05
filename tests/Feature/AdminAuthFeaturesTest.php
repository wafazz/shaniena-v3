<?php

use App\Models\Member;
use App\Models\MemberHq;
use App\Notifications\AdminResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

function makeAdminAccount(string $email = 'ops@example.test', string $password = 'correct-horse'): MemberHq
{
    $id = DB::table('member_hq')->insertGetId([
        'email' => $email,
        'password' => bcrypt($password),
        'sec_pin' => '1234',
        'f_name' => 'Ops',
        'l_name' => 'Staff',
        'phone' => '0100000000',
        'role' => MemberHq::ROLE_STAFF_ADMIN,
        'status' => MemberHq::STATUS_ACTIVE,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return MemberHq::findOrFail($id);
}

it('persists a remember token when an admin logs in with remember me', function () {
    $admin = makeAdminAccount();

    expect(Auth::guard('admin')->attempt(
        ['email' => $admin->email, 'password' => 'correct-horse'],
        remember: true,
    ))->toBeTrue();

    expect($admin->fresh()->remember_token)->not->toBeNull()->not->toBe('');
});

it('persists a remember token for a customer too', function () {
    $member = Member::create([
        'email' => 'shopper@example.test',
        'password' => bcrypt('correct-horse'),
        'name' => 'Shopper',
        'phone' => '0111111111',
        'status' => Member::STATUS_ACTIVE,
    ]);

    expect(Auth::guard('web')->attempt(
        ['email' => $member->email, 'password' => 'correct-horse'],
        remember: true,
    ))->toBeTrue();

    expect($member->fresh()->remember_token)->not->toBeNull();
});

it('sends an admin password reset link on its own broker', function () {
    Notification::fake();
    $admin = makeAdminAccount();

    $status = Password::broker('admins')->sendResetLink(['email' => $admin->email]);

    expect($status)->toBe(Password::RESET_LINK_SENT);
    expect(DB::table('admin_password_reset_tokens')->where('email', $admin->email)->exists())->toBeTrue();

    // The link must land on the admin reset route, not the customer one.
    Notification::assertSentTo($admin, AdminResetPassword::class, function ($notification) use ($admin) {
        $body = $notification->toMail($admin)->render();

        return str_contains($body, route('admin.password.reset', [
            'token' => $notification->token,
            'email' => $admin->email,
        ]));
    });
});

it('resets an admin password and stores it bcrypt-hashed', function () {
    $admin = makeAdminAccount();
    $token = Password::broker('admins')->createToken($admin);

    $status = Password::broker('admins')->reset(
        [
            'email' => $admin->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
            'token' => $token,
        ],
        function (MemberHq $user, string $password) {
            $user->forceFill(['password' => $password])->save();
        },
    );

    expect($status)->toBe(Password::PASSWORD_RESET);

    $stored = $admin->fresh()->password;
    expect(password_get_info($stored)['algoName'])->not->toBe('unknown')
        ->and(Auth::guard('admin')->attempt(['email' => $admin->email, 'password' => 'a-brand-new-password']))->toBeTrue();
});

it('keeps admin and customer reset tokens in separate tables for the same email', function () {
    Notification::fake();
    $shared = 'both@example.test';

    $admin = makeAdminAccount($shared);
    Member::create([
        'email' => $shared,
        'password' => bcrypt('correct-horse'),
        'name' => 'Shopper',
        'phone' => '0111111111',
        'status' => Member::STATUS_ACTIVE,
    ]);

    Password::broker('admins')->sendResetLink(['email' => $shared]);
    Password::broker('members')->sendResetLink(['email' => $shared]);

    // Both requests survive: neither broker overwrote the other's token.
    expect(DB::table('admin_password_reset_tokens')->where('email', $shared)->count())->toBe(1)
        ->and(DB::table('password_reset_tokens')->where('email', $shared)->count())->toBe(1);
});

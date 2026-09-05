<?php

use App\Models\MemberHq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Insert through the query builder so the model's `hashed` cast does not
 * re-hash the value; the point is to store exactly what the legacy app stored.
 */
function makeAdmin(string $storedPassword): MemberHq
{
    $id = DB::table('member_hq')->insertGetId([
        'email' => 'admin@example.test',
        'password' => $storedPassword,
        'sec_pin' => '1234',
        'f_name' => 'Test',
        'l_name' => 'Admin',
        'phone' => '0100000000',
        'role' => MemberHq::ROLE_SUPER_ADMIN,
        'status' => MemberHq::STATUS_ACTIVE,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return MemberHq::findOrFail($id);
}

it('authenticates an admin whose password is a legacy sha256 hash', function () {
    makeAdmin(hash('sha256', 'secret-pass'));

    expect(auth()->guard('admin')->attempt([
        'email' => 'admin@example.test',
        'password' => 'secret-pass',
    ]))->toBeTrue();
});

it('rejects a wrong password against a legacy sha256 hash', function () {
    makeAdmin(hash('sha256', 'secret-pass'));

    expect(auth()->guard('admin')->attempt([
        'email' => 'admin@example.test',
        'password' => 'wrong-pass',
    ]))->toBeFalse();
});

it('upgrades a legacy sha256 hash to bcrypt on successful login', function () {
    $admin = makeAdmin(hash('sha256', 'secret-pass'));
    expect($admin->password)->toHaveLength(64);

    auth()->guard('admin')->attempt([
        'email' => 'admin@example.test',
        'password' => 'secret-pass',
    ]);

    $stored = $admin->fresh()->password;

    expect($stored)->toStartWith('$2y$')
        ->and(Hash::check('secret-pass', $stored))->toBeTrue();
});

it('still authenticates once the password is bcrypt', function () {
    makeAdmin(Hash::make('secret-pass'));

    expect(auth()->guard('admin')->attempt([
        'email' => 'admin@example.test',
        'password' => 'secret-pass',
    ]))->toBeTrue();
});

it('does not authenticate an empty password', function () {
    makeAdmin(hash('sha256', 'secret-pass'));

    expect(auth()->guard('admin')->attempt([
        'email' => 'admin@example.test',
        'password' => '',
    ]))->toBeFalse();
});

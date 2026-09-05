<?php

namespace App\Http\Controllers\Admin;

use App\Auth\LegacyHashUserProvider;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in admin's own profile and password.
 *
 * Separate from StaffController on purpose: that one refuses to touch the
 * current user, because editing your own *access* is what needs blocking.
 * Editing your own name and password is exactly what this screen is for.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $admin = $request->user('admin');

        return Inertia::render('Admin/Account/Profile', [
            'profile' => [
                'reference' => '#'.str_pad((string) $admin->id, 6, '0', STR_PAD_LEFT),
                'f_name' => $admin->f_name,
                'l_name' => $admin->l_name,
                'email' => $admin->email,
                'phone' => $admin->phone,
                'role' => $admin->roleLabel(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');

        $data = $request->validate([
            'f_name' => ['required', 'string', 'max:255'],
            'l_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('member_hq', 'email')->ignore($admin->id)],
            'phone' => ['required', 'string', 'max:255'],
        ]);

        $admin->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function editPassword(): Response
    {
        return Inertia::render('Admin/Account/Password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->symbols()],
        ]);

        // An admin who has not signed in since the migration still has a
        // SHA-256 hash, and Hash::check() throws outright on one — so the
        // legacy branch has to come first, as it does in the auth provider.
        $stored = (string) $admin->password;

        $verified = LegacyHashUserProvider::isLegacySha256($stored)
            ? hash_equals($stored, hash('sha256', $data['current_password']))
            : Hash::check($data['current_password'], $stored);

        if (! $verified) {
            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }

        $admin->forceFill(['password' => Hash::make($data['password'])])->save();

        Activity::record((int) $admin->getKey(), 'Changed their own password', "member_hq|{$admin->id}", 'account_activity');

        return back()->with('success', 'Password changed.');
    }
}

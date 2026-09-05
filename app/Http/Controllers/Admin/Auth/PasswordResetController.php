<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\MemberHq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-service password reset for admins.
 *
 * New in the rebuild: the source rendered a "Forgot Password?" link that had
 * no route behind it. Uses the dedicated `admins` broker so a staff reset can
 * never collide with a customer's on the same email address.
 */
class PasswordResetController extends Controller
{
    private const BROKER = 'admins';

    public function showRequestForm(): Response
    {
        return Inertia::render('Admin/Auth/ForgotPassword');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $status = Password::broker(self::BROKER)->sendResetLink($request->only('email'));

        // Always the same confirmation, whether or not the address exists —
        // otherwise this page enumerates staff accounts.
        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => 'A reset link was sent recently. Check your inbox before requesting another.',
            ]);
        }

        return back()->with('success', 'If that address belongs to a staff account, a reset link is on its way.');
    }

    public function showResetForm(Request $request, string $token): Response
    {
        return Inertia::render('Admin/Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->symbols()],
        ]);

        $status = Password::broker(self::BROKER)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (MemberHq $admin, string $password) {
                // The `hashed` cast bcrypts it, retiring any legacy SHA-256.
                $admin->forceFill(['password' => $password])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'That reset link is invalid or has expired. Request a new one.',
            ]);
        }

        return redirect()->route('admin.login')->with('success', 'Password changed. Sign in with the new one.');
    }
}

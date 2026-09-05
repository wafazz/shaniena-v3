<?php

namespace App\Http\Controllers\Shop\Auth;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Notifications\VerifyCustomerEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer sign-in and registration on the `web` guard.
 *
 * The source had this markup commented out in both checkout views, so the
 * controller behind it was unreachable, and its "Forgot password" and
 * "Resend code" links pointed at routes that did not exist.
 */
class CustomerAuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function showLogin(): Response
    {
        return Inertia::render('Shop/Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => "Those details don't match an account.",
            ]);
        }

        $member = Auth::guard('web')->user();

        if ($member->isBanned()) {
            return $this->reject($request, 'This account has been suspended. Please contact support.');
        }

        if (! $member->isVerified()) {
            return $this->reject($request, 'Please verify your email first.', '/verify-email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended('/account');
    }

    public function showRegister(): Response
    {
        return Inertia::render('Shop/Auth/Register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:100', Rule::unique('members', 'email')],
            'phone' => ['required', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $code = (string) random_int(100000, 999999);

        $member = Member::create($data + [
            'verification_code' => $code,
            'verification_status' => Member::UNVERIFIED,
            'status' => Member::STATUS_INACTIVE,
        ]);

        $member->notify(new VerifyCustomerEmail($code));

        return redirect('/verify-email')
            ->with('pending_email', $member->email)
            ->with('success', "We've emailed a code to {$member->email}.");
    }

    public function showVerify(Request $request): Response
    {
        return Inertia::render('Shop/Auth/Verify', [
            'email' => $request->session()->get('pending_email', ''),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $member = Member::where('email', $data['email'])->first();

        // One message whether the address is unknown or the code is wrong —
        // otherwise this page confirms which addresses have accounts.
        if (! $member || ! hash_equals((string) $member->verification_code, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'That code is not right. Check the email and try again.',
            ]);
        }

        $member->update([
            'verification_status' => Member::VERIFIED,
            'status' => Member::STATUS_ACTIVE,
            'verification_code' => null,
        ]);

        return redirect('/login')->with('success', 'Email verified. You can sign in now.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $member = Member::where('email', $data['email'])->where('verification_status', Member::UNVERIFIED)->first();

        if ($member) {
            $code = (string) random_int(100000, 999999);
            $member->update(['verification_code' => $code]);
            $member->notify(new VerifyCustomerEmail($code));
        }

        // Always the same answer, so this cannot be used to enumerate accounts.
        return back()->with('success', 'If that address is waiting to be verified, a new code is on its way.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function reject(Request $request, string $message, string $to = '/login'): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();

        return redirect($to)->with('error', $message);
    }
}

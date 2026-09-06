<?php

namespace App\Providers;

use App\Auth\LegacyHashUserProvider;
use App\Models\MemberHq;
use App\Services\PageAccess;
use App\Services\Payments\BayarcashGateway;
use App\Services\Payments\CodGateway;
use App\Services\Payments\PaymentGateways;
use App\Services\Payments\SenangPayGateway;
use App\Services\Shipping\Couriers;
use App\Services\Shipping\DhlGateway;
use App\Services\Shipping\JtExpressGateway;
use App\Services\Shipping\NinjaVanGateway;
use App\Services\StoreSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StoreSettings::class);
        $this->app->singleton(PageAccess::class);

        // The payment channels this store can offer. Each decides for itself
        // whether it is switched on and configured.
        $this->app->singleton(PaymentGateways::class, fn ($app) => new PaymentGateways([
            $app->make(CodGateway::class),
            $app->make(SenangPayGateway::class),
            $app->make(BayarcashGateway::class),
        ]));

        $this->app->singleton(Couriers::class, fn ($app) => new Couriers([
            $app->make(JtExpressGateway::class),
            $app->make(NinjaVanGateway::class),
            $app->make(DhlGateway::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('legacy-hash', function ($app, array $config) {
            return new LegacyHashUserProvider($app['hash'], $config['model']);
        });

        $this->registerGates();
        $this->registerRateLimiters();
    }

    /**
     * Rate limits on everything a stranger can reach without signing in.
     *
     * The source had none anywhere: a script could ask for a thousand password
     * resets, place orders in a loop, or replay a payment callback as fast as
     * the network allowed.
     */
    private function registerRateLimiters(): void
    {
        // Password reset sends mail to an address the caller chose, so it is
        // both an email-bomb and an account-enumeration vector.
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinutes(15, 5)->by(Str::lower((string) $request->input('email'))),
            Limit::perMinutes(15, 10)->by($request->ip()),
        ]);

        // Anything that sends a mail or an SMS on demand.
        RateLimiter::for('verification', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by($request->user('web')?->getAuthIdentifier() ?: $request->ip()),
        ]);

        // Placing an order is cheap for us and expensive for a gateway, and a
        // loop here fills the orders table with abandoned rows.
        RateLimiter::for('checkout', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
        ]);

        // Order id plus email. Without a limit that pair is brute-forceable.
        RateLimiter::for('order-lookup', fn (Request $request) => [
            Limit::perMinute(15)->by($request->ip()),
        ]);

        // Public form that writes a row and can carry attachments.
        RateLimiter::for('support', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by($request->ip()),
        ]);

        // The basket drawer reads this whenever it opens, and a shopper may
        // open it repeatedly — so it is generous, but it is the one storefront
        // endpoint that returns data without a form or a token behind it.
        RateLimiter::for('cart-summary', fn (Request $request) => [
            Limit::perMinute(60)->by($request->ip()),
        ]);

        // One open tab polls this every 20 seconds — 3 a minute. The ceiling
        // is for the tab somebody left open in fifteen windows, not for a
        // shopper.
        RateLimiter::for('visitor-counts', fn (Request $request) => [
            Limit::perMinute(60)->by($request->ip()),
        ]);

        // Generous, because a gateway retrying a callback is normal and being
        // throttled would lose a real payment — but not unbounded.
        RateLimiter::for('gateway-callback', fn (Request $request) => [
            Limit::perMinute(120)->by($request->ip()),
        ]);
    }

    /**
     * The admin permission matrix. `access` covers page slugs and `perform`
     * covers button slugs; both read the same role_access table, exactly as
     * roleVerify() did.
     */
    private function registerGates(): void
    {
        $check = fn (MemberHq $user, string $slug): bool => $user->isActive()
            && $this->app->make(PageAccess::class)->allows($user, $slug);

        Gate::define('access', $check);
        Gate::define('perform', $check);
    }
}

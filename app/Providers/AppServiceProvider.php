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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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

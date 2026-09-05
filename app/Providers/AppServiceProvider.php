<?php

namespace App\Providers;

use App\Auth\LegacyHashUserProvider;
use App\Models\MemberHq;
use App\Services\PageAccess;
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

<?php

use App\Http\Middleware\EnsurePageAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleStorefrontRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleStorefrontRequests::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Both are opaque, non-secret values: a 40-char random cart token and a
        // country id. Encrypting them buys nothing — guessing a token yields an
        // empty cart — and leaving them plain keeps them debuggable. The source
        // stored its country cookie in the clear too.
        $middleware->encryptCookies(except: [
            HandleStorefrontRequests::CART_COOKIE,
            HandleStorefrontRequests::COUNTRY_COOKIE,
        ]);

        // CSRF is exempt for gateway callbacks ONLY. They are server-to-server
        // POSTs with no session, and each one is authenticated by its own
        // signature instead.
        $middleware->validateCsrfTokens(except: [
            'payment/callback/*',
        ]);

        $middleware->alias([
            'page' => EnsurePageAccess::class,
        ]);

        // There is no customer-facing `login` route yet, and admin routes must
        // bounce to the admin form regardless.
        // Each surface bounces to its own sign-in and its own landing page.
        $middleware->redirectGuestsTo(fn ($request) => $request->is('admin', 'admin/*')
            ? route('admin.login')
            : route('shop.login'));

        $middleware->redirectUsersTo(fn ($request) => $request->is('admin', 'admin/*')
            ? route('admin.dashboard')
            : route('shop.account'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

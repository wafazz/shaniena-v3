<?php

use Illuminate\Support\Facades\Route;
use Tests\Support\Screens;

/**
 * 8.8, as a test rather than a checklist.
 *
 * A hand-kept list of 66 screens goes stale the first time someone adds one.
 * These compare the screen inventory against the routes that actually exist,
 * so a new screen either joins the smoke walk or is recorded as covered by a
 * named test — and doing neither fails the suite.
 */
it('smoke-tests every admin screen, or records what covers it', function () {
    $smoked = collect(Screens::admin())->map(fn ($screen) => ltrim($screen[1], '/'))->values();
    $elsewhere = collect(Screens::adminCoveredElsewhere())->keys();

    $uncovered = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'admin'))
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        // Auth and self-service screens have their own dedicated tests.
        ->filter(fn ($route) => collect($route->gatherMiddleware())
            ->contains(fn ($m) => is_string($m) && str_starts_with($m, 'page:')))
        ->map(fn ($route) => $route->uri())
        ->reject(fn ($uri) => $smoked->contains($uri) || $elsewhere->contains($uri))
        ->values();

    expect($uncovered->all())->toBeEmpty();
});

it('points every screen in the inventory at a route that exists', function () {
    $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => '/'.$route->uri());

    foreach (Screens::admin() as $name => [$slug, $path, $component]) {
        expect($uris->contains($path))->toBeTrue("Admin screen '{$name}' points at {$path}, which is not a route");
    }

    foreach (Screens::storefront() as $name => [$path, $component]) {
        // Laravel stores the root route's uri as "/", so it comes back as "//"
        // from the map above.
        $uri = $path === '/' ? '//' : $path;

        expect($uris->contains($uri))->toBeTrue("Storefront screen '{$name}' points at {$path}, which is not a route");
    }
});

it('names a real component file for every screen', function () {
    $components = collect(Screens::admin())->map(fn ($s) => $s[2])
        ->merge(collect(Screens::storefront())->map(fn ($s) => $s[1]))
        ->unique();

    foreach ($components as $component) {
        expect(is_file(resource_path("js/Pages/{$component}.vue")))
            ->toBeTrue("No component file for {$component}");
    }
});

it('names a real test for every screen covered outside the smoke walk', function () {
    foreach (Screens::adminCoveredElsewhere() as $uri => $test) {
        expect(is_file(base_path("tests/Feature/{$test}.php")))
            ->toBeTrue("{$uri} says it is covered by {$test}, which does not exist");
    }
});

<?php

use App\Services\Storefront\Themes;
use App\Services\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Screens;

uses(RefreshDatabase::class);

/**
 * The storefront theme HQ chooses in Store Settings.
 *
 * Two things have to hold for a theme switch to be safe: the shop must serve
 * exactly one theme's stylesheet, and every page must render under either one.
 * The second is why the smoke walk below runs twice — a theme that only the
 * homepage was ever looked at under is a theme that breaks on checkout.
 */
function useTheme(string $theme): void
{
    app(StoreSettings::class)->set(Themes::SETTING, $theme);
}

// --- the setting ---------------------------------------------------------

it('serves Ashion until somebody chooses otherwise', function () {
    expect(app(Themes::class)->current())->toBe('ashion')
        ->and(app(Themes::class)->css())->toBe('resources/sass/storefront.scss');
});

it('falls back to Ashion when the stored theme is not one we ship', function () {
    // A hand-edited row, or a theme that was removed in a deploy. The shop
    // still has to render.
    useTheme('a-theme-that-was-deleted');

    expect(app(Themes::class)->current())->toBe('ashion');
});

it('refuses to store a theme that is not on the list', function () {
    $admin = adminWith(['store-setting']);

    $this->actingAs($admin, 'admin')
        ->put('/admin/store-setting', ['settings' => [Themes::SETTING => '../../etc/passwd']])
        ->assertSessionHasErrors('settings.'.Themes::SETTING);

    expect(app(Themes::class)->current())->toBe('ashion');
});

it('lets an operator switch the shop over', function () {
    $admin = adminWith(['store-setting']);

    $this->actingAs($admin, 'admin')
        ->put('/admin/store-setting', ['settings' => [Themes::SETTING => 'electro']])
        ->assertRedirect();

    expect(app(Themes::class)->current())->toBe('electro');
});

it('offers both themes on the settings screen, with what each one is', function () {
    $admin = adminWith(['store-setting']);

    $this->actingAs($admin, 'admin')->get('/admin/store-setting')->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('themes', 2)
            ->where('themes.0.key', 'ashion')
            ->where('themes.1.key', 'electro')
            ->whereNot('themes.1.description', ''));
});

// --- what the browser is served -----------------------------------------

it('serves one theme’s stylesheet and never the other', function () {
    shopCountry();

    $ashion = $this->get('/')->getContent();

    expect($ashion)->toMatch('~/build/assets/storefront-[^"]+\.css~')
        ->and($ashion)->not->toMatch('~/build/assets/electro-[^"]+\.css~')
        ->and($ashion)->toContain('Montserrat');

    useTheme('electro');
    $electro = $this->get('/')->getContent();

    expect($electro)->toMatch('~/build/assets/electro-[^"]+\.css~')
        ->and($electro)->not->toMatch('~/build/assets/storefront-[^"]+\.css~')
        // Each theme brings its own faces, so neither pays for the other's.
        ->and($electro)->toContain('Roboto')
        ->and($electro)->toContain('theme-electro');
})->skip(fn () => ! servingBuiltAssets(), 'vite dev server is running');

it('tells the components which theme they are rendering in', function () {
    shopCountry();
    useTheme('electro');

    $this->get('/')->assertInertia(fn ($page) => $page->where('shop.theme', 'electro'));
});

// --- every page, under both themes --------------------------------------

it('renders every storefront screen under both themes', function (string $theme) {
    useTheme($theme);
    shopCountry();

    foreach (Screens::storefront() as $name => [$path, $component]) {
        $this->get($path)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component($component));
    }
})->with(['ashion', 'electro']);

it('keeps the basket working whichever theme is on', function () {
    useTheme('electro');
    [$product, $variant] = sellable();

    $this->keep($this->post('/cart', [
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'quantity' => 2,
    ]));

    $this->get('/cart')->assertOk()->assertInertia(fn ($page) => $page
        ->where('summary.items.0.quantity', 2)
        ->where('shop.theme', 'electro'));
});

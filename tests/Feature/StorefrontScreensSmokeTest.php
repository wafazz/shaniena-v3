<?php

use App\Models\ListCountry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Screens;

uses(RefreshDatabase::class);

dataset('storefront screens', Screens::storefront());

/** A configured store with nothing in it yet — a fresh deployment. */
function openShop(): ListCountry
{
    return ListCountry::firstOrCreate(
        ['name' => 'Malaysia'],
        ['sign' => 'MYR', 'rate' => 1, 'phone_code' => '+60', 'status' => ListCountry::STATUS_ACTIVE],
    );
}

/**
 * An empty catalogue is where the "undefined index" and "call on null" bugs
 * live — the source crashed on a shop with no products because every row
 * assumed at least one variant.
 */
it('renders for a stranger who has not chosen a country', function (string $path, string $component) {
    openShop();

    $this->get($path)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with('storefront screens');

it('renders once a country is chosen and there is still no stock', function (string $path, string $component) {
    $country = openShop();

    $this->withUnencryptedCookie('shaniena_country', (string) $country->id)
        ->get($path)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with('storefront screens');

it('sends a shopper to the picker when no country is configured at all', function () {
    // Not a valid deployment, but it must not throw — a half-finished install
    // is exactly when someone opens the shop to see whether it works.
    $this->get('/checkout')->assertRedirect('/select-country');
});

it('keeps every page showing one shopper their own state out of the index', function () {
    // The directive is declared in the component, so this reads the source.
    // Track and Support were using a plain <Head> and carried no robots meta
    // at all — both show a customer's own order or ticket after a lookup.
    foreach (Screens::storefrontNoIndex() as $page) {
        $source = file_get_contents(resource_path("js/Pages/Shop/{$page}.vue"));

        // toContain takes further needles, not a message, so the assertion
        // carries the page name on its own line instead.
        expect([$page, str_contains($source, ':index="false"')])->toBe([$page, true]);
    }
});

it('serves a real 404 rather than an error for a product that is gone', function () {
    $this->get('/product/no-such-product')->assertNotFound();
    $this->get('/categories/no-such-category')->assertNotFound();
    $this->get('/brands/no-such-brand')->assertNotFound();
});

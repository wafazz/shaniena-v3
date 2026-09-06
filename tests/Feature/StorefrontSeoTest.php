<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shares an absolute canonical without the query string', function () {
    shopCountry();

    $this->get('/shop?q=serum&sort=name')
        ->assertInertia(fn ($page) => $page
            ->where('shop.canonical', fn ($url) => str_starts_with($url, 'http') && ! str_contains($url, '?')));
});

it('serves the storefront root view with the storefront bundle', function () {
    shopCountry();

    $body = $this->get('/')->assertOk()->getContent();

    // No <title> in the Blade: @inertiaHead emits the real one, and a second
    // would win with crawlers.
    // Asset URLs are content-hashed by the build, so match the entry name.
    expect(substr_count($body, '<title'))->toBeLessThanOrEqual(1)
        ->and($body)->toMatch('~/build/assets/storefront-[^"]+\.js~')
        ->and($body)->not->toMatch('~/build/assets/app-[^"]+\.js~')
        ->and($body)->toContain('manifest.webmanifest');
})->skip(fn () => ! servingBuiltAssets(), 'vite dev server is running');

it('declares the language, unlike the source template', function () {
    shopCountry();

    // Ashion shipped lang="zxx" — "no linguistic content".
    expect($this->get('/')->getContent())->toContain('<html lang="en"')->not->toContain('lang="zxx"');
});

it('gives every product page its own title and description', function () {
    shopCountry();
    $product = Product::factory()->named('Hydra Glow Cleanser')->create(['description' => 'A gentle daily cleanser.']);

    $this->get("/product/{$product->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('product.name', 'Hydra Glow Cleanser')
            ->where('product.description', 'A gentle daily cleanser.'));
});

it('routes products by slug, not by id', function () {
    shopCountry();
    $product = Product::factory()->create();

    $this->get("/product/{$product->slug}")->assertOk();
    $this->get("/product/{$product->id}")->assertNotFound();
});

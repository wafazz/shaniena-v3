<?php

use App\Models\Cart;
use App\Models\CountryPrice;
use App\Models\ListCountry;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sellingCountry(): ListCountry
{
    return ListCountry::create([
        'name' => 'Malaysia', 'sign' => 'MYR', 'rate' => 1,
        'phone_code' => '+60', 'status' => ListCountry::STATUS_ACTIVE,
    ]);
}

it('renders the shop through the storefront root view, not the admin one', function () {
    sellingCountry();

    $response = $this->get('/')->assertOk();

    // The console bundle must never reach a customer. Asset URLs are
    // content-hashed by the build, so match the entry name rather than the
    // source path — which is only emitted while a Vite dev server is running.
    expect($response->getContent())
        ->toMatch('~/build/assets/storefront-[^"]+\.js~')
        ->not->toMatch('~/build/assets/app-[^"]+\.js~');
});

it('shares the selling country, nav and cart badge with every shop page', function () {
    sellingCountry();

    $this->get('/')->assertInertia(fn ($page) => $page
        ->component('Shop/Home')
        ->where('shop.country.sign', 'MYR')
        ->where('shop.cartCount', 0)
        ->has('shop.nav.categories')
        ->has('shop.footer'));
});

it('prices products from list_country_product_price, not the variant columns', function () {
    $country = sellingCountry();
    $product = Product::factory()->named('Hydra Glow Cleanser')->create();
    CountryPrice::create([
        'product_id' => $product->id, 'country_id' => $country->id,
        'market_price' => 79.90, 'sale_price' => 59.90,
    ]);

    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('newArrivals.0.price', '59.90')
        ->where('newArrivals.0.was', '79.90')
        ->where('newArrivals.0.currency', 'MYR'));
});

it('shows no struck-through price when nothing is discounted', function () {
    $country = sellingCountry();
    $product = Product::factory()->create();
    CountryPrice::create([
        'product_id' => $product->id, 'country_id' => $country->id,
        'market_price' => 59.90, 'sale_price' => 59.90,
    ]);

    $this->get('/')->assertInertia(fn ($page) => $page->where('newArrivals.0.was', null));
});

it('lists new arrivals newest first', function () {
    sellingCountry();
    $oldest = Product::factory()->named('Oldest')->create();
    $newest = Product::factory()->named('Newest')->create();

    // The source sorted `ORDER BY created_at` with no direction, so its
    // "New Arrival" row showed the oldest products in the catalogue.
    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('newArrivals.0.name', $newest->name)
        ->where('newArrivals.1.name', $oldest->name));
});

it('ranks best sellers by paid quantity', function () {
    sellingCountry();
    $quiet = Product::factory()->named('Quiet')->create();
    $popular = Product::factory()->named('Popular')->create();

    foreach ([[$quiet, 1], [$popular, 9]] as [$product, $qty]) {
        Cart::create([
            'session_id' => uniqid(), 'p_id' => $product->id, 'pv_id' => 0,
            'quantity' => $qty, 'price' => 10, 'weight' => 1, 'total_weight' => 1,
            'currency_sign' => 'MYR', 'country_id' => 1, 'status' => Cart::STATUS_PAID,
        ]);
    }

    $this->get('/')->assertInertia(fn ($page) => $page->where('bestSellers.0.name', 'Popular'));
});

it('copes with no selling country configured', function () {
    Product::factory()->create();

    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
        ->where('shop.country', null)
        ->where('promos', []));
});

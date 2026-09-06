<?php

use App\Http\Middleware\HandleStorefrontRequests;
use App\Models\Cart;
use App\Models\CountryPrice;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockControl;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The basket drawer.
 *
 * Two things carry it, and both are server-side: the summary endpoint it reads
 * when it opens, and the `default_variant_id` on a product card that decides
 * whether a tile may add straight to the basket or has to send the shopper to
 * the product page to choose.
 *
 * `get()` rather than `getJson()` throughout: Laravel's JSON test helper sends
 * only the default cookies, not the unencrypted ones, and the basket is keyed
 * to an unencrypted `cart_token`. A browser sends it either way — the helper
 * would make a working endpoint look broken.
 */

/** The request the drawer makes: a GET that asks for JSON. */
function drawerSummary($test)
{
    return $test->get('/cart/summary', ['Accept' => 'application/json']);
}

// --- the endpoint the drawer reads ---------------------------------------

it('returns the caller’s own basket, with what the drawer needs to draw it', function () {
    [$product, $variant] = sellable('Hydra Glow Cleanser');

    $this->keep($this->post('/cart', [
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'quantity' => 2,
    ]));

    $summary = drawerSummary($this)->assertOk()->json();

    expect($summary['items'])->toHaveCount(1)
        ->and($summary['items'][0]['name'])->toBe('Hydra Glow Cleanser')
        ->and($summary['items'][0]['quantity'])->toBe(2)
        ->and($summary['items'][0]['line_total'])->toBe(119.80)
        // The stepper in the drawer needs the per-variant cap, or it would
        // offer a quantity the server is about to refuse.
        ->and($summary['items'][0])->toHaveKey('max_purchase')
        ->and($summary['items'][0])->toHaveKey('image')
        ->and($summary['subtotal'])->toBe(119.80);
});

it('never shows one shopper the contents of another’s basket', function () {
    [$product, $variant] = sellable();

    $this->keep($this->post('/cart', [
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'quantity' => 1,
    ]));

    expect(Cart::count())->toBe(1);

    // A different browser: a different cart token, and nothing to see.
    $summary = $this->withUnencryptedCookie(HandleStorefrontRequests::CART_COOKIE, str_repeat('b', 40))
        ->get('/cart/summary', ['Accept' => 'application/json'])
        ->assertOk()
        ->json();

    expect($summary['items'])->toBe([])
        ->and((float) $summary['subtotal'])->toBe(0.0);
});

it('loads the basket’s product images without a query per line', function () {
    foreach (['One', 'Two', 'Three'] as $name) {
        [$product, $variant] = sellable($name);

        $this->keep($this->post('/cart', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]));
    }

    DB::enableQueryLog();
    drawerSummary($this)->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Lines, products, images, variants, prices and the country — a fixed set.
    // Without the eager load, `imageUrl()` falls back to a query per product.
    expect($queries)->toBeLessThanOrEqual(10);
});

// --- what a card may do on its own ---------------------------------------

it('lets a card add straight to the basket only when there is one variant to add', function () {
    [, $variant] = sellable('Single Variant Serum');

    $card = collect($this->get('/')->viewData('page')['props']['newArrivals'] ?? [])
        ->firstWhere('name', 'Single Variant Serum');

    expect($card['default_variant_id'])->toBe($variant->id);
});

it('sends a shopper with a choice to make to the product page instead', function () {
    $country = shopCountry();
    $product = Product::factory()->named('Two Shade Foundation')->create(['weight' => 300]);

    foreach (['Light', 'Deep'] as $shade) {
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'variant_name' => $shade, 'sku' => "SKU-{$shade}",
            'price_retail' => 89.90, 'price_sale' => 69.90, 'max_purchase' => 3,
        ]);

        StockControl::create([
            'p_id' => $product->id, 'pv_id' => $variant->id,
            'stock_in' => 10, 'stock_out' => 0, 'comment' => 'Opening',
        ]);
    }

    CountryPrice::create([
        'product_id' => $product->id, 'country_id' => $country->id,
        'market_price' => 89.90, 'sale_price' => 69.90,
    ]);

    $card = collect($this->get('/')->viewData('page')['props']['newArrivals'] ?? [])
        ->firstWhere('name', 'Two Shade Foundation');

    // Picking a shade on the customer's behalf is how the wrong item ends up
    // in an order.
    expect($card['in_stock'])->toBeTrue()
        ->and($card['default_variant_id'])->toBeNull();
});

it('offers no one-click add for something that is out of stock', function () {
    [, $variant] = sellable('Sold Out Balm', stock: 0);

    $card = collect($this->get('/')->viewData('page')['props']['newArrivals'] ?? [])
        ->firstWhere('name', 'Sold Out Balm');

    expect($card['in_stock'])->toBeFalse()
        ->and($card['default_variant_id'])->toBeNull()
        ->and($variant->id)->toBeInt();
});

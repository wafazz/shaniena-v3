<?php

use App\Http\Middleware\HandleStorefrontRequests;
use App\Models\Cart;
use App\Models\CodCharge;
use App\Models\CountryPrice;
use App\Models\Order;
use App\Models\PostageCost;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StateSetting;
use App\Models\StockControl;
use App\Services\Storefront\Basket;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- country gate --------------------------------------------------------

it('lets a shopper land on the shop without picking a country first', function () {
    shopCountry();

    // The source redirected every storefront URL to a country picker.
    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('Shop/Home'));
});

it('remembers the chosen country for 60 days', function () {
    $country = shopCountry();

    $this->post('/select-country', ['country_id' => $country->id])
        ->assertRedirect()
        ->assertPlainCookie(HandleStorefrontRequests::COUNTRY_COOKIE, (string) $country->id);
});

it('empties the basket when the country changes', function () {
    [$product, $variant] = sellable();
    $this->keep($this->post('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]));

    expect(Cart::count())->toBe(1);

    // The source left the cart alone, so it kept the old currency and prices.
    $this->get('/change-country')->assertRedirect('/select-country');

    expect(Cart::whereIn('status', Cart::STATUS_ACTIVE)->count())->toBe(0);
});

// --- product -------------------------------------------------------------

it('opens a product on the first in-stock variant', function () {
    $country = shopCountry();
    $product = Product::factory()->named('Serum')->create();

    $soldOut = ProductVariant::create(['product_id' => $product->id, 'variant_name' => '30ml', 'sku' => 'A',
        'price_retail' => 10, 'price_sale' => 9, 'max_purchase' => 5]);
    $available = ProductVariant::create(['product_id' => $product->id, 'variant_name' => '50ml', 'sku' => 'B',
        'price_retail' => 10, 'price_sale' => 9, 'max_purchase' => 5]);

    StockControl::create(['p_id' => $product->id, 'pv_id' => $available->id, 'stock_in' => 5, 'stock_out' => 0, 'comment' => '']);
    CountryPrice::create(['product_id' => $product->id, 'country_id' => $country->id, 'market_price' => 10, 'sale_price' => 9]);

    $this->get("/product/{$product->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('defaultVariantId', $available->id));
});

it('counts basket reservations against available stock', function () {
    [$product, $variant] = sellable(stock: 10);

    $this->keep($this->post('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 4]));

    $this->get("/product/{$product->slug}")
        ->assertInertia(fn ($page) => $page->where('variants.0.stock', 6));
});

// --- cart ----------------------------------------------------------------

it('enforces the purchase cap across separate additions', function () {
    [$product, $variant] = sellable(cap: 3);

    $this->keep($this->post('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2]));

    // Adding one at a time must not walk past the cap.
    $this->post('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2])
        ->assertSessionHasErrors('quantity');

    expect((int) Cart::first()->quantity)->toBe(2);
});

it('refuses more than is in stock', function () {
    [$product, $variant] = sellable(stock: 2, cap: 99);

    $this->post('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 5])
        ->assertSessionHasErrors('quantity');

    expect(Cart::count())->toBe(0);
});

it('will not let one session touch another session cart line', function () {
    [$product, $variant] = sellable();
    $this->post('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]);
    $line = Cart::first();

    $line->update(['session_id' => 'someone-else']);

    $this->delete("/cart/{$line->id}")->assertForbidden();
});

// --- pricing and totals --------------------------------------------------

it('prices the basket from the database, not from the request', function () {
    $country = shopCountry();
    [$product, $variant] = sellable(sale: 59.90);

    $this->keep($this->post('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2]));

    // Someone tampers with the stored line price.
    Cart::query()->update(['price' => 0.01]);

    $summary = app(Basket::class)->summary(Cart::first()->session_id, $country);

    // The live catalogue price wins.
    expect($summary['subtotal'])->toBe(119.80);
});

it('charges the first kilo flat and rounds extra weight up', function () {
    $country = shopCountry();
    StateSetting::create(['country_id' => $country->id, 'state_code' => 'SGR', 'name' => 'Selangor', 'shipping_zone' => 1]);
    PostageCost::create(['country_id' => $country->id, 'shipping_zone' => 1, 'currency' => 'MYR',
        'first_kilo' => 6.50, 'next_kilo' => 3.00]);

    $basket = app(Basket::class);

    expect($basket->postage($country, 1, 900))->toBe(6.50)
        ->and($basket->postage($country, 1, 1000))->toBe(6.50)
        ->and($basket->postage($country, 1, 1100))->toBe(9.50)
        ->and($basket->postage($country, 1, 2000))->toBe(9.50)
        ->and($basket->postage($country, 1, 2100))->toBe(12.50);
});

it('applies the COD benchmark at the threshold itself', function () {
    $country = shopCountry();
    CodCharge::create(['country_id' => $country->id, 'shipping_zone' => '1',
        'benchmark_amount' => 100, 'cod_fee_below' => 10, 'cod_fee_above' => 8]);

    $basket = app(Basket::class);

    expect($basket->codFee($country, 1, 99.99))->toBe(10.0)
        ->and($basket->codFee($country, 1, 100.00))->toBe(8.0);
});

it('cannot work out postage until it knows the destination', function () {
    $country = shopCountry();
    [$product, $variant] = sellable();
    $this->keep($this->post('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]));

    StateSetting::create(['country_id' => $country->id, 'state_code' => 'SGR', 'name' => 'Selangor', 'shipping_zone' => 1]);

    $summary = app(Basket::class)->summary(Cart::first()->session_id, $country, null);

    expect($summary['postage_known'])->toBeFalse()->and($summary['postage'])->toBe(0.0);
});

// --- checkout ------------------------------------------------------------

it('rejects a checkout address that is missing a field', function () {
    $country = shopCountry();
    StateSetting::create(['country_id' => $country->id, 'state_code' => 'SGR', 'name' => 'Selangor', 'shipping_zone' => 1]);
    [$product, $variant] = sellable();
    $this->keep($this->post('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]));

    // The source guarded with && instead of ||, so three of four fields passed.
    $this->post('/checkout/address', [
        'first_name' => 'Aisyah', 'last_name' => '', 'address_1' => '1 Jalan Test',
        'city' => 'Dengkil', 'state' => 'Selangor', 'postcode' => '43800',
        'phone' => '0123456789', 'email' => 'a@example.test', 'courier_service' => 'J&T Express',
    ])->assertSessionHasErrors('last_name');
});

it('rejects a state that does not belong to the country', function () {
    $country = shopCountry();
    StateSetting::create(['country_id' => $country->id, 'state_code' => 'SGR', 'name' => 'Selangor', 'shipping_zone' => 1]);
    [$product, $variant] = sellable();
    $this->keep($this->post('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]));

    $this->post('/checkout/address', [
        'first_name' => 'A', 'last_name' => 'B', 'address_1' => '1 Jalan Test',
        'city' => 'Dengkil', 'state' => 'Narnia', 'postcode' => '43800',
        'phone' => '01', 'email' => 'a@example.test', 'courier_service' => 'J&T Express',
    ])->assertSessionHasErrors('state');
});

// --- support -------------------------------------------------------------

it('keeps the old support-ticket urls working', function () {
    // Both themes' footers linked to /customer/support-ticket, which was the
    // source's path and never a route here — so the link 404'd on every page.
    $this->get('/customer/support-ticket')->assertRedirect('/support');
    $this->get('/support-ticket')->assertRedirect('/support');
});

it('points the footer links at the real url rather than through the redirect', function () {
    // The footer is client-side, so asserting on the response body passes
    // whether the href is right or wrong — it is simply not in there. Read
    // the components, the way PwaAssetsTest reads the root view.
    foreach (['Ashion', 'Electro'] as $theme) {
        $source = (string) file_get_contents(
            resource_path("js/Storefront/Themes/{$theme}/Layout.vue")
        );

        expect($source)->not->toContain('/customer/support-ticket');
    }
});

// --- tracking ------------------------------------------------------------

it('needs both the order number and the matching email', function () {
    $order = Order::factory()->create(['customer_email' => 'buyer@example.test']);

    $this->get("/track-order?order={$order->id}&email=someone@else.test")
        ->assertInertia(fn ($page) => $page->where('order', null)->where('searched', true));

    $this->get("/track-order?order={$order->id}&email=buyer@example.test")
        ->assertInertia(fn ($page) => $page->where('order.reference', $order->reference()));
});

it('places the order on the delivery line it has actually reached', function () {
    $order = Order::factory()->create([
        'customer_email' => 'buyer@example.test',
        'status' => Order::STATUS_IN_DELIVERY,
    ]);

    $this->get("/track-order?order={$order->id}&email=buyer@example.test")
        ->assertInertia(fn ($page) => $page
            ->where('order.stage', 2)
            ->where('order.stages', ['New Order', 'Processing', 'In Delivery', 'Completed']));
});

it('keeps an order that never reached the line off it', function () {
    // A cancelled order drawn as two-thirds of the way to delivery would be
    // a progress bar telling the customer something untrue.
    $order = Order::factory()->create([
        'customer_email' => 'buyer@example.test',
        'status' => Order::STATUS_CANCELLED,
    ]);

    $this->get("/track-order?order={$order->id}&email=buyer@example.test")
        ->assertInertia(fn ($page) => $page
            ->where('order.stage', null)
            ->where('order.status', 'Cancelled'));
});

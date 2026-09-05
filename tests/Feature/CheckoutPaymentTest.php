<?php

use App\Http\Middleware\HandleStorefrontRequests;
use App\Mail\OrderPlaced;
use App\Models\Cart;
use App\Models\CodCharge;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\PostageCost;
use App\Models\StateSetting;
use App\Services\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(RefreshDatabase::class);

/** A basket with a saved address, ready to pay for. */
function readyToPay(TestCase $t, float $sale = 59.90, int $qty = 2): array
{
    $country = shopCountry();
    StateSetting::create(['country_id' => $country->id, 'state_code' => 'SGR', 'name' => 'Selangor', 'shipping_zone' => 1]);
    PostageCost::create(['country_id' => $country->id, 'shipping_zone' => 1, 'currency' => 'MYR',
        'first_kilo' => 6.50, 'next_kilo' => 3.00]);
    CodCharge::create(['country_id' => $country->id, 'shipping_zone' => '1',
        'benchmark_amount' => 100, 'cod_fee_below' => 10, 'cod_fee_above' => 8]);

    [$product, $variant] = sellable(sale: $sale);

    $t->keep($t->post('/cart', [
        'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => $qty,
    ]));

    $t->keep($t->post('/checkout/address', [
        'first_name' => 'Aisyah', 'last_name' => 'Rahman', 'address_1' => '1 Jalan Test',
        'city' => 'Dengkil', 'state' => 'Selangor', 'postcode' => '43800',
        'phone' => '+60123456789', 'email' => 'buyer@example.test',
        'courier_service' => 'J&T Express',
    ]));

    return [$country, $product, $variant];
}

beforeEach(function () {
    app(StoreSettings::class)->set('cod_enabled', '1');
});

it('places a COD order priced by the server', function () {
    Mail::fake();
    readyToPay($this, sale: 59.90, qty: 2);

    $this->post('/pay/cod')->assertRedirect();

    $order = Order::firstOrFail();

    // 2 x 59.90 = 119.80, postage 6.50 on 1kg, COD fee 8.00 at/above the
    // 100.00 benchmark.
    expect((float) $order->total_price)->toBe(119.80)
        ->and((float) $order->postage_cost)->toBe(6.50)
        ->and((float) $order->myr_value_include_postage)->toBe(134.30)
        ->and($order->payment_channel)->toBe(Order::CHANNEL_COD)
        // COD is a live order, not one waiting on a gateway.
        ->and((int) $order->status)->toBe(Order::STATUS_NEW);
});

it('emails the customer their confirmation', function () {
    Mail::fake();
    readyToPay($this);

    $this->post('/pay/cod');

    Mail::assertQueued(OrderPlaced::class, fn ($mail) => $mail->hasTo('buyer@example.test'));
});

it('gives the order an unguessable reference hash', function () {
    Mail::fake();
    readyToPay($this);

    $this->post('/pay/cod');

    $hash = OrderDetail::firstOrFail()->hash_code;
    $order = Order::firstOrFail();

    // The source used sha256(id . "_" . name . "_" . timestamp) — derived
    // entirely from guessable inputs.
    expect($hash)->toHaveLength(64)
        ->and($hash)->not->toBe(hash('sha256', $order->id.'_Aisyah_'.now()->toDateTimeString()));
});

it('issues a fresh basket so a paid cart is not reused', function () {
    Mail::fake();
    readyToPay($this);

    $before = Cart::firstOrFail()->session_id;
    $response = $this->post('/pay/cod');

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($c) => $c->getName() === HandleStorefrontRequests::CART_COOKIE);

    expect($cookie?->getValue())->not->toBe($before);
});

it('refuses a channel that is switched off', function () {
    app(StoreSettings::class)->set('cod_enabled', '0');
    readyToPay($this);

    $this->post('/pay/cod')->assertNotFound();

    expect(Order::count())->toBe(0);
});

it('refuses to place an order with no saved address', function () {
    shopCountry();
    [$product, $variant] = sellable();
    $this->keep($this->post('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]));

    $this->post('/pay/cod')->assertRedirect('/checkout');

    expect(Order::count())->toBe(0);
});

it('refuses an empty basket', function () {
    shopCountry();

    $this->post('/pay/cod')->assertRedirect('/checkout');

    expect(Order::count())->toBe(0);
});

it('records the country conversion rate in force at the time', function () {
    Mail::fake();
    [$country] = readyToPay($this);
    $country->update(['rate' => 4.25]);

    $this->post('/pay/cod');

    // The source always wrote 1, discarding the rate entirely.
    expect((float) Order::firstOrFail()->to_myr_rate)->toBe(4.25);
});

// --- callbacks -----------------------------------------------------------

it('rejects a callback for an unknown channel', function () {
    $this->post('/payment/callback/nonsense')->assertNotFound();
});

it('does not require a CSRF token on a gateway callback', function () {
    // Server-to-server: there is no session and no token to send. A 419 here
    // would mean every real callback fails.
    $this->post('/payment/callback/cod')->assertStatus(400);
});

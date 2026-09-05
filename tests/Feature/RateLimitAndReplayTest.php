<?php

use App\Models\Cart;
use App\Models\Order;
use App\Services\Storefront\PlaceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(fn () => RateLimiter::clear(''));

// ---------------------------------------------------------------------------
// 7.6 Rate limiting, exercised rather than asserted structurally
// ---------------------------------------------------------------------------

it('stops a password-reset flood against one address', function () {
    // Five in fifteen minutes, then the door shuts. The source had no limit at
    // all: a script could mail an address as fast as the network allowed.
    for ($i = 0; $i < 5; $i++) {
        $this->post('/admin/forgot-password', ['email' => 'victim@example.test'])
            ->assertStatus(302);
    }

    $this->post('/admin/forgot-password', ['email' => 'victim@example.test'])
        ->assertStatus(429);
});

it('lets a different address through while one is throttled', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/admin/forgot-password', ['email' => 'victim@example.test']);
    }

    // Keyed on the address as well as the IP, so one target being hammered
    // does not lock out everyone behind the same NAT for the whole window.
    $this->post('/admin/forgot-password', ['email' => 'someone-else@example.test'])
        ->assertStatus(302);
});

it('caps order lookups so the id and email pair cannot be brute-forced', function () {
    for ($i = 0; $i < 15; $i++) {
        $this->get('/track-order?order='.$i.'&email=guess@example.test');
    }

    $this->get('/track-order?order=999&email=guess@example.test')->assertStatus(429);
});

it('caps how fast support tickets can be opened', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/support', [
            'customer_name' => 'Spam',
            'customer_email' => 'spam@example.test',
            'title' => 'Hello '.$i,
            'description' => 'Body',
        ]);
    }

    $this->post('/support', [
        'customer_name' => 'Spam',
        'customer_email' => 'spam@example.test',
        'title' => 'One too many',
        'description' => 'Body',
    ])->assertStatus(429);
});

// ---------------------------------------------------------------------------
// 7.9 Callback replay and idempotency
// ---------------------------------------------------------------------------

function awaitingOrder(): Order
{
    $order = Order::factory()->create([
        'status' => Order::STATUS_AWAITING_PAYMENT,
        'payment_channel' => Order::CHANNEL_SENANGPAY,
        'payment_code' => '',
    ]);

    Cart::create([
        'session_id' => $order->session_id,
        'p_id' => 1, 'pv_id' => 0, 'quantity' => 1, 'price' => 49.90,
        'weight' => 200, 'total_weight' => 200,
        'currency_sign' => 'MYR', 'country_id' => 1,
        'status' => Cart::STATUS_UNPAID,
    ]);

    return $order;
}

it('confirms an order exactly once however many callbacks arrive', function () {
    $order = awaitingOrder();
    $placeOrder = app(PlaceOrder::class);

    expect($placeOrder->confirm($order, 'TXN-1'))->toBeTrue();

    // A gateway retrying is normal. The second and third must be no-ops, or
    // the customer gets a confirmation email per retry.
    expect($placeOrder->confirm($order, 'TXN-1'))->toBeFalse()
        ->and($placeOrder->confirm($order->fresh(), 'TXN-2'))->toBeFalse();

    $order->refresh();

    expect((int) $order->status)->toBe(Order::STATUS_NEW)
        // And a replay must not overwrite the code from the payment that
        // actually settled.
        ->and($order->payment_code)->toBe('TXN-1');
});

it('marks the basket paid once and leaves it alone on a replay', function () {
    $order = awaitingOrder();
    $placeOrder = app(PlaceOrder::class);

    $placeOrder->confirm($order, 'TXN-1');

    expect(Cart::where('session_id', $order->session_id)->value('status'))->toBe(Cart::STATUS_PAID);

    $placeOrder->confirm($order->fresh(), 'TXN-1');

    expect(Cart::where('session_id', $order->session_id)->count())->toBe(1);
});

it('refuses to confirm an order that is not awaiting payment', function () {
    $delivered = Order::factory()->create(['status' => Order::STATUS_IN_DELIVERY]);

    // A replayed callback against an order that has since shipped must not
    // walk it back to New.
    expect(app(PlaceOrder::class)->confirm($delivered, 'TXN-9'))->toBeFalse()
        ->and((int) $delivered->fresh()->status)->toBe(Order::STATUS_IN_DELIVERY);
});

it('leaves a failed payment where the retry bot can still find it', function () {
    $order = awaitingOrder();

    app(PlaceOrder::class)->fail($order);

    // Status 10 is one bucket: the dashboard calls it Failed Payment, the
    // SenangPay bot polls it as "ask the gateway again". Moving it out would
    // take the order out of the bot's reach for good.
    expect((int) $order->fresh()->status)->toBe(Order::STATUS_AWAITING_PAYMENT);

    // And it must still be confirmable if the payment lands late.
    expect(app(PlaceOrder::class)->confirm($order->fresh(), 'LATE'))->toBeTrue();
});

it('refreshes the caller copy so it cannot act on a stale status', function () {
    $order = awaitingOrder();

    app(PlaceOrder::class)->confirm($order, 'TXN-1');

    // Without the refresh the caller would still hold status 10 and could send
    // a "payment failed" mail straight after confirming.
    expect((int) $order->status)->toBe(Order::STATUS_NEW);
});

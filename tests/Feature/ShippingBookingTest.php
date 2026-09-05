<?php

use App\Exceptions\ShipmentFailed;
use App\Models\Activity;
use App\Models\DhlSetting;
use App\Models\DhlToken;
use App\Models\JtSetting;
use App\Models\NinjavanToken;
use App\Models\Order;
use App\Services\Shipping\BookShipment;
use App\Services\Shipping\Couriers;
use App\Services\Shipping\DhlGateway;
use App\Services\Shipping\JtExpressGateway;
use App\Services\Shipping\NinjaVanGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function jtConfigured(): JtSetting
{
    $setting = new JtSetting([
        'production_sandbox' => JtSetting::MODE_SANDBOX,
        'url_sandbox' => 'https://sandbox.jtexpress.test/order',
        'username_sanbox' => 'rozeyana',
        'password_sandbox' => 'sandbox-pass',
        'cuscode_sandbox' => 'J0086000000',
        'key_sandbox' => 'sandbox-signing-key',
        'url_production' => 'https://api.jtexpress.test/order',
        'username_production' => 'rozeyana',
        'password_production' => 'live-pass',
        'cuscode_production' => 'J0086111111',
        'key_production' => 'live-signing-key',
    ]);

    $setting->id = 1;
    $setting->save();

    return $setting;
}

function dhlConfigured(): DhlSetting
{
    $setting = new DhlSetting([
        'production_sandbox' => DhlSetting::MODE_SANDBOX,
        'clientid' => 'client-live',
        'password' => 'secret-live',
        'format' => 'json',
        'url' => 'https://api.dhl.test',
        'clientid_test' => 'client-sandbox',
        'password_test' => 'secret-sandbox',
        'format_test' => 'json',
        'url_test' => 'https://sandbox.dhl.test',
    ]);

    $setting->id = 1;
    $setting->save();

    return $setting;
}

// ---------------------------------------------------------------------------
// Signatures
// ---------------------------------------------------------------------------

it('signs J&T payloads over the hex md5 digest', function () {
    $json = '{"detail":[{"orderid":"ROZEYANA-00000042"}]}';
    $key = 'sandbox-signing-key';

    // base64 of the 32-character HEX digest — what the working source does,
    // not the raw-binary digest J&T's own spec asks for.
    expect(app(JtExpressGateway::class)->sign($json, $key))
        ->toBe(base64_encode(md5($json.$key)))
        ->not->toBe(base64_encode(md5($json.$key, true)));
});

it('sends the signature J&T can verify against the exact bytes posted', function () {
    jtConfigured();
    Http::fake(['*' => Http::response(['details' => [['status' => 'success', 'awb_no' => '630000111222']]])]);

    $order = Order::factory()->create(['courier_service' => 'J&T Express', 'awb_number' => '']);
    app(JtExpressGateway::class)->book($order);

    Http::assertSent(function ($request) {
        $json = $request['data_param'];

        return $request['data_sign'] === base64_encode(md5($json.'sandbox-signing-key'));
    });
});

// ---------------------------------------------------------------------------
// Courier dispatch
// ---------------------------------------------------------------------------

it('matches a courier whatever case the order stored it in', function () {
    $couriers = app(Couriers::class);

    // The source wrote back 'J&T EXPRESS' but dispatched on 'J&T Express',
    // so a re-dispatch fell through to "no courier assigned".
    expect($couriers->for('J&T EXPRESS'))->toBeInstanceOf(JtExpressGateway::class)
        ->and($couriers->for('j&t express'))->toBeInstanceOf(JtExpressGateway::class)
        ->and($couriers->for('ninjavan'))->toBeInstanceOf(NinjaVanGateway::class)
        ->and($couriers->for('Pos Laju'))->toBeNull()
        ->and($couriers->for(''))->toBeNull()
        ->and($couriers->for(null))->toBeNull();
});

// ---------------------------------------------------------------------------
// BookShipment
// ---------------------------------------------------------------------------

it('books an order and records the consignment', function () {
    jtConfigured();
    Http::fake(['*' => Http::response(['details' => [['status' => 'success', 'awb_no' => '630000111222']]])]);

    $order = Order::factory()->create([
        'courier_service' => 'J&T Express',
        'awb_number' => '',
        'status' => Order::STATUS_NEW,
    ]);

    $message = app(BookShipment::class)->handle($order, 7);

    expect($message)->toContain('630000111222');

    $order->refresh();

    expect($order->awb_number)->toBe('630000111222')
        ->and($order->tracking_url)->toContain('630000111222')
        ->and($order->courier_service)->toBe('J&T Express')
        ->and((int) $order->status)->toBe(Order::STATUS_PROCESSING);

    expect(Activity::query()->where('activities', 'shipping_activity')->count())->toBe(1)
        ->and(Activity::query()->value('description'))->toContain('630000111222');
});

it('never books the same order twice', function () {
    jtConfigured();
    Http::fake();

    $order = Order::factory()->withAwb('630000999888')->create(['courier_service' => 'J&T Express']);

    $message = app(BookShipment::class)->handle($order, 7);

    expect($message)->toContain('already has AWB');
    Http::assertNothingSent();
});

it('refuses an order with no courier chosen', function () {
    $order = Order::factory()->create(['courier_service' => '', 'awb_number' => '']);

    expect(fn () => app(BookShipment::class)->handle($order, 7))
        ->toThrow(ShipmentFailed::class, 'no courier service assigned');
});

it('refuses a courier whose credentials are missing', function () {
    // jt_setting row absent entirely.
    $order = Order::factory()->create(['courier_service' => 'J&T Express', 'awb_number' => '']);

    expect(fn () => app(BookShipment::class)->handle($order, 7))
        ->toThrow(ShipmentFailed::class, 'not configured');
});

it('leaves the order untouched when the courier refuses', function () {
    jtConfigured();
    Http::fake(['*' => Http::response(['details' => [['status' => 'fail', 'reason' => 'bad postcode']]])]);

    $order = Order::factory()->create([
        'courier_service' => 'J&T Express',
        'awb_number' => '',
        'status' => Order::STATUS_NEW,
    ]);

    expect(fn () => app(BookShipment::class)->handle($order, 7))->toThrow(ShipmentFailed::class);

    $order->refresh();

    expect($order->awb_number)->toBe('')
        ->and((int) $order->status)->toBe(Order::STATUS_NEW);
});

// ---------------------------------------------------------------------------
// Token caching
// ---------------------------------------------------------------------------

it('reuses a DHL token that is still comfortably valid', function () {
    dhlConfigured();

    DhlToken::create([
        'token' => 'cached-token',
        'token_type' => 'Bearer',
        'expires_in_seconds' => 3600,
        'created_at' => now(),
        'expired_at' => now()->addHour(),
    ]);

    Http::fake(['*' => Http::response([
        'manifestResponse' => ['bd' => ['shipmentItems' => [['deliveryConfirmationNo' => 'DHL00099']]]],
    ])]);

    $order = Order::factory()->create(['courier_service' => 'DHL eCommerce', 'awb_number' => '']);
    app(DhlGateway::class)->book($order);

    // Only the shipment call — no OAuth round trip.
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v3/Shipment'));
});

it('renews a DHL token that is about to expire', function () {
    dhlConfigured();

    DhlToken::create([
        'token' => 'nearly-stale',
        'token_type' => 'Bearer',
        'expires_in_seconds' => 3600,
        'created_at' => now()->subHour(),
        // Inside the five-minute renewal margin: still "active" by the
        // expired_at scan, but not worth starting a booking with.
        'expired_at' => now()->addSeconds(120),
    ]);

    Http::fake([
        '*OAuth*' => Http::response([
            'accessTokenResponse' => ['token' => 'fresh-token', 'token_type' => 'Bearer', 'expires_in_seconds' => 3600],
        ]),
        '*' => Http::response([
            'manifestResponse' => ['bd' => ['shipmentItems' => [['deliveryConfirmationNo' => 'DHL00100']]]],
        ]),
    ]);

    $order = Order::factory()->create(['courier_service' => 'DHL eCommerce', 'awb_number' => '']);
    app(DhlGateway::class)->book($order);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'OAuth/AccessToken'));
    // The booking itself must carry the token that was just issued.
    Http::assertSent(fn ($request) => ($request->data()['manifestRequest']['hdr']['accessToken'] ?? null) === 'fresh-token');

    expect(DhlToken::query()->count())->toBe(2);
});

it('keeps DHL credentials out of the query string', function () {
    dhlConfigured();

    Http::fake(['*' => Http::response([
        'accessTokenResponse' => ['token' => 'fresh-token', 'expires_in_seconds' => 3600],
    ])]);

    // The source appended clientId and password to the URL, where they land in
    // every proxy and CDN access log along the way.
    $order = Order::factory()->create(['courier_service' => 'DHL eCommerce', 'awb_number' => '']);

    try {
        app(DhlGateway::class)->book($order);
    } catch (ShipmentFailed) {
        // The faked OAuth response is not a valid shipment response; irrelevant here.
    }

    Http::assertSent(function ($request) {
        return ! str_contains($request->url(), 'secret-sandbox')
            && ! str_contains($request->url(), 'client-sandbox');
    });
});

it('keeps one NinjaVan token row per mode instead of growing without bound', function () {
    config()->set('shipping.ninjavan', [
        'enabled' => true,
        'sandbox' => true,
        'country' => 'MY',
        'client_id' => 'nv-client',
        'client_secret' => 'nv-secret',
    ]);

    Http::fake(['*' => Http::response(['access_token' => 'nv-token', 'expires_in' => 1])]);

    $gateway = app(NinjaVanGateway::class);

    // Each call finds the previous token already past the renewal margin.
    $gateway->token();
    $this->travel(3)->seconds();
    $gateway->token();
    $this->travel(3)->seconds();
    $gateway->token();

    expect(NinjavanToken::query()->count())->toBe(1)
        ->and(NinjavanToken::query()->value('mode'))->toBe(NinjavanToken::MODE_SANDBOX);
});

// ---------------------------------------------------------------------------
// The admin action
// ---------------------------------------------------------------------------

it('books from the order queue', function () {
    jtConfigured();
    Http::fake(['*' => Http::response(['details' => [['status' => 'success', 'awb_no' => '630000444555']]])]);

    $admin = adminWith(['new-order']);
    $order = Order::factory()->create([
        'courier_service' => 'J&T Express',
        'awb_number' => '',
        'status' => Order::STATUS_NEW,
    ]);

    $this->actingAs($admin, 'admin')
        ->post("/admin/orders/{$order->id}/ship")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($order->fresh()->awb_number)->toBe('630000444555');
});

it('finishes a bulk booking even when one order is refused', function () {
    jtConfigured();

    $good = Order::factory()->create(['courier_service' => 'J&T Express', 'awb_number' => '', 'status' => Order::STATUS_NEW]);
    $bad = Order::factory()->create(['courier_service' => 'Pos Laju', 'awb_number' => '', 'status' => Order::STATUS_NEW]);

    Http::fake(['*' => Http::response(['details' => [['status' => 'success', 'awb_no' => '630000777666']]])]);

    $admin = adminWith(['new-order']);

    $this->actingAs($admin, 'admin')
        ->post('/admin/orders/ship', ['orders' => [$good->id, $bad->id]])
        ->assertRedirect()
        ->assertSessionHas('success');

    // The unbookable one must not stop the bookable one.
    expect($good->fresh()->awb_number)->toBe('630000777666')
        ->and($bad->fresh()->awb_number)->toBe('')
        ->and((int) $bad->fresh()->status)->toBe(Order::STATUS_NEW);
});

it('will not let a member of staff without order access book anything', function () {
    jtConfigured();
    Http::fake();

    $order = Order::factory()->create(['courier_service' => 'J&T Express', 'awb_number' => '']);
    $admin = adminWith(['stock-control']);

    $this->actingAs($admin, 'admin')
        ->post("/admin/orders/{$order->id}/ship")
        ->assertForbidden();

    Http::assertNothingSent();
    expect(DB::table('customer_orders')->where('id', $order->id)->value('awb_number'))->toBe('');
});

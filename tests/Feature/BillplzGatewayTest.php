<?php

use App\Models\BillplzSetting;
use App\Models\Order;
use App\Services\Payments\BillplzGateway;
use App\Services\Payments\PaymentGateways;
use App\Services\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Billplz.
 *
 * The source's callback checked no signature and marked an order paid on the
 * strength of a POST body — anyone who knew a bill id could settle an order.
 * These tests are mostly about the replacement: the request is trusted for the
 * bill id and nothing else, and whether that bill was paid is read back from
 * Billplz over an authenticated request.
 */
function billplzSettings(array $overrides = []): BillplzSetting
{
    app(StoreSettings::class)->set('billplz_enabled', '1');

    return BillplzSetting::create(array_merge([
        'sandbox_production' => BillplzSetting::MODE_SANDBOX,
        'sand_box_url' => 'https://www.billplz-sandbox.com/',
        'production_url' => 'https://www.billplz.com/',
        'api_key' => 'test-api-key',
        'x_signature' => 'test-signature-key',
        'bill_collection_id' => 'coll123',
        'payment_collection_slug' => 'shaniena',
        'bill_charge' => 1.00,
        'payment_charge' => BillplzSetting::CHARGE_TO_CUSTOMER,
    ], $overrides));
}

function billplzOrder(float $total = 119.80): Order
{
    $order = Order::factory()->create(['status' => Order::STATUS_AWAITING_PAYMENT]);

    $order->forceFill([
        'payment_channel' => Order::CHANNEL_BILLPLZ,
        'myr_value_include_postage' => $total,
    ])->save();

    return $order;
}

// --- availability --------------------------------------------------------

it('is not offered until it is switched on and configured', function () {
    $gateway = app(BillplzGateway::class);

    expect($gateway->isAvailable())->toBeFalse();

    billplzSettings(['api_key' => '', 'bill_collection_id' => '']);
    expect($gateway->isAvailable())->toBeFalse();

    BillplzSetting::query()->delete();
    billplzSettings();
    expect($gateway->isAvailable())->toBeTrue();
});

it('joins the channels the checkout can offer', function () {
    billplzSettings();

    expect(app(PaymentGateways::class)->availability())->toHaveKey(Order::CHANNEL_BILLPLZ)
        ->and(app(PaymentGateways::class)->availability()[Order::CHANNEL_BILLPLZ])->toBeTrue();
});

// --- creating the bill ---------------------------------------------------

it('creates a bill with the amount the server worked out and keeps its id', function () {
    $row = billplzSettings();
    $order = billplzOrder(119.80);

    Http::fake(['*/api/v3/bills' => Http::response([
        'id' => 'w_8x9r2', 'url' => 'https://www.billplz-sandbox.com/bills/w_8x9r2', 'state' => 'due',
    ])]);

    $handoff = app(BillplzGateway::class)->start($order, 119.80);

    expect($handoff->method)->toBe('GET')
        ->and($handoff->url)->toBe('https://www.billplz-sandbox.com/bills/w_8x9r2')
        // The callback carries the bill id and little else we can rely on, so
        // this is the thread back to the order.
        ->and($order->fresh()->payment_code)->toBe('w_8x9r2');

    Http::assertSent(function ($request) use ($row) {
        return $request->url() === 'https://www.billplz-sandbox.com/api/v3/bills'
            && $request['collection_id'] === $row->bill_collection_id
            // 119.80 plus the RM1 FPX fee, in cents.
            && (int) $request['amount'] === 12080
            && str_contains($request['callback_url'], '/payment/callback/billplz')
            && $request->hasHeader('Authorization');
    });
});

it('absorbs the FPX fee when the shop said it would', function () {
    $row = billplzSettings(['payment_charge' => BillplzSetting::CHARGE_TO_SELLER]);

    // The source added `bill_charge` to every bill regardless of this column.
    expect(app(BillplzGateway::class)->cents($row, 119.80))->toBe(11980);
});

it('does not leave an order pointing at a bill Billplz refused to create', function () {
    billplzSettings();
    $order = billplzOrder();
    $before = $order->payment_code;

    Http::fake(['*/api/v3/bills' => Http::response(['error' => ['message' => 'Collection not found']], 422)]);

    expect(fn () => app(BillplzGateway::class)->start($order, 119.80))
        ->toThrow(RuntimeException::class);

    // The factory gives an order a reference of its own; what matters is that
    // a failed creation did not overwrite it with a half-made bill.
    expect($order->fresh()->payment_code)->toBe($before);
});

// --- the callback --------------------------------------------------------

it('confirms an order only after Billplz itself says the bill is paid', function () {
    billplzSettings();
    $order = billplzOrder(119.80);
    $order->forceFill(['payment_code' => 'w_paid1'])->save();

    Http::fake(['*/api/v3/bills/w_paid1' => Http::response([
        'id' => 'w_paid1', 'paid' => true, 'state' => 'paid', 'amount' => 12080, 'paid_amount' => 12080,
    ])]);

    $this->post('/payment/callback/billplz', ['id' => 'w_paid1', 'paid' => 'true', 'state' => 'paid'])
        ->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_NEW);
});

it('ignores a callback that claims a payment Billplz has not taken', function () {
    billplzSettings();
    $order = billplzOrder();
    $order->forceFill(['payment_code' => 'w_unpaid'])->save();

    // Exactly the forged request the source would have accepted: it says paid,
    // and the bill is not.
    Http::fake(['*/api/v3/bills/w_unpaid' => Http::response([
        'id' => 'w_unpaid', 'paid' => false, 'state' => 'due', 'amount' => 12080, 'paid_amount' => 0,
    ])]);

    $this->post('/payment/callback/billplz', ['id' => 'w_unpaid', 'paid' => 'true', 'state' => 'paid'])
        ->assertOk();

    expect($order->fresh()->status)->not->toBe(Order::STATUS_NEW);
});

it('refuses a bill that belongs to no order here', function () {
    billplzSettings();

    $this->post('/payment/callback/billplz', ['id' => 'w_someone_else', 'paid' => 'true'])
        ->assertStatus(400);

    Http::assertNothingSent();
});

it('does not settle an order that was short paid', function () {
    billplzSettings();
    $order = billplzOrder(119.80);
    $order->forceFill(['payment_code' => 'w_short'])->save();

    Http::fake(['*/api/v3/bills/w_short' => Http::response([
        'id' => 'w_short', 'paid' => true, 'state' => 'paid', 'amount' => 12080, 'paid_amount' => 5000,
    ])]);

    $this->post('/payment/callback/billplz', ['id' => 'w_short'])->assertOk();

    expect($order->fresh()->status)->not->toBe(Order::STATUS_NEW);
});

it('confirms once when Billplz retries its callback', function () {
    billplzSettings();
    $order = billplzOrder(119.80);
    $order->forceFill(['payment_code' => 'w_retry'])->save();

    Http::fake(['*/api/v3/bills/w_retry' => Http::response([
        'id' => 'w_retry', 'paid' => true, 'state' => 'paid', 'amount' => 12080, 'paid_amount' => 12080,
    ])]);

    $this->post('/payment/callback/billplz', ['id' => 'w_retry'])->assertOk();
    $this->post('/payment/callback/billplz', ['id' => 'w_retry'])->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_NEW);
});

// --- signatures ----------------------------------------------------------

it('recognises a correctly signed callback and a tampered one', function () {
    $gateway = app(BillplzGateway::class);
    $key = 'test-signature-key';

    $payload = ['id' => 'w_sig', 'collection_id' => 'coll123', 'paid' => 'true', 'state' => 'paid', 'amount' => '12080'];

    $source = collect($payload)->sortKeys()->map(fn ($v, $k) => $k.$v)->implode('|');
    $signed = $payload + ['x_signature' => hash_hmac('sha256', $source, $key)];

    $request = Request::create('/payment/callback/billplz', 'POST', $signed);
    expect($gateway->signatureMatches($request, $key))->toBeTrue();

    $tampered = Request::create('/payment/callback/billplz', 'POST', [...$signed, 'amount' => '1']);
    expect($gateway->signatureMatches($tampered, $key))->toBeFalse();
});

// --- the settings screen -------------------------------------------------

it('shows the Billplz form without ever sending its keys to the browser', function () {
    billplzSettings(['api_key' => 'sk_live_billplz_realone']);
    $admin = adminWith(['payment-setting']);

    $response = $this->actingAs($admin, 'admin')->get('/admin/payment-setting');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('billplz.bill_collection_id', 'coll123')
        ->where('billplz.api_key', '••••••••lone'));

    expect($response->getContent())->not->toContain('sk_live_billplz_realone');
});

it('keeps a stored key when the operator saves without retyping it', function () {
    billplzSettings(['api_key' => 'keep-me', 'x_signature' => 'keep-me-too']);
    $admin = adminWith(['payment-setting']);

    $this->actingAs($admin, 'admin')->put('/admin/payment-setting/billplz', [
        'sandbox_production' => BillplzSetting::MODE_PRODUCTION,
        'sand_box_url' => 'https://www.billplz-sandbox.com/',
        'production_url' => 'https://www.billplz.com/',
        'bill_collection_id' => 'coll999',
        'payment_collection_slug' => 'shaniena',
        'bill_charge' => 1.5,
        'payment_charge' => BillplzSetting::CHARGE_TO_SELLER,
        'api_key' => '',
        'x_signature' => '',
    ])->assertRedirect();

    $row = BillplzSetting::current();

    expect($row->api_key)->toBe('keep-me')
        ->and($row->x_signature)->toBe('keep-me-too')
        ->and($row->bill_collection_id)->toBe('coll999')
        ->and($row->isProduction())->toBeTrue()
        ->and($row->baseUrl())->toBe('https://www.billplz.com/');
});

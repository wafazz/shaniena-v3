<?php

use App\Models\BayarcashSetting;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\SenangPaySetting;
use App\Services\Payments\BayarcashGateway;
use App\Services\Payments\SenangPayGateway;
use App\Services\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const SP_SECRET = 'test-secret-key';
const BC_SECRET = 'bayarcash-secret';

function senangpayConfigured(): SenangPaySetting
{
    app(StoreSettings::class)->set('senangpay_enabled', '1');

    return SenangPaySetting::create([
        'merchant_id' => '3531', 'secret_key' => SP_SECRET,
        'pro_merchant_id' => '9999', 'pro_secret_key' => 'live-secret',
        'type' => SenangPaySetting::MODE_SANDBOX,
    ]);
}

function bayarcashConfigured(): BayarcashSetting
{
    app(StoreSettings::class)->set('bayarcash_enabled', '1');

    return BayarcashSetting::create([
        'type' => BayarcashSetting::MODE_SANDBOX,
        'sandbox_api_token' => 'token', 'sandbox_secret_key' => BC_SECRET, 'sandbox_portal_key' => 'portal',
    ]);
}

// --- SenangPay -----------------------------------------------------------

it('builds the SenangPay request hash exactly as the source does', function () {
    $gateway = app(SenangPayGateway::class);

    $detail = 'Payment for ORDERID_12';
    $amount = '134.30';
    $orderId = 'ORDERID_12';

    // secret . detail . amount . order_id, no separator, HMAC keyed on the
    // same secret — lib/gateway/SenangPayGateway.php:37.
    $expected = hash_hmac('sha256', SP_SECRET.$detail.$amount.$orderId, SP_SECRET);

    expect($gateway->requestHash(SP_SECRET, $detail, $amount, $orderId))->toBe($expected);
});

it('builds the SenangPay callback hash exactly as the source does', function () {
    $gateway = app(SenangPayGateway::class);

    // secret . status_id . order_id . transaction_id . msg
    $expected = hash_hmac('sha256', SP_SECRET.'1'.'ORDERID_12'.'TXN99'.'Payment successful', SP_SECRET);

    expect($gateway->callbackHash(SP_SECRET, '1', 'ORDERID_12', 'TXN99', 'Payment successful'))->toBe($expected);
});

it('urldecodes callback fields before hashing, as the source does', function () {
    $gateway = app(SenangPayGateway::class);

    // A gateway that sends "Payment+successful" must still verify.
    expect($gateway->callbackHash(SP_SECRET, '1', 'ORDERID_12', 'TXN99', 'Payment+successful'))
        ->toBe($gateway->callbackHash(SP_SECRET, '1', 'ORDERID_12', 'TXN99', 'Payment successful'));
});

it('confirms a SenangPay callback whose hash checks out', function () {
    senangpayConfigured();
    $gateway = app(SenangPayGateway::class);

    $fields = ['status_id' => '1', 'order_id' => 'ORDERID_12', 'transaction_id' => 'TXN99', 'msg' => 'ok'];
    $fields['hash'] = $gateway->callbackHash(SP_SECRET, '1', 'ORDERID_12', 'TXN99', 'ok');

    $result = $gateway->verify(request()->merge($fields));

    expect($result->verified)->toBeTrue()
        ->and($result->paid)->toBeTrue()
        ->and($result->reference)->toBe('12')
        ->and($result->paymentCode)->toBe('TXN99');
});

it('rejects a SenangPay callback with a tampered amount or hash', function () {
    senangpayConfigured();
    $gateway = app(SenangPayGateway::class);

    $result = $gateway->verify(request()->merge([
        'status_id' => '1', 'order_id' => 'ORDERID_12',
        'transaction_id' => 'TXN99', 'msg' => 'ok', 'hash' => str_repeat('0', 64),
    ]));

    expect($result->verified)->toBeFalse()->and($result->paid)->toBeFalse();
});

it('treats SenangPay pending as not paid', function () {
    senangpayConfigured();
    $gateway = app(SenangPayGateway::class);

    $fields = ['status_id' => '2', 'order_id' => 'ORDERID_12', 'transaction_id' => 'T', 'msg' => 'pending'];
    $fields['hash'] = $gateway->callbackHash(SP_SECRET, '2', 'ORDERID_12', 'T', 'pending');

    $result = $gateway->verify(request()->merge($fields));

    expect($result->verified)->toBeTrue()->and($result->paid)->toBeFalse();
});

// --- Bayarcash -----------------------------------------------------------

it('builds the Bayarcash intent checksum over 5 sorted fields', function () {
    $gateway = app(BayarcashGateway::class);

    $data = [
        'amount' => '134.30', 'order_number' => 'ORDERID_12',
        'payer_email' => 'buyer@example.test', 'payer_name' => 'Aisyah Rahman',
        'payment_channel' => '1', 'portal_key' => 'ignored', 'callback_url' => 'ignored',
    ];

    // ksort order: amount|order_number|payer_email|payer_name|payment_channel.
    // portal_key, telephone, callback_url and return_url are sent but excluded.
    $expected = hash_hmac('sha256',
        '134.30|ORDERID_12|buyer@example.test|Aisyah Rahman|1', BC_SECRET);

    expect($gateway->checksum(BC_SECRET, $data, [
        'amount', 'order_number', 'payer_email', 'payer_name', 'payment_channel',
    ]))->toBe($expected);
});

it('builds the Bayarcash callback checksum in ksort order, not doc order', function () {
    $gateway = app(BayarcashGateway::class);

    $callback = [
        'record_type' => 'transaction', 'transaction_id' => 'TXN1',
        'exchange_reference_number' => 'ERN1', 'exchange_transaction_id' => 'ETX1',
        'order_number' => 'ORDERID_12', 'currency' => 'MYR', 'amount' => '134.30',
        'payer_name' => 'Aisyah', 'payer_email' => 'a@example.test',
        'payer_bank_name' => 'Maybank', 'status' => '3',
        'status_description' => 'Successful', 'datetime' => '2026-09-05 12:00:00',
    ];

    // Alphabetical by key — the source declares the documented order then
    // ksort()s it, so these are the bytes actually hashed.
    $expected = hash_hmac('sha256', implode('|', [
        '134.30', 'MYR', '2026-09-05 12:00:00', 'ERN1', 'ETX1', 'ORDERID_12',
        'Maybank', 'a@example.test', 'Aisyah', 'transaction', '3', 'Successful', 'TXN1',
    ]), BC_SECRET);

    expect($gateway->checksum(BC_SECRET, $callback, [
        'amount', 'currency', 'datetime', 'exchange_reference_number',
        'exchange_transaction_id', 'order_number', 'payer_bank_name',
        'payer_email', 'payer_name', 'record_type', 'status',
        'status_description', 'transaction_id',
    ]))->toBe($expected);
});

it('confirms a Bayarcash callback whose checksum checks out', function () {
    bayarcashConfigured();
    $gateway = app(BayarcashGateway::class);

    $callback = [
        'record_type' => 'transaction', 'transaction_id' => 'TXN1',
        'exchange_reference_number' => '', 'exchange_transaction_id' => '',
        'order_number' => 'ORDERID_12', 'currency' => 'MYR', 'amount' => '134.30',
        'payer_name' => 'Aisyah', 'payer_email' => 'a@example.test',
        'payer_bank_name' => 'Maybank', 'status' => '3',
        'status_description' => 'Successful', 'datetime' => '2026-09-05 12:00:00',
    ];
    $callback['checksum'] = $gateway->checksum(BC_SECRET, $callback, [
        'amount', 'currency', 'datetime', 'exchange_reference_number',
        'exchange_transaction_id', 'order_number', 'payer_bank_name',
        'payer_email', 'payer_name', 'record_type', 'status',
        'status_description', 'transaction_id',
    ]);

    $result = $gateway->verify(request()->merge($callback));

    expect($result->verified)->toBeTrue()->and($result->paid)->toBeTrue()
        ->and($result->reference)->toBe('12');
});

it('rejects a Bayarcash callback with no checksum at all', function () {
    bayarcashConfigured();

    // The source answered 200 "OK" to these and fired an UPDATE on order id 0.
    $result = app(BayarcashGateway::class)->verify(request()->merge(['order_number' => 'ORDERID_12', 'status' => '3']));

    expect($result->verified)->toBeFalse()->and($result->reference)->toBeNull();
});

it('rejects a Bayarcash callback whose amount was altered after signing', function () {
    bayarcashConfigured();
    $gateway = app(BayarcashGateway::class);

    $callback = [
        'record_type' => 'transaction', 'transaction_id' => 'TXN1',
        'exchange_reference_number' => '', 'exchange_transaction_id' => '',
        'order_number' => 'ORDERID_12', 'currency' => 'MYR', 'amount' => '134.30',
        'payer_name' => 'A', 'payer_email' => 'a@example.test',
        'payer_bank_name' => 'Maybank', 'status' => '3',
        'status_description' => 'Successful', 'datetime' => '2026-09-05 12:00:00',
    ];
    $callback['checksum'] = $gateway->checksum(BC_SECRET, $callback, [
        'amount', 'currency', 'datetime', 'exchange_reference_number',
        'exchange_transaction_id', 'order_number', 'payer_bank_name',
        'payer_email', 'payer_name', 'record_type', 'status',
        'status_description', 'transaction_id',
    ]);

    $callback['amount'] = '1.00';

    expect($gateway->verify(request()->merge($callback))->verified)->toBeFalse();
});

// --- availability --------------------------------------------------------

it('does not offer a gateway that is switched on but not configured', function () {
    app(StoreSettings::class)->set('senangpay_enabled', '1');
    app(StoreSettings::class)->set('bayarcash_enabled', '1');

    // No settings rows exist: the source rendered the button anyway.
    expect(app(SenangPayGateway::class)->isAvailable())->toBeFalse()
        ->and(app(BayarcashGateway::class)->isAvailable())->toBeFalse();
});

it('offers a gateway that is both on and configured', function () {
    senangpayConfigured();
    bayarcashConfigured();

    expect(app(SenangPayGateway::class)->isAvailable())->toBeTrue()
        ->and(app(BayarcashGateway::class)->isAvailable())->toBeTrue();
});

// --- replay --------------------------------------------------------------

it('confirms an order once, however many times the callback arrives', function () {
    senangpayConfigured();
    $gateway = app(SenangPayGateway::class);
    $order = Order::factory()->status(Order::STATUS_AWAITING_PAYMENT)->create();

    $fields = ['status_id' => '1', 'order_id' => 'ORDERID_'.$order->id, 'transaction_id' => 'TXN1', 'msg' => 'ok'];
    $fields['hash'] = $gateway->callbackHash(SP_SECRET, '1', $fields['order_id'], 'TXN1', 'ok');

    // The source re-ran the whole completion every time: another order_details
    // row, cart rows re-marked paid, and another confirmation email.
    $this->post('/payment/callback/senangpay', $fields)->assertOk();
    $this->post('/payment/callback/senangpay', $fields)->assertOk();

    expect((int) $order->fresh()->status)->toBe(Order::STATUS_NEW)
        ->and(OrderDetail::where('order_id', $order->id)->count())->toBeLessThanOrEqual(1);
});

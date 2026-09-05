<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Contracts\PaymentHandoff;
use App\Contracts\PaymentResult;
use App\Models\BayarcashSetting;
use App\Models\Order;
use App\Services\StoreSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Bayarcash v3.
 *
 * Two checksums, both `hash_hmac('sha256', implode('|', $values), $secret)`
 * over values sorted by KEY — which is the load-bearing detail. The source
 * declares the callback payload in Bayarcash's documented order and then runs
 * ksort() over it, so the bytes actually hashed are alphabetical. Reproducing
 * the declared order instead makes every callback fail verification.
 */
class BayarcashGateway implements PaymentGateway
{
    public const REFERENCE_PREFIX = 'ORDERID_';

    /** Fields in the payment-intent checksum. Sorted, as ksort() leaves them. */
    private const INTENT_FIELDS = [
        'amount', 'order_number', 'payer_email', 'payer_name', 'payment_channel',
    ];

    /** Fields in the callback checksum, in ksort() order — not doc order. */
    private const CALLBACK_FIELDS = [
        'amount', 'currency', 'datetime', 'exchange_reference_number',
        'exchange_transaction_id', 'order_number', 'payer_bank_name',
        'payer_email', 'payer_name', 'record_type', 'status',
        'status_description', 'transaction_id',
    ];

    private const STATUS_SUCCESSFUL = '3';

    public const CHANNEL_FPX = 1;

    /** Channels the storefront offers, from the source's own checkout. */
    public const CHANNELS = [
        1 => 'FPX Online Banking',
        5 => 'DuitNow Online Banking/Wallets',
        6 => 'DuitNow QR',
        7 => 'SPayLater',
        8 => 'Boost PayFlex',
    ];

    public function __construct(private StoreSettings $settings) {}

    public function channel(): string
    {
        return Order::CHANNEL_BAYARCASH;
    }

    public function isAvailable(): bool
    {
        if (! $this->settings->enabled('bayarcash_enabled')) {
            return false;
        }

        $credentials = BayarcashSetting::current()?->credentials();

        return filled($credentials['api_token'] ?? null)
            && filled($credentials['secret_key'] ?? null)
            && filled($credentials['portal_key'] ?? null);
    }

    public function start(Order $order, float $amount, ?int $channel = null): PaymentHandoff
    {
        $setting = BayarcashSetting::current();
        $credentials = $setting->credentials();

        // Only channels the store actually offers; the source took an
        // unvalidated ?channel= straight from the query string.
        $channel = array_key_exists((int) $channel, self::CHANNELS) ? (int) $channel : self::CHANNEL_FPX;

        $payload = [
            'portal_key' => $credentials['portal_key'],
            'order_number' => self::REFERENCE_PREFIX.$order->id,
            'amount' => number_format($amount, 2, '.', ''),
            'payer_name' => $order->customerFullName(),
            'payer_email' => (string) $order->customer_email,
            'payer_telephone_number' => (string) $order->customer_phone,
            'callback_url' => route('shop.pay.callback', ['channel' => $this->channel()]),
            'return_url' => route('shop.pay.return', ['channel' => $this->channel()]),
            'payment_channel' => (string) $channel,
        ];

        $payload['checksum'] = $this->checksum($credentials['secret_key'], $payload, self::INTENT_FIELDS);

        $response = Http::asForm()
            ->withToken($credentials['api_token'])
            ->acceptJson()
            ->timeout(30)
            ->post($this->baseUrl($setting).'/payment-intents', $payload);

        if ($response->failed() || blank($response->json('url'))) {
            // Never log the payload: it contains the checksum and the payer's
            // details. The source error_log()ged both, plus the secret's prefix.
            Log::error('Bayarcash refused a payment intent.', [
                'order' => $order->id,
                'status' => $response->status(),
            ]);

            throw new RuntimeException('Bayarcash could not start that payment. Please try again.');
        }

        return PaymentHandoff::redirect($response->json('url'));
    }

    public function verify(Request $request): PaymentResult
    {
        $credentials = BayarcashSetting::current()?->credentials();

        if (blank($credentials['secret_key'] ?? null)) {
            return PaymentResult::untrusted('Bayarcash is not configured.');
        }

        $received = (string) $request->input('checksum', '');

        if ($received === '') {
            return PaymentResult::untrusted();
        }

        $expected = $this->checksum($credentials['secret_key'], $request->all(), self::CALLBACK_FIELDS);

        if (! hash_equals($expected, $received)) {
            return PaymentResult::untrusted();
        }

        $orderId = str_replace(self::REFERENCE_PREFIX, '', (string) $request->input('order_number', ''));
        $status = (string) $request->input('status', '');

        return $status === self::STATUS_SUCCESSFUL
            ? PaymentResult::paid($orderId, (string) $request->input('transaction_id', ''))
            : PaymentResult::declined($orderId, (string) $request->input('status_description', 'Payment was not completed.'));
    }

    /**
     * hash_hmac over the named fields, values only, joined with a pipe, in the
     * order given — which for both field lists is ksort() order.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $fields
     */
    public function checksum(string $secret, array $data, array $fields): string
    {
        $values = array_map(fn (string $field) => (string) ($data[$field] ?? ''), $fields);

        return hash_hmac('sha256', implode('|', $values), $secret);
    }

    private function baseUrl(BayarcashSetting $setting): string
    {
        return $setting->isSandbox()
            ? 'https://api.console.bayarcash-sandbox.com/v3'
            : 'https://api.console.bayar.cash/v3';
    }
}

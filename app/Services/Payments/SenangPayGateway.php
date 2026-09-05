<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Contracts\PaymentHandoff;
use App\Contracts\PaymentResult;
use App\Models\Order;
use App\Models\SenangPaySetting;
use App\Services\StoreSettings;
use Illuminate\Http\Request;

/**
 * SenangPay.
 *
 * Both hashes are reproduced byte-for-byte from lib/gateway/SenangPayGateway.php.
 * Getting either wrong means every payment silently fails to confirm, so the
 * construction is spelled out rather than tidied:
 *
 *   request  = hmac_sha256(secret . detail . amount . order_id, secret)
 *   callback = hmac_sha256(secret . status_id . order_id . transaction_id . msg, secret)
 *
 * No separator between fields. Each field is urldecode()d first; the secret is
 * not. The secret appears twice — prefixed onto the message AND as the HMAC key.
 */
class SenangPayGateway implements PaymentGateway
{
    /** The source prefixes its order ids; the callback strips it back off. */
    public const REFERENCE_PREFIX = 'ORDERID_';

    private const STATUS_SUCCESS = '1';

    public function __construct(private StoreSettings $settings) {}

    public function channel(): string
    {
        return Order::CHANNEL_SENANGPAY;
    }

    public function isAvailable(): bool
    {
        if (! $this->settings->enabled('senangpay_enabled')) {
            return false;
        }

        $credentials = SenangPaySetting::current()?->credentials();

        return filled($credentials['merchant_id'] ?? null)
            && filled($credentials['secret_key'] ?? null);
    }

    public function start(Order $order, float $amount): PaymentHandoff
    {
        $credentials = SenangPaySetting::current()->credentials();

        $reference = self::REFERENCE_PREFIX.$order->id;
        // Two decimals, no thousands separator — what the hash is built over.
        $formatted = number_format($amount, 2, '.', '');
        $detail = 'Payment for '.$reference;

        return PaymentHandoff::post(
            rtrim($credentials['url'], '/').'/payment/'.$credentials['merchant_id'],
            [
                'detail' => $detail,
                'amount' => $formatted,
                'order_id' => $reference,
                'name' => $order->customerFullName(),
                'email' => (string) $order->customer_email,
                'phone' => (string) $order->customer_phone,
                'hash' => $this->requestHash($credentials['secret_key'], $detail, $formatted, $reference),
            ],
        );
    }

    public function verify(Request $request): PaymentResult
    {
        $credentials = SenangPaySetting::current()?->credentials();

        if (blank($credentials['secret_key'] ?? null)) {
            return PaymentResult::untrusted('SenangPay is not configured.');
        }

        $statusId = (string) $request->input('status_id', '');
        $reference = (string) $request->input('order_id', '');
        $transactionId = (string) $request->input('transaction_id', '');
        $message = (string) $request->input('msg', '');
        $received = (string) $request->input('hash', '');

        $expected = $this->callbackHash($credentials['secret_key'], $statusId, $reference, $transactionId, $message);

        if (! hash_equals($expected, urldecode($received))) {
            return PaymentResult::untrusted();
        }

        $orderId = str_replace(self::REFERENCE_PREFIX, '', $reference);

        // status_id 2 is "pending" in SenangPay's own docs. The source treated
        // it as failure; it is not a payment, so it is not a confirmation.
        return $statusId === self::STATUS_SUCCESS
            ? PaymentResult::paid($orderId, $transactionId)
            : PaymentResult::declined($orderId, $message ?: 'SenangPay did not approve the payment.');
    }

    public function requestHash(string $secret, string $detail, string $amount, string $orderId): string
    {
        return hash_hmac(
            'sha256',
            $secret.urldecode($detail).urldecode($amount).urldecode($orderId),
            $secret,
        );
    }

    public function callbackHash(string $secret, string $statusId, string $orderId, string $transactionId, string $message): string
    {
        return hash_hmac(
            'sha256',
            $secret.urldecode($statusId).urldecode($orderId).urldecode($transactionId).urldecode($message),
            $secret,
        );
    }
}

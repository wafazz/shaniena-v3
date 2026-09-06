<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Contracts\PaymentHandoff;
use App\Contracts\PaymentResult;
use App\Models\BillplzSetting;
use App\Models\Order;
use App\Services\StoreSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Billplz (FPX and e-wallets).
 *
 * Ported from the source's billPlzzOrder() and bp-callback.php, with the
 * callback rewritten. What the source did on payment confirmation:
 *
 *     if($paid == "true" AND $state = "paid"){ ... confirmBillPlz(...) }
 *
 * There is no signature check anywhere in it, and that `=` is an assignment,
 * so the second half of the condition is always true. Anyone who knew a bill
 * id could POST that URL and mark the order paid.
 *
 * So the request is trusted for exactly one thing here: the bill id. Whether
 * that bill was actually paid, and for how much, is then read back from
 * Billplz over an authenticated request with our own API key. An attacker
 * forging a callback achieves nothing unless Billplz itself says the bill is
 * paid. The `x_signature` is verified too where a key is configured, but it is
 * logged rather than trusted, because a signature scheme is a thing you can
 * get subtly wrong and the re-read is not.
 */
class BillplzGateway implements PaymentGateway
{
    private const BILLS_PATH = 'api/v3/bills';

    public function __construct(private StoreSettings $settings) {}

    public function channel(): string
    {
        return Order::CHANNEL_BILLPLZ;
    }

    public function isAvailable(): bool
    {
        if (! $this->settings->enabled('billplz_enabled')) {
            return false;
        }

        $row = BillplzSetting::current();

        return $row !== null
            && filled($row->api_key)
            && filled($row->bill_collection_id)
            && filled($row->baseUrl());
    }

    public function start(Order $order, float $amount): PaymentHandoff
    {
        $row = BillplzSetting::current();
        $cents = $this->cents($row, $amount);

        $response = Http::withBasicAuth($row->api_key, '')
            ->asForm()
            ->timeout(20)
            ->post($this->endpoint($row), [
                'collection_id' => $row->bill_collection_id,
                'email' => (string) $order->customer_email,
                // Not urlencode()d: the source encoded it by hand and then let
                // cURL encode the field again, so every phone number reached
                // Billplz double-escaped.
                'mobile' => (string) $order->customer_phone,
                'name' => $order->customerFullName(),
                'amount' => $cents,
                'callback_url' => route('shop.pay.callback', ['channel' => $this->channel()]),
                'redirect_url' => route('shop.pay.return', ['channel' => $this->channel()]),
                'description' => 'Payment for order '.$order->id,
                'reference_1_label' => 'Order',
                'reference_1' => (string) $order->id,
            ]);

        $bill = $response->json();

        if (! $response->successful() || blank($bill['id'] ?? null) || blank($bill['url'] ?? null)) {
            Log::error('Billplz refused to create a bill.', [
                'order' => $order->id,
                'status' => $response->status(),
                'error' => $bill['error'] ?? null,
            ]);

            throw new \RuntimeException('Billplz could not take that payment. Try another method.');
        }

        // The callback carries the bill id and nothing else we can rely on, so
        // this is what ties it back to the order.
        $order->forceFill([
            'payment_code' => $bill['id'],
            'payment_url' => $bill['url'],
        ])->save();

        return PaymentHandoff::redirect($bill['url']);
    }

    public function verify(Request $request): PaymentResult
    {
        $row = BillplzSetting::current();

        if (! $row || blank($row->api_key)) {
            return PaymentResult::untrusted('Billplz is not configured.');
        }

        $billId = $this->billIdFrom($request);

        if (blank($billId)) {
            return PaymentResult::untrusted('That callback carried no bill id.');
        }

        $order = Order::query()->where('payment_code', $billId)->first();

        if (! $order) {
            return PaymentResult::untrusted('That bill belongs to no order here.');
        }

        // Checked, and loudly logged when it fails — but the decision below is
        // Billplz's own answer, not this.
        if (filled($row->x_signature) && ! $this->signatureMatches($request, (string) $row->x_signature)) {
            Log::warning('Billplz callback signature did not match.', [
                'order' => $order->id,
                'bill' => $billId,
                'ip' => $request->ip(),
            ]);
        }

        $bill = $this->fetchBill($row, $billId);

        if ($bill === null) {
            return PaymentResult::untrusted('Could not read that bill back from Billplz.');
        }

        if (! filter_var($bill['paid'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return PaymentResult::declined((string) $order->id, 'Billplz has not been paid for this order.');
        }

        // Underpayment is not payment. Billplz reports both in cents.
        $expected = $this->cents($row, (float) $order->myr_value_include_postage);
        $paid = (int) ($bill['paid_amount'] ?? 0);

        if ($paid < $expected) {
            Log::warning('Billplz reported a short payment.', [
                'order' => $order->id,
                'expected_cents' => $expected,
                'paid_cents' => $paid,
            ]);

            return PaymentResult::declined((string) $order->id, 'The amount paid does not cover this order.');
        }

        return PaymentResult::paid((string) $order->id, (string) $billId);
    }

    /**
     * What the customer is asked for.
     *
     * `bill_charge` is the flat FPX fee. Whose it is comes from
     * `payment_charge` — the source added it to every bill regardless, so a
     * shop that had chosen to absorb the fee charged it to the customer
     * anyway.
     */
    public function cents(BillplzSetting $row, float $amount): int
    {
        $charge = (int) $row->payment_charge === BillplzSetting::CHARGE_TO_CUSTOMER
            ? (float) $row->bill_charge
            : 0.0;

        return (int) round(($amount + $charge) * 100);
    }

    /**
     * Billplz signs its callbacks by joining `key + value` pairs with "|" in
     * key order and taking an HMAC-SHA256 with the X-Signature key. The
     * redirect back to the browser nests everything under `billplz[...]`, and
     * signs the flattened `billplzid`-style names.
     */
    public function signatureMatches(Request $request, string $key): bool
    {
        $payload = $request->has('billplz')
            ? collect((array) $request->input('billplz'))->mapWithKeys(fn ($v, $k) => ['billplz'.$k => $v])->all()
            : $request->except(['x_signature']);

        $signature = $request->has('billplz')
            ? (string) ($request->input('billplz.x_signature') ?? '')
            : (string) $request->input('x_signature', '');

        if ($signature === '') {
            return false;
        }

        $pairs = collect($payload)
            ->except(['billplzx_signature', 'x_signature'])
            ->map(fn ($value) => is_scalar($value) || $value === null ? (string) $value : '')
            ->sortKeys()
            ->map(fn ($value, $name) => $name.$value)
            ->implode('|');

        return hash_equals(hash_hmac('sha256', $pairs, $key), $signature);
    }

    /** @return array<string, mixed>|null */
    private function fetchBill(BillplzSetting $row, string $billId): ?array
    {
        try {
            $response = Http::withBasicAuth($row->api_key, '')
                ->timeout(20)
                ->get($this->endpoint($row).'/'.$billId);
        } catch (Throwable $e) {
            Log::error('Billplz was unreachable while verifying a payment.', [
                'bill' => $billId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $response->successful() ? (array) $response->json() : null;
    }

    private function billIdFrom(Request $request): string
    {
        // Webhook posts `id`; the browser redirect nests it under billplz[id].
        return (string) ($request->input('id') ?? $request->input('billplz.id') ?? '');
    }

    private function endpoint(BillplzSetting $row): string
    {
        return rtrim((string) $row->baseUrl(), '/').'/'.self::BILLS_PATH;
    }
}

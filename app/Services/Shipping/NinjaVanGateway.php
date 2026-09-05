<?php

namespace App\Services\Shipping;

use App\Contracts\Consignment;
use App\Contracts\ShippingGateway;
use App\Exceptions\ShipmentFailed;
use App\Models\NinjavanToken;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NinjaVan.
 *
 * OAuth client-credentials, with the token cached in `ninjavan_token`. The
 * source INSERTed a new row on every refresh and never pruned, so the table
 * grew without bound; this updates the row for the mode instead.
 */
class NinjaVanGateway implements ShippingGateway
{
    public const NAME = 'NinjaVan';

    /** Refresh with five minutes to spare, as the source did. */
    private const RENEW_MARGIN_SECONDS = 300;

    public function name(): string
    {
        return self::NAME;
    }

    public function isConfigured(): bool
    {
        return (bool) config('shipping.ninjavan.enabled')
            && filled(config('shipping.ninjavan.client_id'))
            && filled(config('shipping.ninjavan.client_secret'));
    }

    public function book(Order $order): Consignment
    {
        $reference = str_pad((string) $order->id, 8, '0', STR_PAD_LEFT);

        $response = Http::withToken($this->token())
            ->acceptJson()
            ->timeout(30)
            ->post($this->baseUrl().'/4.2/orders', $this->payload($order, $reference));

        $awb = $response->json('tracking_number');

        if ($response->failed() || blank($awb)) {
            Log::error('NinjaVan refused a shipment.', ['order' => $order->id, 'status' => $response->status()]);

            throw new ShipmentFailed('NinjaVan could not book that shipment.');
        }

        return new Consignment(
            (string) $awb,
            'https://www.ninjavan.co/en-my/tracking?id='.$awb,
            self::NAME,
        );
    }

    /** Cached per mode, refreshed shortly before it expires. */
    public function token(): string
    {
        $mode = $this->mode();

        if ($cached = NinjavanToken::activeFor($mode)) {
            if ($cached->expired_at?->diffInSeconds(now(), false) < -self::RENEW_MARGIN_SECONDS) {
                return $cached->access_token;
            }
        }

        $response = Http::asForm()->timeout(20)->post($this->baseUrl().'/2.0/oauth/access_token', [
            'client_id' => config('shipping.ninjavan.client_id'),
            'client_secret' => config('shipping.ninjavan.client_secret'),
            'grant_type' => 'client_credentials',
        ]);

        $token = $response->json('access_token');

        if ($response->failed() || blank($token)) {
            throw new ShipmentFailed('NinjaVan would not issue an access token.');
        }

        $expiresIn = (int) $response->json('expires_in', 3600);

        // One row per mode, updated in place.
        NinjavanToken::updateOrCreate(['mode' => $mode], [
            'access_token' => $token,
            'token_type' => (string) $response->json('token_type', 'Bearer'),
            'expires_in' => $expiresIn,
            'created_at' => now(),
            'expired_at' => now()->addSeconds($expiresIn),
        ]);

        return $token;
    }

    private function mode(): string
    {
        return config('shipping.ninjavan.sandbox')
            ? NinjavanToken::MODE_SANDBOX
            : NinjavanToken::MODE_PRODUCTION;
    }

    private function baseUrl(): string
    {
        return config('shipping.ninjavan.sandbox')
            ? 'https://api-sandbox.ninjavan.co/sg'
            : 'https://api.ninjavan.co/'.config('shipping.ninjavan.country', 'MY');
    }

    /** @return array<string, mixed> */
    private function payload(Order $order, string $reference): array
    {
        $shipper = config('shipping.shipper');

        $job = [
            'is_pickup_required' => false,
            'pickup_service_type' => 'Scheduled',
            'pickup_service_level' => 'Standard',
            'delivery_start_date' => Carbon::tomorrow()->toDateString(),
            'delivery_timeslot' => [
                'start_time' => '09:00',
                'end_time' => '22:00',
                'timezone' => config('app.timezone'),
            ],
            'dimensions' => [
                'weight' => max(0.1, round((int) $order->lines()->sum('total_weight') / 1000, 2)),
            ],
        ];

        if ($order->payment_channel === Order::CHANNEL_COD) {
            $job['cash_on_delivery'] = round((float) $order->myr_value_include_postage, 2);
        }

        return [
            'service_type' => 'Parcel',
            'service_level' => 'Standard',
            'requested_tracking_number' => $reference,
            'reference' => ['merchant_order_number' => $order->reference()],
            'from' => [
                'name' => $shipper['name'],
                'phone_number' => $shipper['phone'],
                'email' => $shipper['email'],
                'address' => [
                    'address1' => $shipper['address1'],
                    'address2' => $shipper['address2'],
                    'city' => $shipper['city'],
                    'state' => $shipper['state'],
                    'postcode' => $shipper['postcode'],
                    'country' => 'MY',
                ],
            ],
            'to' => [
                'name' => $order->customerFullName(),
                'phone_number' => (string) $order->customer_phone,
                'email' => (string) $order->customer_email,
                'address' => [
                    'address1' => (string) $order->address_1,
                    'address2' => (string) $order->address_2,
                    'city' => (string) $order->city,
                    'state' => (string) $order->state,
                    'postcode' => (string) $order->postcode,
                    'country' => 'MY',
                ],
            ],
            'parcel_job' => $job,
        ];
    }
}

<?php

namespace App\Services\Shipping;

use App\Contracts\Consignment;
use App\Contracts\ShippingGateway;
use App\Exceptions\ShipmentFailed;
use App\Models\DhlSetting;
use App\Models\DhlToken;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DHL eCommerce.
 *
 * Three things the source got wrong are fixed here:
 *  - dhlToken() selected from `dhl_token_test` in BOTH branches, so production
 *    always authenticated with the sandbox token.
 *  - The stored `expired_at` was never checked, so an expired token was reused
 *    until someone re-saved the settings form by hand.
 *  - Credentials went in the URL query string, where they land in every
 *    intermediary access log.
 */
class DhlGateway implements ShippingGateway
{
    public const NAME = 'DHL eCommerce';

    /** Refresh this long before expiry rather than racing it. */
    private const RENEW_MARGIN_SECONDS = 300;

    public function name(): string
    {
        return self::NAME;
    }

    public function isConfigured(): bool
    {
        $credentials = DhlSetting::current()?->credentials();

        return filled($credentials['clientid'] ?? null) && filled($credentials['password'] ?? null);
    }

    public function book(Order $order): Consignment
    {
        $setting = DhlSetting::current();

        if (! $setting) {
            throw new ShipmentFailed('DHL is not configured.');
        }

        $credentials = $setting->credentials();
        $token = $this->token($setting);
        $reference = str_pad((string) $order->id, 8, '0', STR_PAD_LEFT);
        $shipmentId = ($setting->isProduction() ? 'MYNVUROZZ' : 'MYXXX').$reference;

        $response = Http::acceptJson()->timeout(30)->post(
            rtrim($credentials['url'], '/').'/rest/v3/Shipment',
            $this->payload($order, $setting, $token, $shipmentId),
        );

        $item = $response->json('manifestResponse.bd.shipmentItems.0');

        if ($response->failed() || blank($item['deliveryConfirmationNo'] ?? null)) {
            Log::error('DHL refused a shipment.', ['order' => $order->id, 'status' => $response->status()]);

            throw new ShipmentFailed('DHL could not book that shipment.');
        }

        $awb = (string) $item['deliveryConfirmationNo'];

        return new Consignment(
            $awb,
            'https://www.dhl.com/my-en/home/tracking.html?tracking-id='.$awb,
            self::NAME,
        );
    }

    /** A cached token, refreshed when it is close to expiring. */
    private function token(DhlSetting $setting): string
    {
        if ($cached = DhlToken::active()) {
            if ($cached->expired_at?->diffInSeconds(now(), false) < -self::RENEW_MARGIN_SECONDS) {
                return $cached->token;
            }
        }

        $credentials = $setting->credentials();

        // Credentials in the body, not the query string.
        $response = Http::acceptJson()->timeout(20)->asForm()->post(
            rtrim($credentials['url'], '/').'/rest/v1/OAuth/AccessToken',
            ['clientId' => $credentials['clientid'], 'password' => $credentials['password'], 'returnFormat' => 'json'],
        );

        $token = $response->json('accessTokenResponse.token');

        if ($response->failed() || blank($token)) {
            throw new ShipmentFailed('DHL would not issue an access token.');
        }

        $expiresIn = (int) $response->json('accessTokenResponse.expires_in_seconds', 0);

        DhlToken::create([
            'token' => $token,
            'token_type' => (string) $response->json('accessTokenResponse.token_type', 'Bearer'),
            'expires_in_seconds' => $expiresIn,
            'created_at' => now(),
            'expired_at' => now()->addSeconds($expiresIn ?: 3600),
        ]);

        return $token;
    }

    /** @return array<string, mixed> */
    private function payload(Order $order, DhlSetting $setting, string $token, string $shipmentId): array
    {
        $account = $setting->isProduction() ? '9000000416' : '5999999940';

        return [
            'manifestRequest' => [
                'hdr' => [
                    'messageType' => 'SHIPMENT',
                    'messageDateTime' => Carbon::now()->toIso8601String(),
                    // DHL wants the token inside the body, not as a header.
                    'accessToken' => $token,
                    'messageVersion' => '1.0',
                    'messageLanguage' => 'en',
                ],
                'bd' => [
                    'pickupAccountId' => $account,
                    'soldToAccountId' => $account,
                    'pickupDateTime' => Carbon::now()->addDay()->toIso8601String(),
                    'handoverMethod' => 1,
                    'pickupAddress' => $this->shipper(),
                    'shipperAddress' => $this->shipper(),
                    'shipmentItems' => [[
                        'consigneeAddress' => [
                            'name' => $order->customerFullName(),
                            'address1' => (string) $order->address_1,
                            'address2' => (string) $order->address_2,
                            'city' => (string) $order->city,
                            'state' => (string) $order->state,
                            'district' => (string) $order->city,
                            'country' => 'MY',
                            'postCode' => (string) $order->postcode,
                            'phone' => (string) $order->customer_phone,
                            'email' => (string) $order->customer_email,
                        ],
                        'shipmentID' => $shipmentId,
                        'packageDesc' => 'Order '.$order->reference(),
                        // The real parcel weight, not the hardcoded 10g the
                        // source sent on every shipment.
                        'totalWeight' => max(1, (int) $order->lines()->sum('total_weight')),
                        'totalWeightUOM' => 'g',
                        'dimensionUOM' => 'CM',
                        'height' => 10.00,
                        'length' => 30.00,
                        'width' => 20.00,
                        'productCode' => 'PDO',
                        // COD is now declared to the courier; the source always
                        // sent null, so COD parcels were delivered uncollected.
                        'codValue' => $order->payment_channel === Order::CHANNEL_COD
                            ? round((float) $order->myr_value_include_postage, 2)
                            : null,
                        'insuranceValue' => null,
                        'totalValue' => round((float) $order->myr_value_include_postage, 2),
                        'currency' => (string) $order->currency_sign,
                        'isRoutingInfoRequired' => 'Y',
                        'isMult' => 'FALSE',
                        'deliveryOption' => 'c',
                    ]],
                ],
            ],
        ];
    }

    /** @return array<string, string> */
    private function shipper(): array
    {
        return [
            'name' => (string) config('shipping.shipper.name'),
            'address1' => (string) config('shipping.shipper.address1'),
            'address2' => (string) config('shipping.shipper.address2'),
            'city' => (string) config('shipping.shipper.city'),
            'state' => (string) config('shipping.shipper.state'),
            'district' => (string) config('shipping.shipper.city'),
            'country' => 'MY',
            'postCode' => (string) config('shipping.shipper.postcode'),
            'phone' => (string) config('shipping.shipper.phone'),
            'email' => (string) config('shipping.shipper.email'),
        ];
    }
}

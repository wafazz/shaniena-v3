<?php

namespace App\Services\Shipping;

use App\Contracts\Consignment;
use App\Contracts\ShippingGateway;
use App\Exceptions\ShipmentFailed;
use App\Models\JtSetting;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * J&T Express.
 *
 * No token: every request carries a signature.
 *
 *   signature = base64_encode(md5($json . $key))
 *
 * md5() without the raw flag, so that is base64 over the 32-character HEX
 * digest. J&T's own spec asks for raw binary and the source's own comment says
 * so — but the working integration hashes hex, so hex it stays. Byte-exactness
 * matters: the JSON is built with JSON_UNESCAPED_UNICODE and the key order below.
 *
 * Booked one order at a time. The source posted a batch and then matched AWBs
 * back by array position, walking a sorted id list against rows returned in
 * SELECT order — so AWBs could attach to the wrong parcels.
 */
class JtExpressGateway implements ShippingGateway
{
    public const NAME = 'J&T Express';

    public function name(): string
    {
        return self::NAME;
    }

    public function isConfigured(): bool
    {
        $credentials = JtSetting::current()?->credentials();

        return filled($credentials['url'] ?? null)
            && filled($credentials['username'] ?? null)
            && filled($credentials['key'] ?? null);
    }

    public function book(Order $order): Consignment
    {
        $setting = JtSetting::current();

        if (! $setting) {
            throw new ShipmentFailed('J&T Express is not configured.');
        }

        $credentials = $setting->credentials();
        $reference = 'ROZEYANA-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT);

        $detail = [$this->detail($order, $credentials, $reference)];
        $json = json_encode(['detail' => $detail], JSON_UNESCAPED_UNICODE);

        $response = Http::asForm()->timeout(30)->post($credentials['url'], [
            'data_param' => $json,
            'data_sign' => $this->sign($json, $credentials['key']),
        ]);

        $item = $response->json('details.0');
        $awb = $item['awb_no'] ?? null;

        if ($response->failed() || ($item['status'] ?? '') !== 'success' || blank($awb)) {
            Log::error('J&T refused a shipment.', [
                'order' => $order->id,
                'status' => $response->status(),
                'reason' => $item['reason'] ?? null,
            ]);

            throw new ShipmentFailed('J&T Express could not book that shipment.');
        }

        return new Consignment((string) $awb, 'https://jtexpress.my/tracking/'.$awb, self::NAME);
    }

    /** base64 of the HEX md5 digest — reproduced exactly, not "corrected". */
    public function sign(string $json, string $key): string
    {
        return base64_encode(md5($json.$key));
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    private function detail(Order $order, array $credentials, string $reference): array
    {
        $shipper = config('shipping.shipper');
        $weightKg = max(0.1, round((int) $order->lines()->sum('total_weight') / 1000, 2));

        return [
            'username' => $credentials['username'],
            // From the settings row, not hardcoded per mode as the source did.
            'api_key' => $credentials['key'],
            'cuscode' => $credentials['cuscode'],
            'password' => $credentials['password'],
            'orderid' => $reference,
            'shipper_name' => $shipper['name'],
            'shipper_contact' => $shipper['contact'],
            'shipper_phone' => $shipper['phone'],
            'shipper_addr' => trim($shipper['address1'].' '.$shipper['address2']),
            'sender_zip' => $shipper['postcode'],
            'receiver_name' => $order->customerFullName(),
            'receiver_addr' => trim($order->address_1.' '.$order->address_2),
            'receiver_phone' => (string) $order->customer_phone,
            'receiver_zip' => preg_replace('/\s+/', '', (string) $order->postcode),
            'qty' => (string) $order->total_qty,
            // The real weight, not the hardcoded 0.5 the source sent.
            'weight' => (string) $weightKg,
            'Item_name' => 'Order '.$order->reference(),
            'goodsdesc' => 'Order '.$order->reference(),
            'goodsvalue' => number_format((float) $order->myr_value_include_postage, 2, '.', ''),
            'payType' => '1',
            'expressType' => 'EZ',
            'goodsType' => 'PARCEL',
            'servicetype' => 'PICKUP',
            'sendstarttime' => Carbon::today()->setTime(10, 0)->format('Y-m-d H:i:s'),
            'sendendtime' => Carbon::today()->setTime(17, 0)->format('Y-m-d H:i:s'),
        ];
    }
}

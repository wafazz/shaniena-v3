<?php

namespace App\Services\Storefront;

use App\Models\Cart;
use App\Models\ListCountry;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderTempData;
use App\Services\AdminNavigation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns a basket plus a saved address into an order.
 *
 * Shared by every payment channel. The amount is recomputed here, from the
 * catalogue, at the moment the order is written — no caller passes a total in,
 * because in the source anything that could render the checkout view could set
 * what the gateway charged.
 */
class PlaceOrder
{
    public function __construct(private Basket $basket) {}

    /**
     * Create the order in a pending state and hand back the row plus the
     * authoritative amount to charge.
     *
     * @return array{order: Order, reference: string, amount: float, currency: string}
     */
    public function draft(string $cartToken, ListCountry $country, string $channel): array
    {
        $address = OrderTempData::forSession($cartToken)->latest('id')->first();

        if (! $address) {
            throw new RuntimeException('No delivery address has been saved for this basket.');
        }

        $summary = $this->basket->summary($cartToken, $country, $address->state, $channel === Order::CHANNEL_COD);

        if ($summary['items'] === []) {
            throw new RuntimeException('The basket is empty.');
        }

        if (! $summary['postage_known']) {
            throw new RuntimeException('Postage could not be worked out for that address.');
        }

        return DB::transaction(function () use ($cartToken, $country, $channel, $address, $summary) {
            $order = Order::create([
                'session_id' => $cartToken,
                'order_to' => 1,
                // Denormalised variant list, kept for parity with the source.
                'product_var_id' => collect($summary['items'])->pluck('variant_id')->implode(','),
                'total_qty' => collect($summary['items'])->sum('quantity'),
                'total_price' => $summary['subtotal'],
                'postage_cost' => $summary['postage'],
                'currency_sign' => $country->sign,
                'country_id' => $country->id,
                'country' => $country->name,
                'state' => $address->state,
                'city' => $address->city,
                'postcode' => $address->postcode,
                'address_1' => $address->add_1,
                'address_2' => (string) $address->add_2,
                'customer_name' => $address->first_name,
                'customer_name_last' => $address->last_name,
                'customer_phone' => $address->phone,
                'customer_email' => $address->email,
                'payment_channel' => $channel,
                'payment_code' => '',
                'payment_url' => '',
                'ship_channel' => 'courier',
                'courier_service' => (string) $address->method,
                'awb_number' => '',
                'tracking_url' => '',
                'remark_comment' => (string) $address->remark,
                'tracking_milestone' => '',
                // The rate in force at the time of the order, not 1 as the
                // source always wrote.
                'to_myr_rate' => (float) $country->rate,
                'myr_value_include_postage' => $summary['total'],
                'myr_value_without_postage' => $summary['subtotal'],
                // COD is placed as a live order; gateway payments wait for the
                // callback to confirm them.
                'status' => $channel === Order::CHANNEL_COD
                    ? Order::STATUS_NEW
                    : Order::STATUS_AWAITING_PAYMENT,
                'printed_awb' => 0,
            ]);

            OrderDetail::create([
                'order_id' => $order->id,
                // 64 random hex characters, not sha256(id_name_timestamp).
                // The source's hash was derived entirely from guessable inputs
                // and was the only thing protecting the order page.
                'hash_code' => bin2hex(random_bytes(32)),
            ]);

            return [
                'order' => $order,
                'reference' => $order->reference(),
                'amount' => $summary['total'],
                'currency' => $country->sign,
            ];
        });
    }

    /**
     * Confirm a paid order and retire its basket.
     *
     * Idempotent: a gateway that delivers the same callback twice — which they
     * all do — must not double-confirm or double-decrement stock.
     */
    public function confirm(Order $order, string $paymentCode = ''): bool
    {
        if ((int) $order->status !== Order::STATUS_AWAITING_PAYMENT) {
            return false;
        }

        DB::transaction(function () use ($order, $paymentCode) {
            $order->forceFill([
                'status' => Order::STATUS_NEW,
                'payment_code' => $paymentCode ?: $order->payment_code,
            ])->save();

            Cart::query()
                ->where('session_id', $order->session_id)
                ->whereIn('status', Cart::STATUS_ACTIVE)
                ->update(['status' => Cart::STATUS_PAID, 'updated_at' => now()]);
        });

        app(AdminNavigation::class)->flushCounts();

        return true;
    }

    /** Mark a gateway payment as failed, leaving the basket intact to retry. */
    public function fail(Order $order): void
    {
        if ((int) $order->status === Order::STATUS_AWAITING_PAYMENT) {
            $order->forceFill(['status' => Order::STATUS_AWAITING_PAYMENT])->save();
        }
    }

    public function detailFor(Order $order): ?OrderDetail
    {
        return OrderDetail::where('order_id', $order->id)->latest('id')->first();
    }

    /** A fresh basket token, so a confirmed order's cart is not reused. */
    public function newCartToken(): string
    {
        return Str::random(40);
    }
}

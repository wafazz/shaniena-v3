<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Order;
use App\Models\PostcodeMy;
use App\Models\StateMy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * One order, in full — the panel staff open from a queue row.
 *
 * The source rendered this inline for every row on the page and ran four raw
 * queries per variant to do it. It is fetched for the one order being looked
 * at now.
 */
class OrderDetailController extends Controller
{
    /** The delivery fields staff are allowed to correct. */
    private const ADDRESS_FIELDS = [
        'customer_name', 'customer_name_last', 'customer_phone', 'customer_email',
        'address_1', 'address_2', 'city', 'state', 'postcode',
    ];

    public function show(Order $order): JsonResponse
    {
        $order->load(['lines' => fn ($q) => $q->active()->with([
            'product:id,name,slug',
            'variant:id,product_id,variant_name,sku',
        ])]);

        return response()->json([
            'id' => $order->id,
            'reference' => $order->reference(),
            'status' => (int) $order->status,
            'placed_at' => $order->created_at?->format('j M Y, h:iA'),
            'updated_at' => $order->updated_at?->format('j M Y, h:iA'),

            'customer' => [
                'first_name' => (string) $order->customer_name,
                'last_name' => (string) $order->customer_name_last,
                'phone' => (string) $order->customer_phone,
                'email' => (string) $order->customer_email,
            ],
            'address' => [
                'address_1' => (string) $order->address_1,
                'address_2' => (string) $order->address_2,
                'city' => (string) $order->city,
                'state' => (string) $order->state,
                'postcode' => (string) $order->postcode,
                'country' => (string) $order->country,
            ],

            'payment' => [
                'channel' => $order->payment_channel,
                'code' => $order->payment_code ?: null,
                'paid' => (int) $order->status !== Order::STATUS_AWAITING_PAYMENT,
            ],

            'shipping' => [
                'courier' => $order->courier_service ?: null,
                'channel' => $order->ship_channel ?: null,
                'awb' => $order->awb_number ?: null,
                'tracking_url' => $order->tracking_url ?: null,
                'milestone' => $order->tracking_milestone ?: null,
                'printed' => (bool) $order->printed_awb,
            ],

            'money' => [
                'currency' => $order->currency_sign,
                'items' => (float) $order->total_price,
                'postage' => (float) $order->postage_cost,
                'total' => (float) $order->myr_value_include_postage,
                'rate' => (float) $order->to_myr_rate,
                'quantity' => (int) $order->total_qty,
            ],

            'remark' => $order->remark_comment ?: null,

            'lines' => $order->lines->map(fn ($line) => [
                'id' => $line->id,
                'name' => $line->product?->name ?? 'Product removed',
                'slug' => $line->product?->slug,
                'variant' => $line->variant?->variant_name,
                'sku' => $line->variant?->sku,
                'quantity' => (int) $line->quantity,
                'price' => (float) $line->price,
                'line_total' => $line->lineTotal(),
                'weight' => (int) $line->total_weight,
            ])->all(),

            // Editing a booked parcel's address does not reach the courier.
            'address_is_with_courier' => filled($order->awb_number),
            'states' => StateMy::query()->orderBy('state_name')->pluck('state_name')->all(),
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_name_last' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_email' => ['required', 'email', 'max:255'],
            'address_1' => ['required', 'string', 'max:255'],
            'address_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'postcode' => ['required', 'string', 'max:20'],
            'remark_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['address_2'] ??= '';
        $data['customer_name_last'] ??= '';
        $data['remark_comment'] ??= '';

        $changed = collect(self::ADDRESS_FIELDS)
            ->filter(fn (string $field) => (string) $order->{$field} !== (string) $data[$field])
            ->values();

        if ($changed->isEmpty() && (string) $order->remark_comment === $data['remark_comment']) {
            return back()->with('success', 'Nothing to change.');
        }

        $booked = filled($order->awb_number);

        DB::transaction(function () use ($order, $data, $changed, $booked, $request) {
            $order->forceFill($data)->save();

            Activity::record(
                (int) $request->user('admin')->getKey(),
                'Edited order '.$order->reference().' ('.$changed->implode(', ').')'
                    .($booked ? ' AFTER AWB '.$order->awb_number.' was booked — the courier still has the old address.' : ''),
                "customer_orders|{$order->id}",
                'order_activity',
            );
        });

        return back()->with(
            $booked ? 'warning' : 'success',
            $booked
                ? 'Saved — but AWB '.$order->awb_number.' is already with the courier, which still has the old address.'
                : 'Order '.$order->reference().' updated.',
        );
    }

    /** Postcode → city and state, so staff do not have to guess. */
    public function lookupPostcode(Request $request): JsonResponse
    {
        $postcode = preg_replace('/\D/', '', (string) $request->query('postcode', ''));

        if (strlen((string) $postcode) !== 5) {
            return response()->json(['city' => null, 'state' => null]);
        }

        $row = PostcodeMy::query()->with('state')->where('postcode', $postcode)->first();

        return response()->json([
            'city' => $row?->post_office,
            'state' => $row?->state?->state_name,
        ]);
    }
}

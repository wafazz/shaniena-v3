<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer's own account: details and order history.
 *
 * The source had no order-history page at all — the only way to see an order
 * was the emailed link or the tracking form.
 */
class AccountController extends Controller
{
    public function show(Request $request): Response
    {
        $member = $request->user('web');

        return Inertia::render('Shop/Account', [
            'member' => [
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
                'address_1' => $member->address_1,
                'address_2' => $member->address_2,
                'city' => $member->city,
                'postcode' => $member->postcode,
                'state' => $member->state,
            ],
            'orders' => Order::query()
                ->where('customer_email', $member->email)
                ->with(['lines' => fn ($q) => $q->active()->with('product:id,name,slug')])
                ->latest('id')
                ->paginate(10)
                ->through(fn (Order $order) => [
                    'reference' => $order->reference(),
                    'placed_at' => $order->created_at?->format('j M Y'),
                    'status' => $order->statusLabel(),
                    'currency' => $order->currency_sign,
                    'total' => number_format((float) $order->myr_value_include_postage, 2),
                    'courier' => $order->courier_service ?: null,
                    'awb' => $order->awb_number ?: null,
                    'tracking_url' => $order->tracking_url ?: null,
                    'items' => $order->lines->map(fn ($line) => [
                        'name' => $line->product?->name ?? 'Product removed',
                        'slug' => $line->product?->slug,
                        'quantity' => (int) $line->quantity,
                    ])->all(),
                ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $member = $request->user('web');

        $member->update($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:50'],
            'address_1' => ['nullable', 'string', 'max:255'],
            'address_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:50'],
            'postcode' => ['nullable', 'string', 'max:50'],
            'state' => ['nullable', 'string', 'max:50'],
        ]));

        return back()->with('success', 'Details saved.');
    }
}

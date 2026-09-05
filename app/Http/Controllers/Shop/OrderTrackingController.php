<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Order tracking.
 *
 * Requires the order number AND the email it was placed with. The source's
 * order-details page was reachable by a sha256 of guessable inputs alone.
 */
class OrderTrackingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $order = null;
        $searched = $request->filled('order') || $request->filled('email');

        if ($searched) {
            $data = $request->validate([
                'order' => ['required', 'string', 'max:20'],
                'email' => ['required', 'email'],
            ]);

            $order = Order::query()
                ->where('id', (int) ltrim($data['order'], '#0') ?: 0)
                ->where('customer_email', $data['email'])
                ->with(['lines' => fn ($q) => $q->active()->with('product:id,name')])
                ->first();
        }

        return Inertia::render('Shop/Track', [
            'searched' => $searched,
            'order' => $order ? [
                'reference' => $order->reference(),
                'placed_at' => $order->created_at?->format('j M Y'),
                'status' => $order->statusLabel(),
                'courier' => $order->courier_service ?: null,
                'awb' => $order->awb_number ?: null,
                'tracking_url' => $order->tracking_url ?: null,
                'currency' => $order->currency_sign,
                'total' => number_format((float) $order->myr_value_include_postage, 2),
                'items' => $order->lines->map(fn ($line) => [
                    'name' => $line->product?->name ?? 'Product removed',
                    'quantity' => (int) $line->quantity,
                ])->all(),
            ] : null,
        ]);
    }
}

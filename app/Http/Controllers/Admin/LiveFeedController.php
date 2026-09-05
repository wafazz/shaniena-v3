<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DashboardMetrics;
use Illuminate\Http\JsonResponse;

/**
 * The dashboard's live figures, polled by the browser.
 *
 * Replaces the source's `live-orders.php`: a server-sent-event script with an
 * infinite loop that held a PHP worker open per signed-in admin and fired
 * eight uncached queries every two seconds — against a connection whose root
 * password was written into the file.
 */
class LiveFeedController extends Controller
{
    public function __invoke(DashboardMetrics $metrics): JsonResponse
    {
        return response()->json([
            ...$metrics->live(),
            'orders' => Order::query()
                ->whereIn('status', [
                    Order::STATUS_NEW,
                    Order::STATUS_PROCESSING,
                    Order::STATUS_IN_DELIVERY,
                    Order::STATUS_COMPLETED,
                    Order::STATUS_RETURNED,
                    Order::STATUS_CANCELLED,
                ])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'reference' => $order->reference(),
                    'customer' => $order->customerFullName(),
                    'country' => $order->country,
                    'quantity' => (int) $order->total_qty,
                    'total' => (float) $order->myr_value_include_postage,
                    'currency' => $order->currency_sign,
                    'status' => (int) $order->status,
                ])->all(),
        ]);
    }
}

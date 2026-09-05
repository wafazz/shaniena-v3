<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Cart;
use App\Models\Order;
use App\Services\AdminNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves orders between lifecycle stages.
 *
 * Two things change from the source. Transitions are checked against
 * Order::ALLOWED_TRANSITIONS rather than trusted from the URL — the source put
 * the from/to pair in the href, so any status could be forced. And
 * moveToProcessing() ran with no checkAccess() call at all, which this route
 * closes by carrying the same `page:` middleware as every other admin route.
 */
class OrderStatusController extends Controller
{
    public function update(Request $request, Order $order): RedirectResponse
    {
        $to = (int) $request->validate([
            'to' => ['required', 'integer'],
        ])['to'];

        if (! $order->canMoveTo($to)) {
            throw ValidationException::withMessages([
                'to' => "A {$order->statusLabel()} order can't be moved to ".(Order::STATUSES[$to] ?? 'that status').'.',
            ]);
        }

        $this->move($request, [$order], $to);

        return back()->with('success', "Order {$order->reference()} moved to ".Order::STATUSES[$to].'.');
    }

    /** Bulk stage move — the source's "Bulk Move In Delivery". */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'to' => ['required', 'integer'],
            'orders' => ['required', 'array', 'min:1'],
            'orders.*' => ['integer'],
        ]);

        $to = (int) $validated['to'];
        $orders = Order::query()->whereIn('id', $validated['orders'])->get();

        $movable = $orders->filter(fn (Order $o) => $o->canMoveTo($to));
        $blocked = $orders->count() - $movable->count();

        if ($movable->isEmpty()) {
            return back()->with('error', 'None of those orders can move to '.Order::STATUSES[$to].'.');
        }

        $this->move($request, $movable->all(), $to);

        $message = $movable->count().' order'.($movable->count() === 1 ? '' : 's').' moved to '.Order::STATUSES[$to].'.';

        if ($blocked > 0) {
            $message .= " {$blocked} skipped — not at a stage that allows it.";
        }

        return back()->with('success', $message);
    }

    /**
     * @param  list<Order>  $orders
     */
    private function move(Request $request, array $orders, int $to): void
    {
        $cartStatus = Order::CART_STATUS_ON_TRANSITION[$to] ?? null;
        $actorId = (int) $request->user('admin')->getKey();

        DB::transaction(function () use ($orders, $to, $cartStatus, $actorId) {
            foreach ($orders as $order) {
                $order->forceFill(['status' => $to])->save();

                // Returning or cancelling an order moves its basket lines too.
                if ($cartStatus !== null) {
                    Cart::query()
                        ->where('session_id', $order->session_id)
                        ->update(['status' => $cartStatus, 'updated_at' => now()]);
                }

                Activity::record(
                    $actorId,
                    "Set order {$order->reference()} to ".Order::STATUSES[$to],
                    "customer_orders|{$order->id}",
                    'order_activity',
                );
            }
        });

        // The sidebar badges are the operator's work signal; a stale count
        // after moving a queue's worth of orders is worse than no count.
        app(AdminNavigation::class)->flushCounts();
    }
}

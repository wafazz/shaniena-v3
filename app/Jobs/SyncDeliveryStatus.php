<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\AdminNavigation;
use App\Services\Shipping\JtTracking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Polls the courier for every parcel that is out for delivery and completes
 * the ones that have landed.
 *
 * The source kept its place in a `last_processed.json` file that only ever
 * moved forward, so an order shipped after the cursor had passed its id was
 * skipped until the cursor happened to reset. This walks the whole in-delivery
 * set in id order and lets the status itself decide when an order leaves it —
 * a completed order is no longer in delivery, so it will not be polled again.
 */
class SyncDeliveryStatus implements ShouldQueue
{
    use Queueable;

    /** The scan text J&T returns for a parcel that has been handed over. */
    public const DELIVERED = 'delivered';

    public int $tries = 1;

    public function handle(JtTracking $tracking, AdminNavigation $navigation): void
    {
        if (! $tracking->isConfigured()) {
            Log::info('Skipping delivery sync: J&T tracking is not configured.');

            return;
        }

        $completed = 0;
        $checked = 0;

        Order::query()
            ->where('status', Order::STATUS_IN_DELIVERY)
            ->whereNotNull('awb_number')
            ->where('awb_number', '!=', '')
            ->orderBy('id')
            ->limit((int) config('shop.tracking.batch', 100))
            ->get()
            ->each(function (Order $order) use ($tracking, &$completed, &$checked) {
                $status = $tracking->latestStatus((string) $order->awb_number);
                $checked++;

                if (blank($status)) {
                    return;
                }

                // The milestone is recorded whatever it says, so support can
                // see where a parcel is without opening the courier's site.
                $order->forceFill(['tracking_milestone' => $status]);

                if (strcasecmp(trim($status), self::DELIVERED) === 0) {
                    $order->forceFill(['status' => Order::STATUS_COMPLETED]);
                    $completed++;
                }

                $order->save();
            });

        if ($completed > 0) {
            $navigation->flushCounts();
        }

        Log::info('Delivery status sync finished.', ['checked' => $checked, 'completed' => $completed]);
    }
}

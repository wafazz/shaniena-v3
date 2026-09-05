<?php

namespace App\Jobs;

use App\Models\Cart;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Marks unpaid baskets abandoned once they have gone quiet.
 *
 * The source read every open cart row into PHP and issued one UPDATE per row,
 * echoing HTML as it went. This is one indexed UPDATE, chunked so a large
 * backlog cannot hold a write lock over the whole table.
 */
class ExpireAbandonedCarts implements ShouldQueue
{
    use Queueable;

    /** Only ever one of these in flight. */
    public int $tries = 1;

    public function handle(): void
    {
        $cutoff = now()->subMinutes((int) config('shop.cart_abandon_minutes', 10));
        $expired = 0;

        do {
            $affected = Cart::query()
                ->where('status', Cart::STATUS_UNPAID)
                ->whereNull('deleted_at')
                ->where('updated_at', '<', $cutoff)
                ->limit(500)
                ->update([
                    'status' => Cart::STATUS_REMOVED,
                    'updated_at' => now(),
                    'deleted_at' => now(),
                ]);

            $expired += $affected;
        } while ($affected > 0);

        if ($expired > 0) {
            Log::info('Expired abandoned baskets.', ['rows' => $expired, 'cutoff' => $cutoff->toDateTimeString()]);
        }
    }
}

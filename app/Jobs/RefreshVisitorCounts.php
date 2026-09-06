<?php

namespace App\Jobs;

use App\Services\Storefront\Visitors;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recomputes the visitor figures the dashboard and the storefront card show.
 *
 * The source wrote these to `live_visitors.json` inside the web root and
 * chmod'd it 0666 — world-readable traffic figures, world-writable file. They
 * live in the cache now, where nothing outside the app can reach them.
 *
 * The counting itself belongs to the Visitors service, which is also what
 * records a visit; this is the minute hand.
 */
class RefreshVisitorCounts implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    // Resolved rather than injected: the existing tests call handle() bare,
    // and a job whose whole body is one service call gains nothing from a
    // signature they would all have to pass through.
    public function handle(): void
    {
        app(Visitors::class)->refresh();
    }

    /**
     * The figures, with the key names the admin dashboard has always used.
     *
     * `live`/`all` are aliases: both are now counts of distinct visitors
     * rather than of rows, because two rows from one IP are one visitor who
     * came back, and a tile that says otherwise is just wrong.
     *
     * @return array{live: int, all: int, today: int, week: int, month: int, overall: int, online: int, updated_at: ?string}
     */
    public static function counts(): array
    {
        $counts = app(Visitors::class)->counts();

        return [
            ...$counts,
            'live' => $counts['online'],
            'all' => $counts['overall'],
        ];
    }
}

<?php

namespace App\Jobs;

use App\Models\OnlineVisitor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Recomputes the visitor tiles the dashboard shows.
 *
 * The source wrote these to `live_visitors.json` inside the web root and
 * chmod'd it 0666 — world-readable traffic figures, world-writable file. They
 * live in the cache now, where nothing outside the app can reach them.
 */
class RefreshVisitorCounts implements ShouldQueue
{
    use Queueable;

    public const CACHE_KEY = 'dashboard:visitors';

    public int $tries = 1;

    public function handle(): void
    {
        Cache::put(self::CACHE_KEY, [
            'live' => OnlineVisitor::query()->live()->count(),
            'all' => OnlineVisitor::query()->count(),
            'today' => OnlineVisitor::query()->whereDate('created_at', today())->count(),
            'updated_at' => now()->toIso8601String(),
        ], now()->addMinutes(10));
    }

    /** @return array{live: int, all: int, today: int, updated_at: ?string} */
    public static function counts(): array
    {
        return Cache::get(self::CACHE_KEY, ['live' => 0, 'all' => 0, 'today' => 0, 'updated_at' => null]);
    }
}

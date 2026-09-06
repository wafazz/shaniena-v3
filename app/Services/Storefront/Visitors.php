<?php

namespace App\Services\Storefront;

use App\Models\OnlineVisitor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Who is on the shop, and how many have been.
 *
 * The table this reads (`online_visitor_return`) came across with the schema
 * migration, and **nothing had ever written to it** — the source's counter
 * lived in a `live_visitors.json` file in the web root, chmod 0666, and the
 * migrated dashboard tiles have been reading an empty table ever since. This
 * is the missing half: recording a visit, and counting them.
 *
 * A "visitor" is a distinct IP address, which is what the migrated column
 * gives us. That undercounts an office behind one NAT and overcounts a phone
 * moving between wifi and mobile data; it is the same definition the source
 * used, and the number is a shop-front figure, not analytics.
 *
 * A row is one *session*: while somebody keeps browsing, their row's
 * `session_end_at` is pushed forward, and a return after the window lapses
 * starts a new one. So rows count sessions and `DISTINCT ip_address` counts
 * visitors, which is what makes the per-period figures mean anything.
 */
class Visitors
{
    public const CACHE_KEY = 'storefront:visitors';

    /** How long after their last page view somebody still counts as online. */
    public const SESSION_MINUTES = 5;

    /** At most one write per visitor per this window, however fast they click. */
    public const WRITE_EVERY_SECONDS = 60;

    /**
     * How old a cached figure may be before a poll recomputes it.
     *
     * Matched to the card's poll interval, because "online now" is the one
     * figure that has to be true when you look at it: at 60 seconds somebody
     * who just arrived is missing from a panel that claims to be live.
     *
     * The cost of that is bounded, not per-shopper — the scheduled job usually
     * gets there first, and when it does not, the lock below means one poll
     * runs the aggregate and every other tab is served its result.
     */
    public const STALE_AFTER_SECONDS = 20;

    /**
     * Crawlers are traffic, not visitors. Left deliberately short — a list
     * that tries to name every bot is a list that goes stale.
     */
    private const BOTS = '/bot|crawl|spider|slurp|bingpreview|headlesschrome|lighthouse|curl|wget|python-requests|axios|postman|symfony|guzzle|okhttp/i';

    public function record(Request $request): void
    {
        $ip = (string) $request->ip();

        if ($ip === '' || $this->isBot($request)) {
            return;
        }

        // The guard is the point: a shopper clicking through ten pages in a
        // minute must not be ten writes. Redis holds the flag, so the database
        // sees at most one statement per visitor per minute.
        $seen = 'visitor:seen:'.sha1($ip);

        if (Cache::get($seen)) {
            return;
        }

        Cache::put($seen, true, self::WRITE_EVERY_SECONDS);

        $endsAt = now()->addMinutes(self::SESSION_MINUTES);

        // Extend the session they are already in — this uses the migrated
        // (ip_address, created_at) index rather than scanning.
        $extended = OnlineVisitor::query()
            ->where('ip_address', $ip)
            ->where('created_at', '>=', now()->subDay())
            ->where('session_end_at', '>=', now())
            ->update(['session_end_at' => $endsAt, 'updated_at' => now()]);

        if ($extended === 0) {
            OnlineVisitor::create(['ip_address' => $ip, 'session_end_at' => $endsAt]);
        }
    }

    /**
     * The five figures the storefront card shows.
     *
     * @return array{online: int, today: int, week: int, month: int, overall: int, updated_at: string}
     */
    public function refresh(): array
    {
        // One pass over the table with conditional aggregation, not five
        // separate COUNT(DISTINCT) queries over the same rows.
        $row = OnlineVisitor::query()
            ->selectRaw('COUNT(DISTINCT CASE WHEN session_end_at >= ? THEN ip_address END) AS online', [now()])
            ->selectRaw('COUNT(DISTINCT CASE WHEN created_at >= ? THEN ip_address END) AS today', [now()->startOfDay()])
            ->selectRaw('COUNT(DISTINCT CASE WHEN created_at >= ? THEN ip_address END) AS week', [now()->startOfWeek()])
            ->selectRaw('COUNT(DISTINCT CASE WHEN created_at >= ? THEN ip_address END) AS month', [now()->startOfMonth()])
            ->selectRaw('COUNT(DISTINCT ip_address) AS overall')
            ->first();

        $counts = [
            'online' => (int) ($row->online ?? 0),
            'today' => (int) ($row->today ?? 0),
            'week' => (int) ($row->week ?? 0),
            'month' => (int) ($row->month ?? 0),
            'overall' => (int) ($row->overall ?? 0),
            // Every payload carries when it was computed, so a stalled cache
            // reads as stale rather than as a dead store.
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put(self::CACHE_KEY, $counts, now()->addMinutes(10));

        return $counts;
    }

    /**
     * What the card is served.
     *
     * Read from cache, refreshed once a minute by the scheduled job. If the
     * queue worker is not running — every local machine, and any server where
     * it has died — this computes once and caches it for a minute rather than
     * showing zeroes forever.
     *
     * @return array{online: int, today: int, week: int, month: int, overall: int, updated_at: string}
     */
    public function counts(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && ! $this->isStale($cached)) {
            return $cached;
        }

        // One recompute at a time. Without this every open tab on a busy shop
        // would run the same aggregate the moment the figure went stale.
        if (! Cache::add(self::CACHE_KEY.':computing', true, 15)) {
            return is_array($cached) ? $cached : $this->empty();
        }

        try {
            return $this->refresh();
        } finally {
            Cache::forget(self::CACHE_KEY.':computing');
        }
    }

    private function isStale(array $counts): bool
    {
        $at = $counts['updated_at'] ?? null;

        if (! is_string($at)) {
            return true;
        }

        return Carbon::parse($at)->lt(now()->subSeconds(self::STALE_AFTER_SECONDS));
    }

    /** @return array{online: int, today: int, week: int, month: int, overall: int, updated_at: string} */
    private function empty(): array
    {
        return [
            'online' => 0, 'today' => 0, 'week' => 0, 'month' => 0, 'overall' => 0,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /** Rows nobody will ever count again — kept out of the distinct scans. */
    public function prune(int $keepDays = 400): int
    {
        return OnlineVisitor::query()
            ->where('created_at', '<', now()->subDays($keepDays))
            ->delete();
    }

    private function isBot(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        return $agent === '' || preg_match(self::BOTS, $agent) === 1;
    }

    /** Only ordinary page views count — not JSON polls, not form posts. */
    public static function shouldRecord(Request $request): bool
    {
        return $request->isMethod('GET')
            && ! $request->expectsJson()
            && ! $request->headers->has('X-Inertia-Partial-Component')
            // The card polls this every 20 seconds; counting its own polls
            // would keep an abandoned open tab "online" indefinitely.
            && ! $request->is('visitors', 'cart/summary');
    }
}

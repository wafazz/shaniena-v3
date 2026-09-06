<?php

use App\Jobs\RefreshVisitorCounts;
use App\Models\OnlineVisitor;
use App\Services\Storefront\Visitors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * The storefront visitor counter.
 *
 * Two halves, and the first one had never existed: `online_visitor_return`
 * came across with the schema and nothing wrote to it, so every visitor tile
 * in the migrated console has been reading an empty table.
 */

/** A page view from a real browser at a given address. */
function visit(string $path = '/', string $ip = '203.0.113.10')
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/152 Safari/537.36')
        ->get($path);
}

function visitorRow(string $ip, array $attributes = []): OnlineVisitor
{
    $row = OnlineVisitor::create([
        'ip_address' => $ip,
        'session_end_at' => $attributes['session_end_at'] ?? now()->addMinutes(5),
    ]);

    // `created_at` is not fillable — the row is backdated after the insert, or
    // every one of these lands on today and the period figures mean nothing.
    if (isset($attributes['created_at'])) {
        OnlineVisitor::query()->whereKey($row->id)->update(['created_at' => $attributes['created_at']]);
        $row->refresh();
    }

    return $row;
}

// --- recording -----------------------------------------------------------

it('records a visitor the first time a page is opened', function () {
    shopCountry();

    visit('/')->assertOk();

    expect(OnlineVisitor::count())->toBe(1)
        ->and(OnlineVisitor::first()->ip_address)->toBe('203.0.113.10');
});

it('does not write a row for every page a shopper opens', function () {
    shopCountry();

    visit('/');
    visit('/shop');
    visit('/blog');
    visit('/contact');

    // One write per visitor per minute, however fast they click. Their session
    // still ends five minutes after that last page view.
    expect(OnlineVisitor::count())->toBe(1);
});

it('starts a new session when someone comes back after theirs has lapsed', function () {
    shopCountry();

    visit('/');
    Cache::flush();                                  // the per-minute write guard
    OnlineVisitor::query()->update(['session_end_at' => now()->subMinute()]);

    visit('/');

    expect(OnlineVisitor::count())->toBe(2)
        // Two sessions, one visitor.
        ->and(app(Visitors::class)->refresh()['today'])->toBe(1);
});

it('counts crawlers as traffic, not as visitors', function () {
    shopCountry();

    $this->withServerVariables(['REMOTE_ADDR' => '66.249.66.1'])
        ->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
        ->get('/')
        ->assertOk();

    expect(OnlineVisitor::count())->toBe(0);
});

it('does not count the card’s own polling', function () {
    shopCountry();

    visit('/visitors')->assertOk();
    visit('/cart/summary')->assertOk();

    // Otherwise a tab left open in a background window would stay "online"
    // for as long as the browser was running.
    expect(OnlineVisitor::count())->toBe(0);
});

// --- counting ------------------------------------------------------------

it('counts one visitor once, however many sessions they had', function () {
    visitorRow('198.51.100.1');
    visitorRow('198.51.100.1', ['session_end_at' => now()->subHour()]);
    visitorRow('198.51.100.2');

    $counts = app(Visitors::class)->refresh();

    expect($counts['today'])->toBe(2)
        ->and($counts['overall'])->toBe(2)
        // Only one of those three sessions is still live, and it belongs to .1;
        // .2's is live too, so two people are on the site.
        ->and($counts['online'])->toBe(2);
});

it('separates today, this week, this month and all time', function () {
    // A fixed mid-week, mid-month Wednesday. Anchoring on a relative date
    // makes this pass or fail depending on the day it runs: pick the 21st and
    // it is a Monday in some months, where "start of week" is today and the
    // week figure collapses into the day figure.
    $this->travelTo(Carbon::parse('2026-09-16 12:00:00'));

    visitorRow('203.0.113.1', ['created_at' => now()]);
    visitorRow('203.0.113.2', ['created_at' => now()->startOfWeek()->addHour()]);
    visitorRow('203.0.113.3', ['created_at' => now()->startOfMonth()->addHour()]);
    visitorRow('203.0.113.4', ['created_at' => now()->subMonths(3)]);

    $counts = app(Visitors::class)->refresh();

    expect($counts['today'])->toBe(1)
        ->and($counts['week'])->toBe(2)
        ->and($counts['month'])->toBe(3)
        ->and($counts['overall'])->toBe(4);
});

it('counts as online only the sessions that have not run out', function () {
    visitorRow('203.0.113.1', ['session_end_at' => now()->addMinutes(5)]);
    visitorRow('203.0.113.2', ['session_end_at' => now()->addSeconds(30)]);
    visitorRow('203.0.113.3', ['session_end_at' => now()->subSecond()]);

    expect(app(Visitors::class)->refresh()['online'])->toBe(2);
});

// --- the endpoint --------------------------------------------------------

it('serves the five figures and nothing that identifies anybody', function () {
    visitorRow('203.0.113.9');

    $response = $this->get('/visitors', ['Accept' => 'application/json'])->assertOk();
    $payload = $response->json();

    expect(array_keys($payload))->toEqualCanonicalizing(
        ['online', 'today', 'week', 'month', 'overall', 'updated_at'],
    );

    // The panel is public. Aggregates only — never an address, a session or a
    // page somebody looked at.
    expect($response->getContent())->not->toContain('203.0.113.9');
});

it('answers with real numbers even when the queue worker is not running', function () {
    visitorRow('203.0.113.5');
    Cache::flush();

    // Nothing has refreshed the cache; the endpoint computes once rather than
    // reporting zero to every shopper until a worker comes back.
    expect($this->get('/visitors', ['Accept' => 'application/json'])->json('overall'))->toBe(1);
});

it('serves later polls from the cache instead of querying again', function () {
    visitorRow('203.0.113.6');
    $this->get('/visitors', ['Accept' => 'application/json'])->assertOk();

    DB::enableQueryLog();
    $this->get('/visitors', ['Accept' => 'application/json'])->assertOk();
    $queries = collect(DB::getQueryLog())->pluck('query')
        ->filter(fn ($q) => str_contains($q, 'online_visitor_return'));
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
});

it('recomputes a figure that has gone stale, so a dead scheduler is not a dead card', function () {
    visitorRow('203.0.113.11');
    $this->get('/visitors', ['Accept' => 'application/json']);

    // Somebody arrives, and the job that would have noticed is not running.
    visitorRow('203.0.113.12');
    $this->travel(Visitors::STALE_AFTER_SECONDS + 5)->seconds();

    expect($this->get('/visitors', ['Accept' => 'application/json'])->json('overall'))->toBe(2);
});

it('does not recompute for every tab the moment a figure goes stale', function () {
    visitorRow('203.0.113.13');
    $this->get('/visitors', ['Accept' => 'application/json']);
    $this->travel(Visitors::STALE_AFTER_SECONDS + 5)->seconds();

    // A recompute is in flight; a second poll arriving in that window is
    // served the figure we have rather than running the aggregate again.
    Cache::add(Visitors::CACHE_KEY.':computing', true, 15);

    DB::enableQueryLog();
    $this->get('/visitors', ['Accept' => 'application/json'])->assertOk();
    $ran = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'online_visitor_return'));
    DB::disableQueryLog();

    expect($ran)->toBeEmpty();
});

it('keeps the console’s own tiles working off the same figures', function () {
    visitorRow('203.0.113.7');
    visitorRow('203.0.113.8', ['session_end_at' => now()->subHour()]);

    app(Visitors::class)->refresh();
    $counts = RefreshVisitorCounts::counts();

    // `live` and `all` are the names the dashboard has always used.
    expect($counts['live'])->toBe(1)
        ->and($counts['all'])->toBe(2)
        ->and($counts['week'])->toBe(2);
});

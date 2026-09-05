<?php

use App\Models\MemberHq;
use App\Models\Order;
use App\Models\RoleAccess;
use App\Services\AdminNavigation;
use App\Services\PageAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function navAdmin(): MemberHq
{
    $id = DB::table('member_hq')->insertGetId([
        'email' => 'nav'.uniqid().'@example.test',
        'password' => bcrypt('secret'),
        'sec_pin' => '1234',
        'f_name' => 'Nav',
        'l_name' => 'Tester',
        'phone' => '0100000000',
        'role' => MemberHq::ROLE_STAFF_LOGISTIC,
        'status' => MemberHq::STATUS_ACTIVE,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return MemberHq::findOrFail($id);
}

function grant(MemberHq $user, string ...$slugs): void
{
    foreach ($slugs as $i => $slug) {
        RoleAccess::create([
            'page_url' => $slug,
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'allowed_user' => '['.$user->id.']',
            'sort' => $i,
        ]);
    }

    app(PageAccess::class)->flushFor($user->id);
}

function slugsIn(array $nav): array
{
    $out = [];

    foreach ($nav as $entry) {
        if ($entry['type'] === 'item') {
            $out[] = $entry['slug'];
        } elseif ($entry['type'] === 'group') {
            foreach ($entry['items'] as $child) {
                $out[] = $child['slug'];
            }
        }
    }

    return $out;
}

beforeEach(fn () => Cache::forget(AdminNavigation::COUNTS_CACHE_KEY));

it('shows only the sections the admin has been granted', function () {
    $user = navAdmin();
    grant($user, 'new-order', 'process-order');

    $slugs = slugsIn(app(AdminNavigation::class)->for($user));

    expect($slugs)->toContain('new-order', 'process-order')
        ->and($slugs)->not->toContain('dashboard', 'hq-staff', 'stock-control');
});

it('always offers profile and password, which were never role-gated', function () {
    $slugs = slugsIn(app(AdminNavigation::class)->for(navAdmin()));

    expect($slugs)->toBe(['profile', 'password']);
});

it('drops a group whose every child is denied', function () {
    $user = navAdmin();
    grant($user, 'dashboard');

    $labels = collect(app(AdminNavigation::class)->for($user))
        ->where('type', 'group')->pluck('label')->all();

    expect($labels)->toBeEmpty();
});

it('drops a section title left standing over nothing', function () {
    $user = navAdmin();
    grant($user, 'dashboard');

    $titles = collect(app(AdminNavigation::class)->for($user))
        ->where('type', 'title')->pluck('label')->all();

    // "Settings", "Support" and "Account" all have no visible children —
    // except Account, which keeps Profile/Password.
    expect($titles)->toBe(['Account']);
});

it('counts each order queue in a single grouped query', function () {
    $user = navAdmin();
    grant($user, 'new-order', 'process-order');

    Order::factory()->status(Order::STATUS_NEW)->count(2)->create();
    Order::factory()->status(Order::STATUS_PROCESSING)->create();

    DB::enableQueryLog();
    $nav = app(AdminNavigation::class)->for($user);
    $countQueries = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'customer_orders'))
        ->count();
    DB::disableQueryLog();

    $items = collect($nav)->firstWhere('type', 'group')['items'];

    expect(collect($items)->firstWhere('slug', 'new-order')['count'])->toBe(2)
        ->and(collect($items)->firstWhere('slug', 'process-order')['count'])->toBe(1)
        // The source ran six SELECT * scans here, one per status.
        ->and($countQueries)->toBe(1);
});

it('excludes soft-deleted orders from the queue badges', function () {
    $user = navAdmin();
    grant($user, 'new-order');

    Order::factory()->status(Order::STATUS_NEW)->create();
    Order::factory()->status(Order::STATUS_NEW)->create()->delete();

    $items = collect(app(AdminNavigation::class)->for($user))->firstWhere('type', 'group')['items'];

    expect(collect($items)->firstWhere('slug', 'new-order')['count'])->toBe(1);
});

it('gives database-order no badge', function () {
    $user = navAdmin();
    grant($user, 'database-order');

    $items = collect(app(AdminNavigation::class)->for($user))->firstWhere('type', 'group')['items'];

    expect($items[0]['slug'])->toBe('database-order')
        ->and($items[0])->not->toHaveKey('count');
});

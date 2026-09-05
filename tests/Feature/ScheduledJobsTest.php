<?php

use App\Jobs\ExpireAbandonedCarts;
use App\Jobs\RefreshVisitorCounts;
use App\Jobs\SyncDeliveryStatus;
use App\Models\Cart;
use App\Models\JtSetting;
use App\Models\MemberHq;
use App\Models\OnlineVisitor;
use App\Models\Order;
use App\Models\RoleAccess;
use App\Services\AdminNavigation;
use App\Services\PageAccess;
use App\Services\Shipping\JtTracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function cartRow(string $session, int $status, $updatedAt): Cart
{
    $cart = Cart::create([
        'session_id' => $session,
        'p_id' => 1,
        'pv_id' => 0,
        'quantity' => 1,
        'price' => 49.90,
        'weight' => 200,
        'total_weight' => 200,
        'currency_sign' => 'MYR',
        'country_id' => 1,
        'status' => $status,
    ]);

    // Written straight past the timestamps so the row can be aged.
    Cart::query()->whereKey($cart->id)->update(['updated_at' => $updatedAt]);

    return $cart->fresh();
}

// ---------------------------------------------------------------------------
// Abandoned baskets
// ---------------------------------------------------------------------------

it('releases baskets that have gone quiet and leaves fresh ones alone', function () {
    $stale = cartRow('stale-session', Cart::STATUS_UNPAID, now()->subMinutes(30));
    $fresh = cartRow('fresh-session', Cart::STATUS_UNPAID, now()->subMinutes(2));

    (new ExpireAbandonedCarts)->handle();

    expect((int) $stale->fresh()->status)->toBe(Cart::STATUS_REMOVED)
        ->and($stale->fresh()->deleted_at)->not->toBeNull()
        ->and((int) $fresh->fresh()->status)->toBe(Cart::STATUS_UNPAID)
        ->and($fresh->fresh()->deleted_at)->toBeNull();
});

it('never touches a basket that has already been paid for', function () {
    $paid = cartRow('paid-session', Cart::STATUS_PAID, now()->subDay());

    (new ExpireAbandonedCarts)->handle();

    expect((int) $paid->fresh()->status)->toBe(Cart::STATUS_PAID)
        ->and($paid->fresh()->deleted_at)->toBeNull();
});

it('honours the configured expiry window', function () {
    config()->set('shop.cart_abandon_minutes', 120);

    $cart = cartRow('waiting-session', Cart::STATUS_UNPAID, now()->subMinutes(30));

    (new ExpireAbandonedCarts)->handle();

    // Thirty minutes old is stale at the default ten, live at two hours.
    expect((int) $cart->fresh()->status)->toBe(Cart::STATUS_UNPAID);
});

// ---------------------------------------------------------------------------
// Visitor counts
// ---------------------------------------------------------------------------

it('counts who is on the site without writing a file to the web root', function () {
    OnlineVisitor::create(['ip_address' => '1.1.1.1', 'session_end_at' => now()->addMinutes(5)]);
    OnlineVisitor::create(['ip_address' => '2.2.2.2', 'session_end_at' => now()->addMinute()]);
    OnlineVisitor::create(['ip_address' => '3.3.3.3', 'session_end_at' => now()->subHour()]);

    (new RefreshVisitorCounts)->handle();

    $counts = RefreshVisitorCounts::counts();

    expect($counts['live'])->toBe(2)
        ->and($counts['all'])->toBe(3)
        ->and($counts['today'])->toBe(3)
        ->and($counts['updated_at'])->not->toBeNull();

    // The source published these as a 0666 file inside the document root.
    expect(file_exists(public_path('live_visitors.json')))->toBeFalse();
});

it('reports zeroes rather than failing before the first run', function () {
    expect(RefreshVisitorCounts::counts())
        ->toMatchArray(['live' => 0, 'all' => 0, 'today' => 0]);
});

// ---------------------------------------------------------------------------
// Delivery tracking
// ---------------------------------------------------------------------------

it('completes an order the courier says it delivered', function () {
    config()->set('shop.tracking.jt_key', 'tracking-key');

    Http::fake(['*' => Http::response([
        'responseitems' => ['data' => [['details' => [['scanstatus' => 'Delivered']]]]],
    ])]);

    $order = Order::factory()->withAwb('630000555444')->create(['status' => Order::STATUS_IN_DELIVERY]);

    app(SyncDeliveryStatus::class)->handle(app(JtTracking::class), app(AdminNavigation::class));

    $order->refresh();

    expect((int) $order->status)->toBe(Order::STATUS_COMPLETED)
        ->and($order->tracking_milestone)->toBe('Delivered');
});

it('records the milestone but leaves an in-transit order in delivery', function () {
    config()->set('shop.tracking.jt_key', 'tracking-key');

    Http::fake(['*' => Http::response([
        'responseitems' => ['data' => [['details' => [['scanstatus' => 'In Transit']]]]],
    ])]);

    $order = Order::factory()->withAwb('630000555445')->create(['status' => Order::STATUS_IN_DELIVERY]);

    app(SyncDeliveryStatus::class)->handle(app(JtTracking::class), app(AdminNavigation::class));

    $order->refresh();

    expect((int) $order->status)->toBe(Order::STATUS_IN_DELIVERY)
        ->and($order->tracking_milestone)->toBe('In Transit');
});

it('survives a tracking response in an unexpected shape', function () {
    config()->set('shop.tracking.jt_key', 'tracking-key');

    // The source indexed straight into responseitems.data.0.details.0 and
    // fatal'd the whole cron run when the shape differed.
    Http::fake(['*' => Http::response(['responseitems' => []])]);

    $order = Order::factory()->withAwb('630000555446')->create([
        'status' => Order::STATUS_IN_DELIVERY,
        'tracking_milestone' => '',
    ]);

    app(SyncDeliveryStatus::class)->handle(app(JtTracking::class), app(AdminNavigation::class));

    expect((int) $order->fresh()->status)->toBe(Order::STATUS_IN_DELIVERY);
});

it('does not call the courier at all when tracking is unconfigured', function () {
    config()->set('shop.tracking.jt_key', null);
    JtSetting::query()->delete();

    Http::fake();

    Order::factory()->withAwb('630000555447')->create(['status' => Order::STATUS_IN_DELIVERY]);

    app(SyncDeliveryStatus::class)->handle(app(JtTracking::class), app(AdminNavigation::class));

    Http::assertNothingSent();
});

it('only polls orders that are actually out for delivery', function () {
    config()->set('shop.tracking.jt_key', 'tracking-key');

    Http::fake(['*' => Http::response([
        'responseitems' => ['data' => [['details' => [['scanstatus' => 'Delivered']]]]],
    ])]);

    Order::factory()->withAwb('630000555448')->create(['status' => Order::STATUS_IN_DELIVERY]);
    Order::factory()->withAwb('630000555449')->create(['status' => Order::STATUS_NEW]);
    Order::factory()->create(['status' => Order::STATUS_IN_DELIVERY, 'awb_number' => '']);

    app(SyncDeliveryStatus::class)->handle(app(JtTracking::class), app(AdminNavigation::class));

    Http::assertSentCount(1);
});

// ---------------------------------------------------------------------------
// The live feed that replaced the SSE loop
// ---------------------------------------------------------------------------

it('serves the dashboard live figures as plain JSON', function () {
    $admin = MemberHq::create([
        'email' => 'l'.uniqid().'@example.test',
        'password' => bcrypt('secret'),
        'sec_pin' => '1234',
        'f_name' => 'Live',
        'l_name' => 'Watcher',
        'phone' => '0100000000',
        'role' => MemberHq::ROLE_STAFF_ADMIN,
        'status' => MemberHq::STATUS_ACTIVE,
    ]);

    RoleAccess::create(['page_url' => 'dashboard', 'name' => 'dashboard', 'allowed_user' => '['.$admin->id.']', 'sort' => 0]);
    app(PageAccess::class)->flushFor($admin->id);

    OnlineVisitor::create(['ip_address' => '1.1.1.1', 'session_end_at' => now()->addMinutes(5)]);
    (new RefreshVisitorCounts)->handle();

    Order::factory()->create(['status' => Order::STATUS_NEW]);

    $this->actingAs($admin, 'admin')
        ->getJson('/admin/dashboard/live')
        ->assertOk()
        ->assertJsonPath('visitors.live', 1)
        ->assertJsonCount(1, 'orders')
        ->assertJsonStructure(['generated_at', 'visitors', 'today', 'queues', 'orders']);
});

it('keeps the live feed behind the dashboard grant', function () {
    $admin = MemberHq::create([
        'email' => 'n'.uniqid().'@example.test',
        'password' => bcrypt('secret'),
        'sec_pin' => '1234',
        'f_name' => 'No',
        'l_name' => 'Access',
        'phone' => '0100000000',
        'role' => MemberHq::ROLE_STAFF_LOGISTIC,
        'status' => MemberHq::STATUS_ACTIVE,
    ]);

    app(PageAccess::class)->flushFor($admin->id);

    $this->actingAs($admin, 'admin')->getJson('/admin/dashboard/live')->assertForbidden();
});

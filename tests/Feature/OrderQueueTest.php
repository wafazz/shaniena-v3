<?php

use App\Models\Cart;
use App\Models\MemberHq;
use App\Models\Order;
use App\Models\Product;
use App\Models\RoleAccess;
use App\Services\PageAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function queueAdmin(string ...$slugs): MemberHq
{
    $id = DB::table('member_hq')->insertGetId([
        'email' => 'q'.uniqid().'@example.test',
        'password' => bcrypt('secret'),
        'sec_pin' => '1234',
        'f_name' => 'Queue',
        'l_name' => 'Operator',
        'phone' => '0100000000',
        'role' => MemberHq::ROLE_STAFF_LOGISTIC,
        'status' => MemberHq::STATUS_ACTIVE,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = MemberHq::findOrFail($id);

    foreach ($slugs as $i => $slug) {
        RoleAccess::create([
            'page_url' => $slug,
            'name' => $slug,
            'allowed_user' => '['.$user->id.']',
            'sort' => $i,
        ]);
    }

    app(PageAccess::class)->flushFor($user->id);

    return $user;
}

/** An order with real cart lines, the way the source stores them. */
function orderWithLines(int $status, array $productNames, int $qty = 1): Order
{
    $order = Order::factory()->status($status)->create();

    foreach ($productNames as $name) {
        $product = Product::factory()->named($name)->create();

        Cart::create([
            'session_id' => $order->session_id,
            'p_id' => $product->id,
            'pv_id' => 0,
            'quantity' => $qty,
            'price' => 29.90,
            'weight' => 100,
            'total_weight' => 100 * $qty,
            'currency_sign' => 'MYR',
            'country_id' => 1,
            'status' => Cart::STATUS_PAID,
        ]);
    }

    return $order->fresh();
}

it('lists a working queue without needing a filter', function () {
    $admin = queueAdmin('new-order');
    Order::factory()->status(Order::STATUS_NEW)->count(3)->create();

    $this->actingAs($admin, 'admin')->get('/admin/new-order')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Orders/Queue')
            ->where('queue.working', true)
            ->has('orders.data', 3));
});

it('opens an archive empty until something is asked of it', function () {
    $admin = queueAdmin('completed-order');
    Order::factory()->status(Order::STATUS_COMPLETED)->count(3)->create();

    $this->actingAs($admin, 'admin')->get('/admin/completed-order')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('queue.working', false)->where('orders', null));

    $this->actingAs($admin, 'admin')->get('/admin/completed-order?qty=1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('orders.data'));
});

it('shows only the requested status', function () {
    $admin = queueAdmin('new-order');
    Order::factory()->status(Order::STATUS_NEW)->create();
    Order::factory()->status(Order::STATUS_PROCESSING)->count(4)->create();

    $this->actingAs($admin, 'admin')->get('/admin/new-order')
        ->assertInertia(fn ($page) => $page->has('orders.data', 1));
});

it('matches a product on any line, not only single-item orders', function () {
    $admin = queueAdmin('new-order');

    // The source's `HAVING COUNT(*) = 1` hid exactly this order.
    orderWithLines(Order::STATUS_NEW, ['Hydra Glow Cleanser', 'Aloe Vera Gel Moisturizer 100ml']);
    orderWithLines(Order::STATUS_NEW, ['Hydra Glow Cleanser']);
    orderWithLines(Order::STATUS_NEW, ['Vitamin C Brightening Serum 30ml']);

    $this->actingAs($admin, 'admin')->get('/admin/new-order?product=Hydra')
        ->assertInertia(fn ($page) => $page->has('orders.data', 2));
});

it('403s a queue the operator was not granted', function () {
    $admin = queueAdmin('new-order');

    $this->actingAs($admin, 'admin')->get('/admin/database-order')->assertForbidden();
});

it('advances an order one stage and moves its cart lines on cancel', function () {
    $admin = queueAdmin('new-order');
    $order = orderWithLines(Order::STATUS_NEW, ['Hydra Glow Cleanser']);

    $this->actingAs($admin, 'admin')
        ->post("/admin/orders/{$order->id}/status", ['to' => Order::STATUS_CANCELLED])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(Order::STATUS_CANCELLED)
        ->and(Cart::where('session_id', $order->session_id)->value('status'))->toBe(Cart::STATUS_CANCELLED);
});

it('refuses a transition the order is not eligible for', function () {
    $admin = queueAdmin('new-order');
    $order = Order::factory()->status(Order::STATUS_NEW)->create();

    // New -> Completed skips two stages; the source trusted this from the URL.
    $this->actingAs($admin, 'admin')
        ->post("/admin/orders/{$order->id}/status", ['to' => Order::STATUS_COMPLETED])
        ->assertSessionHasErrors('to');

    expect($order->fresh()->status)->toBe(Order::STATUS_NEW);
});

it('writes an activity row for every stage move', function () {
    $admin = queueAdmin('new-order');
    $order = Order::factory()->status(Order::STATUS_NEW)->create();

    $this->actingAs($admin, 'admin')->post("/admin/orders/{$order->id}/status", ['to' => Order::STATUS_PROCESSING]);

    expect(DB::table('activities')->where('user_id', $admin->id)->count())->toBe(1);
});

it('moves a bulk selection and reports the ones it skipped', function () {
    $admin = queueAdmin('new-order');
    $movable = Order::factory()->status(Order::STATUS_NEW)->count(2)->create();
    $blocked = Order::factory()->status(Order::STATUS_COMPLETED)->create();

    $this->actingAs($admin, 'admin')->post('/admin/orders/status', [
        'to' => Order::STATUS_PROCESSING,
        'orders' => [...$movable->pluck('id')->all(), $blocked->id],
    ])->assertSessionHas('success', fn ($m) => str_contains($m, '2 orders') && str_contains($m, '1 skipped'));

    expect(Order::where('status', Order::STATUS_PROCESSING)->count())->toBe(2)
        ->and($blocked->fresh()->status)->toBe(Order::STATUS_COMPLETED);
});

it('loads order lines without a query per row', function () {
    $admin = queueAdmin('new-order');
    foreach (range(1, 5) as $i) {
        orderWithLines(Order::STATUS_NEW, ["Product {$i}A", "Product {$i}B"]);
    }

    DB::enableQueryLog();
    $this->actingAs($admin, 'admin')->get('/admin/new-order')->assertOk();
    $queries = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'cart') || str_contains($q['query'], 'products'))
        ->count();
    DB::disableQueryLog();

    // Eager loading: one query for lines, one for products, one for variants.
    // The source ran four raw queries per variant per order.
    expect($queries)->toBeLessThanOrEqual(4);
});

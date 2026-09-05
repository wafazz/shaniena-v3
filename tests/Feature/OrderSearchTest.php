<?php

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rests without querying until asked', function () {
    $admin = queueAdmin('search-order');
    Order::factory()->count(3)->create();

    $this->actingAs($admin, 'admin')->get('/admin/search-order')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Orders/Search')->where('results', null));
});

it('finds an order by its exact id', function () {
    $admin = queueAdmin('search-order');
    $order = Order::factory()->create();
    Order::factory()->count(3)->create();

    $this->actingAs($admin, 'admin')->get("/admin/search-order?search={$order->id}")
        ->assertInertia(fn ($page) => $page->has('results.data', 1)
            ->where('results.data.0.reference', $order->reference()));
});

it('finds an order by customer name, phone or email', function () {
    $admin = queueAdmin('search-order');
    $order = Order::factory()->create([
        'customer_name' => 'Aisyah',
        'customer_name_last' => 'Binti Rahman',
        'customer_phone' => '+60123456789',
        'customer_email' => 'aisyah@example.test',
    ]);
    Order::factory()->count(2)->create(['customer_name' => 'Someone', 'customer_email' => 'x@y.test']);

    foreach (['Aisyah', 'Binti', '456789', 'aisyah@example'] as $term) {
        $this->actingAs($admin, 'admin')->get('/admin/search-order?search='.urlencode($term))
            ->assertInertia(fn ($page) => $page->has('results.data', 1)
                ->where('results.data.0.reference', $order->reference()));
    }
});

it('searches every status, including ones no queue shows', function () {
    $admin = queueAdmin('search-order');
    Order::factory()->status(Order::STATUS_AWAITING_PAYMENT)->create(['customer_name' => 'Farah']);

    $this->actingAs($admin, 'admin')->get('/admin/search-order?search=Farah')
        ->assertInertia(fn ($page) => $page->has('results.data', 1)
            ->where('results.data.0.status', Order::STATUS_AWAITING_PAYMENT));
});

it('403s an operator without the search slug', function () {
    $this->actingAs(queueAdmin('new-order'), 'admin')->get('/admin/search-order')->assertForbidden();
});

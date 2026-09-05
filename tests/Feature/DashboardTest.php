<?php

use App\Models\Order;
use App\Services\DashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::forget(DashboardMetrics::CACHE_KEY));

it('renders with metrics, latest orders and activity', function () {
    $admin = queueAdmin('dashboard');
    Order::factory()->status(Order::STATUS_NEW)->count(3)->create();

    $this->actingAs($admin, 'admin')->get('/admin/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->has('metrics.generated_at')
            ->has('latestOrders', 3)
            ->has('activity'));
});

it('excludes cancelled and failed orders from revenue', function () {
    Order::factory()->status(Order::STATUS_COMPLETED)->create(['myr_value_include_postage' => 100]);
    Order::factory()->status(Order::STATUS_CANCELLED)->create(['myr_value_include_postage' => 500]);
    Order::factory()->status(Order::STATUS_AWAITING_PAYMENT)->create(['myr_value_include_postage' => 900]);

    expect(app(DashboardMetrics::class)->all()['all_time']['sales'])->toBe(100.0);
});

it('carries the time its figures were computed', function () {
    expect(app(DashboardMetrics::class)->all())->toHaveKey('generated_at');
});

it('zero-fills the trend so quiet days are visible as gaps', function () {
    Order::factory()->status(Order::STATUS_COMPLETED)->create([
        'myr_value_include_postage' => 250,
        'created_at' => now(),
    ]);

    $trend = app(DashboardMetrics::class)->all()['trend'];

    expect($trend)->toHaveCount(14)
        ->and(end($trend)['sales'])->toBe(250.0)
        ->and($trend[0]['sales'])->toBe(0.0);
});

it('counts each queue for the waiting-on-you panel', function () {
    Order::factory()->status(Order::STATUS_NEW)->count(2)->create();
    Order::factory()->status(Order::STATUS_PROCESSING)->create();

    $queues = app(DashboardMetrics::class)->all()['queues'];

    expect($queues[Order::STATUS_NEW])->toBe(2)
        ->and($queues[Order::STATUS_PROCESSING])->toBe(1);
});

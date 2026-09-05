<?php

use App\Models\Activity;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function csvFrom($response): array
{
    $body = $response->streamedContent();

    // Strip the BOM before parsing, then split into rows.
    $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);

    return array_map('str_getcsv', array_filter(explode("\n", trim($body))));
}

it('exports one row per order with a header', function () {
    $admin = adminWith(['search-order']);

    Order::factory()->create(['customer_name' => 'Aina', 'customer_name_last' => 'Rahim', 'status' => Order::STATUS_NEW]);
    Order::factory()->create(['customer_name' => 'Farid', 'customer_name_last' => 'Osman', 'status' => Order::STATUS_COMPLETED]);

    $rows = csvFrom($this->actingAs($admin, 'admin')->get('/admin/orders/export')->assertOk());

    expect($rows[0][0])->toBe('Order')
        ->and($rows)->toHaveCount(3);
});

it('exports one row per item when asked', function () {
    $admin = adminWith(['search-order']);
    $order = Order::factory()->create();

    foreach (['Cleanser', 'Toner', 'Serum'] as $name) {
        $product = Product::factory()->named($name)->create();
        Cart::create([
            'session_id' => $order->session_id, 'p_id' => $product->id, 'pv_id' => 0,
            'quantity' => 1, 'price' => 39.00, 'weight' => 100, 'total_weight' => 100,
            'currency_sign' => 'MYR', 'country_id' => 1, 'status' => Cart::STATUS_PAID,
        ]);
    }

    $rows = csvFrom($this->actingAs($admin, 'admin')->get('/admin/orders/export?rows=items')->assertOk());

    // Header plus one line per item, not per order.
    expect($rows)->toHaveCount(4)
        ->and(array_column(array_slice($rows, 1), 4))->toContain('Cleanser', 'Toner', 'Serum');
});

it('limits the export to one status', function () {
    $admin = adminWith(['search-order']);

    Order::factory()->count(3)->create(['status' => Order::STATUS_NEW]);
    Order::factory()->count(2)->create(['status' => Order::STATUS_COMPLETED]);

    $rows = csvFrom($this->actingAs($admin, 'admin')->get('/admin/orders/export?queue=new-order')->assertOk());

    expect($rows)->toHaveCount(4);
});

it('limits the export to a date range', function () {
    $admin = adminWith(['search-order']);

    $old = Order::factory()->create();
    Order::query()->whereKey($old->id)->update(['created_at' => now()->subMonths(3)]);
    Order::factory()->count(2)->create();

    $rows = csvFrom($this->actingAs($admin, 'admin')
        ->get('/admin/orders/export?from='.now()->subWeek()->toDateString())
        ->assertOk());

    expect($rows)->toHaveCount(3);
});

it('refuses a range that ends before it starts', function () {
    $admin = adminWith(['search-order']);

    $this->actingAs($admin, 'admin')
        ->get('/admin/orders/export?from=2026-03-01&to=2026-01-01')
        ->assertSessionHasErrors('to');
});

it('keeps identifiers out of the spreadsheet number formatter', function () {
    $admin = adminWith(['search-order']);

    Order::factory()->withAwb('0630000123456')->create(['postcode' => '05100', 'customer_phone' => '0123456789']);

    $rows = csvFrom($this->actingAs($admin, 'admin')->get('/admin/orders/export')->assertOk());
    $row = $rows[1];

    // A bare 05100 becomes 5100 and a long AWB becomes 6.3E+11 in Excel.
    expect($row[8])->toBe("\t05100")
        ->and($row[5])->toBe("\t0123456789")
        ->and($row[19])->toBe("\t0630000123456");
});

it('records who took the export and what was in it', function () {
    $admin = adminWith(['search-order']);
    Order::factory()->create(['status' => Order::STATUS_NEW]);

    $this->actingAs($admin, 'admin')
        ->get('/admin/orders/export?queue=new-order&from=2026-01-01')
        ->assertOk()
        ->streamedContent();

    $entry = Activity::query()->where('activities', 'order_activity')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->user_id)->toBe($admin->id)
        ->and($entry->description)->toContain(Order::STATUSES[Order::STATUS_NEW])
        ->and($entry->description)->toContain('2026-01-01');
});

it('keeps the export behind the order-search grant', function () {
    $admin = adminWith(['stock-control']);

    $this->actingAs($admin, 'admin')->get('/admin/orders/export')->assertForbidden();

    expect(Activity::query()->count())->toBe(0);
});

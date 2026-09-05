<?php

use App\Jobs\ExpireAbandonedCarts;
use App\Models\Cart;
use App\Models\CodCharge;
use App\Models\Order;
use App\Models\PostageCost;
use App\Models\StateSetting;
use App\Services\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(fn () => app(StoreSettings::class)->set('cod_enabled', '1'));

/**
 * A COD order is live the moment it is placed — no callback is coming to
 * retire its basket. The abandoned-cart sweep used to soft-delete those rows
 * ten minutes later, and since `cart` IS where an order's line items live, the
 * order kept its totals and lost every product on it.
 */
function placeCod(TestCase $t): Order
{
    $country = shopCountry();
    StateSetting::create(['country_id' => $country->id, 'state_code' => 'SGR', 'name' => 'Selangor', 'shipping_zone' => 1]);
    PostageCost::create(['country_id' => $country->id, 'shipping_zone' => 1, 'currency' => 'MYR',
        'first_kilo' => 6.50, 'next_kilo' => 3.00]);
    CodCharge::create(['country_id' => $country->id, 'shipping_zone' => '1',
        'benchmark_amount' => 100, 'cod_fee_below' => 10, 'cod_fee_above' => 8]);

    [$product, $variant] = sellable();

    $t->keep($t->post('/cart', [
        'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2,
    ]));

    $t->keep($t->post('/checkout/address', [
        'first_name' => 'Nurul', 'last_name' => 'Aisyah', 'address_1' => 'No 22, Jalan Setia',
        'city' => 'Shah Alam', 'state' => 'Selangor', 'postcode' => '40170',
        'phone' => '+60123456789', 'email' => 'cod@example.test',
        'courier_service' => 'J&T Express',
    ]));

    $t->keep($t->post('/pay/cod'));

    return Order::latest('id')->firstOrFail();
}

it('keeps a COD order its line items after the abandoned-cart sweep', function () {
    $order = placeCod($this);

    expect((int) $order->status)->toBe(Order::STATUS_NEW)
        ->and($order->lines()->count())->toBe(1);

    // Age the basket well past the expiry window, then run the sweep.
    Cart::query()->where('session_id', $order->session_id)->update(['updated_at' => now()->subHour()]);

    (new ExpireAbandonedCarts)->handle();

    // The lines have to survive: they are the order. Losing them left the
    // queue showing no products, the AWB printing empty at minimum weight,
    // and the courier quoted for the wrong parcel.
    expect($order->fresh()->lines()->count())->toBe(1)
        ->and((int) $order->fresh()->lines()->sum('total_weight'))->toBeGreaterThan(0);
});

it('marks a COD basket paid so it counts toward best sellers', function () {
    $order = placeCod($this);

    // Best sellers rank on paid cart quantity, so COD never counted before.
    expect((int) Cart::where('session_id', $order->session_id)->value('status'))
        ->toBe(Cart::STATUS_PAID);
});

it('still expires a basket nobody checked out with', function () {
    [$product, $variant] = sellable();

    $this->keep($this->post('/cart', [
        'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1,
    ]));

    Cart::query()->update(['updated_at' => now()->subHour()]);

    (new ExpireAbandonedCarts)->handle();

    expect((int) Cart::withTrashed()->first()->status)->toBe(Cart::STATUS_REMOVED);
});

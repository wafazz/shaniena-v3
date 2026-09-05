<?php

use App\Models\Activity;
use App\Models\Cart;
use App\Models\MemberHq;
use App\Models\Order;
use App\Models\PostcodeMy;
use App\Models\Product;
use App\Models\RoleAccess;
use App\Models\StateMy;
use App\Services\PageAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function detailAdmin(string ...$slugs): MemberHq
{
    $user = MemberHq::create([
        'email' => 'd'.uniqid().'@example.test',
        'password' => bcrypt('secret'),
        'sec_pin' => '1234',
        'f_name' => 'Detail',
        'l_name' => 'Operator',
        'phone' => '0100000000',
        'role' => MemberHq::ROLE_STAFF_ADMIN,
        'status' => MemberHq::STATUS_ACTIVE,
    ]);

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

it('returns one order in full', function () {
    $admin = detailAdmin('new-order');

    $order = Order::factory()->create([
        'customer_name' => 'Siti',
        'customer_name_last' => 'Zaleha',
        'courier_service' => 'J&T Express',
        'remark_comment' => 'Customer asked for a gift note.',
    ]);

    $product = Product::factory()->named('Night Repair Oil')->create();

    Cart::create([
        'session_id' => $order->session_id, 'p_id' => $product->id, 'pv_id' => 0,
        'quantity' => 2, 'price' => 79.00, 'weight' => 150, 'total_weight' => 300,
        'currency_sign' => 'MYR', 'country_id' => 1, 'status' => Cart::STATUS_PAID,
    ]);

    $this->actingAs($admin, 'admin')
        ->getJson("/admin/orders/{$order->id}/detail")
        ->assertOk()
        ->assertJsonPath('reference', $order->reference())
        ->assertJsonPath('customer.first_name', 'Siti')
        ->assertJsonPath('lines.0.name', 'Night Repair Oil')
        ->assertJsonPath('lines.0.quantity', 2)
        ->assertJsonPath('lines.0.line_total', 158)
        ->assertJsonPath('remark', 'Customer asked for a gift note.')
        ->assertJsonPath('address_is_with_courier', false);
});

it('never sends the order hash to the browser', function () {
    $admin = detailAdmin('new-order');
    $order = Order::factory()->create();

    $order->detail()->create([
        'hash_code' => bin2hex(random_bytes(32)),
        'created_at' => now(),
    ]);

    // That hash is what authenticates a tracking link; it has no business in
    // an admin payload.
    $this->actingAs($admin, 'admin')
        ->getJson("/admin/orders/{$order->id}/detail")
        ->assertOk()
        ->assertJsonMissing(['hash_code' => $order->detail()->value('hash_code')]);
});

it('lets staff correct a delivery address', function () {
    $admin = detailAdmin('new-order');
    $order = Order::factory()->create(['city' => 'Klang', 'postcode' => '41000', 'awb_number' => '']);

    $this->actingAs($admin, 'admin')
        ->patch("/admin/orders/{$order->id}/detail", [
            'customer_name' => 'Siti',
            'customer_name_last' => 'Zaleha',
            'customer_phone' => '0123456789',
            'customer_email' => 'siti@example.test',
            'address_1' => 'No 8, Jalan Bukit',
            'address_2' => '',
            'city' => 'Shah Alam',
            'state' => 'Selangor',
            'postcode' => '40000',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $order->refresh();

    expect($order->city)->toBe('Shah Alam')
        ->and($order->postcode)->toBe('40000')
        ->and($order->address_1)->toBe('No 8, Jalan Bukit');

    expect(Activity::query()->where('activities', 'order_activity')->value('description'))
        ->toContain('city')
        ->and(Activity::query()->value('description'))->toContain('postcode');
});

it('warns when the address is edited after the courier has it', function () {
    $admin = detailAdmin('new-order');
    $order = Order::factory()->withAwb('630000888777')->create(['city' => 'Klang']);

    $this->actingAs($admin, 'admin')
        ->patch("/admin/orders/{$order->id}/detail", [
            'customer_name' => $order->customer_name,
            'customer_name_last' => $order->customer_name_last,
            'customer_phone' => $order->customer_phone,
            'customer_email' => $order->customer_email,
            'address_1' => $order->address_1,
            'address_2' => '',
            'city' => 'Shah Alam',
            'state' => $order->state,
            'postcode' => $order->postcode,
        ])
        ->assertRedirect()
        // Saved, but the operator has to know the parcel is already booked.
        ->assertSessionHas('warning')
        ->assertSessionMissing('success');

    expect($order->fresh()->city)->toBe('Shah Alam')
        ->and(Activity::query()->value('description'))->toContain('AFTER AWB 630000888777');
});

it('says nothing changed rather than logging a no-op edit', function () {
    $admin = detailAdmin('new-order');
    $order = Order::factory()->create(['address_2' => '', 'remark_comment' => '']);

    $this->actingAs($admin, 'admin')
        ->patch("/admin/orders/{$order->id}/detail", [
            'customer_name' => $order->customer_name,
            'customer_name_last' => $order->customer_name_last,
            'customer_phone' => $order->customer_phone,
            'customer_email' => $order->customer_email,
            'address_1' => $order->address_1,
            'address_2' => '',
            'city' => $order->city,
            'state' => $order->state,
            'postcode' => $order->postcode,
        ])
        ->assertSessionHas('success', 'Nothing to change.');

    expect(Activity::query()->count())->toBe(0);
});

it('rejects an address edit that would not deliver', function () {
    $admin = detailAdmin('new-order');
    $order = Order::factory()->create();

    $this->actingAs($admin, 'admin')
        ->patch("/admin/orders/{$order->id}/detail", [
            'customer_name' => '',
            'customer_phone' => '',
            'customer_email' => 'not-an-email',
            'address_1' => '',
            'city' => '',
            'state' => '',
            'postcode' => '',
        ])
        ->assertSessionHasErrors(['customer_name', 'customer_phone', 'customer_email', 'address_1', 'city', 'state', 'postcode']);
});

it('fills the town and state from a postcode', function () {
    $admin = detailAdmin('new-order');

    StateMy::create(['state_code' => 'SGR', 'state_name' => 'Selangor']);
    PostcodeMy::create([
        'postcode' => '40000',
        'area_name' => 'Seksyen 1',
        'post_office' => 'Shah Alam',
        'state_code' => 'SGR',
    ]);

    $this->actingAs($admin, 'admin')
        ->getJson('/admin/orders/postcode?postcode=40000')
        ->assertOk()
        ->assertJson(['city' => 'Shah Alam', 'state' => 'Selangor']);

    // A partial postcode is a keystroke, not a miss — it answers empty.
    $this->actingAs($admin, 'admin')
        ->getJson('/admin/orders/postcode?postcode=400')
        ->assertOk()
        ->assertJson(['city' => null, 'state' => null]);
});

it('keeps order detail behind the order grant', function () {
    $admin = detailAdmin('stock-control');
    $order = Order::factory()->create();

    $this->actingAs($admin, 'admin')->getJson("/admin/orders/{$order->id}/detail")->assertForbidden();
});

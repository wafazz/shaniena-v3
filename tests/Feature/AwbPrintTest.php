<?php

use App\Models\AwbPrint;
use App\Models\Order;
use App\Services\Shipping\AwbLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('streams a PDF for one booked order', function () {
    $admin = adminWith(['new-order']);
    $order = Order::factory()->withAwb('630000123456')->create([
        'courier_service' => 'J&T Express',
        'status' => Order::STATUS_PROCESSING,
    ]);

    $response = $this->actingAs($admin, 'admin')->get("/admin/orders/{$order->id}/awb");

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF-');
});

it('marks the order printed and logs who printed it', function () {
    $admin = adminWith(['new-order']);
    $order = Order::factory()->withAwb('630000123456')->create(['courier_service' => 'J&T Express']);

    expect((bool) $order->printed_awb)->toBeFalse();

    $this->actingAs($admin, 'admin')->get("/admin/orders/{$order->id}/awb")->assertOk();

    expect((bool) $order->fresh()->printed_awb)->toBeTrue();

    $print = AwbPrint::query()->forOrder($order->id)->first();

    expect($print)->not->toBeNull()
        ->and($print->printed_by)->toBe($admin->id);
});

it('refuses to print an order with no AWB yet', function () {
    $admin = adminWith(['new-order']);
    $order = Order::factory()->create(['awb_number' => '']);

    $this->actingAs($admin, 'admin')
        ->get("/admin/orders/{$order->id}/awb")
        ->assertStatus(422);

    // Nothing recorded for a label that was never produced — the source wrote
    // the audit row before rendering, so failed prints still counted.
    expect(AwbPrint::query()->count())->toBe(0)
        ->and((bool) $order->fresh()->printed_awb)->toBeFalse();
});

it('prints a batch and skips the ones with nothing to print', function () {
    $admin = adminWith(['new-order']);

    $ready = Order::factory()->withAwb('630000111111')->create(['courier_service' => 'J&T Express']);
    $alsoReady = Order::factory()->withAwb('630000222222')->create(['courier_service' => 'DHL eCommerce']);
    $notReady = Order::factory()->create(['awb_number' => '']);

    $response = $this->actingAs($admin, 'admin')->post('/admin/orders/awb', [
        'orders' => [$ready->id, $alsoReady->id, $notReady->id, $ready->id],
    ]);

    $response->assertOk();

    expect((bool) $ready->fresh()->printed_awb)->toBeTrue()
        ->and((bool) $alsoReady->fresh()->printed_awb)->toBeTrue()
        ->and((bool) $notReady->fresh()->printed_awb)->toBeFalse()
        // The duplicated id must not produce two audit rows or two labels.
        ->and(AwbPrint::query()->count())->toBe(2);
});

it('recognises a legacy print record for the same order', function () {
    $order = Order::factory()->withAwb('630000333333')->create();

    // The shape the source wrote: every id of one print run in one text column.
    AwbPrint::create(['order_id' => '['.$order->id.'],[9998],[9999]', 'printed_by' => 1]);

    expect(AwbPrint::query()->forOrder($order->id)->exists())->toBeTrue()
        ->and(AwbPrint::query()->forOrder(1234)->exists())->toBeFalse();
});

it('encodes the AWB in both the barcode and the QR', function () {
    $label = app(AwbLabel::class);

    expect($label->barcode('630000123456'))->toStartWith('data:image/png;base64,')
        ->and($label->qr('630000123456'))->toStartWith('data:image/png;base64,');
});

it('keeps AWBs away from staff without order access', function () {
    $admin = adminWith(['stock-control']);
    $order = Order::factory()->withAwb('630000444444')->create();

    $this->actingAs($admin, 'admin')
        ->get("/admin/orders/{$order->id}/awb")
        ->assertForbidden();

    expect((bool) $order->fresh()->printed_awb)->toBeFalse();
});

<?php

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockControl;
use App\Services\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function variantFor(Product $product, string $sku = 'HGC-100'): ProductVariant
{
    return ProductVariant::create([
        'product_id' => $product->id,
        'variant_name' => 'Default',
        'sku' => $sku,
        'price_retail' => 59.90,
        'price_sale' => 49.90,
        'max_purchase' => 5,
    ]);
}

it('lists products with a computed stock balance and sold count', function () {
    $admin = adminWith(['stock-control']);
    $product = Product::factory()->named('Hydra Glow Cleanser')->create();
    $variant = variantFor($product);

    StockControl::create(['p_id' => $product->id, 'pv_id' => $variant->id, 'stock_in' => 500, 'stock_out' => 0, 'comment' => 'Opening stock']);
    StockControl::create(['p_id' => $product->id, 'pv_id' => $variant->id, 'stock_in' => 0, 'stock_out' => 120, 'comment' => 'Sold']);

    Cart::create([
        'session_id' => 'sess-1', 'p_id' => $product->id, 'pv_id' => $variant->id, 'quantity' => 7,
        'price' => 49.90, 'weight' => 100, 'total_weight' => 700, 'currency_sign' => 'MYR',
        'country_id' => 1, 'status' => Cart::STATUS_PAID,
    ]);

    $this->actingAs($admin, 'admin')->get('/admin/stock-control')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Products/StockControl')
            ->where('products.data.0.variants.0.stock', 380)
            ->where('products.data.0.variants.0.sold', 7));
});

it('records an adjustment as an append-only ledger row', function () {
    $admin = adminWith(['stock-control', 'button-add-deduct-stock']);
    $product = Product::factory()->create();
    $variant = variantFor($product);

    $this->actingAs($admin, 'admin')
        ->post("/admin/stock-control/{$variant->id}/adjust", ['type' => 'add', 'quantity' => 40])
        ->assertRedirect();

    $this->actingAs($admin, 'admin')
        ->post("/admin/stock-control/{$variant->id}/adjust", ['type' => 'deduct', 'quantity' => 15]);

    expect(StockControl::where('pv_id', $variant->id)->count())->toBe(2)
        ->and((int) StockControl::where('pv_id', $variant->id)->sum('stock_in'))->toBe(40)
        ->and((int) StockControl::where('pv_id', $variant->id)->sum('stock_out'))->toBe(15);
});

it('refuses an adjustment from an operator without the button permission', function () {
    $admin = adminWith(['stock-control']);
    $variant = variantFor(Product::factory()->create());

    $this->actingAs($admin, 'admin')
        ->post("/admin/stock-control/{$variant->id}/adjust", ['type' => 'add', 'quantity' => 10])
        ->assertForbidden();

    expect(StockControl::count())->toBe(0);
});

it('tells the screen which buttons this operator may use', function () {
    $admin = adminWith(['stock-control']);

    $this->actingAs($admin, 'admin')->get('/admin/stock-control')
        ->assertInertia(fn ($page) => $page->where('can.adjustStock', false)->where('can.deleteProduct', false));
});

it('rejects a zero or negative adjustment', function () {
    $admin = adminWith(['stock-control', 'button-add-deduct-stock']);
    $variant = variantFor(Product::factory()->create());

    $this->actingAs($admin, 'admin')
        ->post("/admin/stock-control/{$variant->id}/adjust", ['type' => 'add', 'quantity' => 0])
        ->assertSessionHasErrors('quantity');
});

it('uses the configured low-stock threshold, defaulting to the source value', function () {
    $admin = adminWith(['stock-control']);

    $this->actingAs($admin, 'admin')->get('/admin/stock-control')
        ->assertInertia(fn ($page) => $page->where('lowStockThreshold', 101));

    app(StoreSettings::class)->set('low_stock_threshold', '25');

    $this->actingAs($admin, 'admin')->get('/admin/stock-control')
        ->assertInertia(fn ($page) => $page->where('lowStockThreshold', 25));
});

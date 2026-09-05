<?php

use App\Models\BayarcashSetting;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CodCharge;
use App\Models\ListCountry;
use App\Models\PostageCost;
use App\Models\Product;
use App\Models\SenangPaySetting;
use App\Models\StateSetting;
use App\Models\StripeSetting;
use App\Services\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- catalogue -----------------------------------------------------------

it('adds a category and refuses one that is its own parent', function () {
    $admin = queueAdmin('category-product');

    $this->actingAs($admin, 'admin')
        ->post('/admin/categories', ['name' => 'Skincare', 'slug' => 'skincare', 'parent_id' => '', 'sort_order' => 1])
        ->assertRedirect();

    $category = Category::where('slug', 'skincare')->firstOrFail();

    $this->actingAs($admin, 'admin')
        ->post("/admin/categories/{$category->id}", [
            'name' => 'Skincare', 'slug' => 'skincare', 'parent_id' => $category->id, 'sort_order' => 1,
        ])->assertSessionHasErrors('parent_id');
});

it('will not remove a category that still has products', function () {
    $admin = queueAdmin('category-product');
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id]);

    $this->actingAs($admin, 'admin')->delete("/admin/categories/{$category->id}")->assertStatus(422);

    expect(Category::whereKey($category->id)->exists())->toBeTrue();
});

it('removes a brand once nothing points at it', function () {
    $admin = queueAdmin('brand-product');
    $brand = Brand::factory()->create();

    $this->actingAs($admin, 'admin')->delete("/admin/brands/{$brand->id}")->assertRedirect();

    expect(Brand::whereKey($brand->id)->exists())->toBeFalse();
});

// --- store settings ------------------------------------------------------

it('saves only the keys the screen owns', function () {
    $admin = queueAdmin('store-setting');

    $this->actingAs($admin, 'admin')->put('/admin/store-setting', [
        'settings' => ['store_name' => 'Shaniena', 'cod_enabled' => '1', 'not_a_real_key' => 'nope'],
    ])->assertRedirect();

    $settings = app(StoreSettings::class);

    expect($settings->get('store_name'))->toBe('Shaniena')
        ->and($settings->enabled('cod_enabled'))->toBeTrue()
        ->and($settings->get('not_a_real_key'))->toBeNull();
});

// --- payment settings ----------------------------------------------------

it('never sends a payment secret to the browser', function () {
    $admin = queueAdmin('payment-setting');

    SenangPaySetting::create([
        'merchant_id' => 'M-1', 'secret_key' => 'super-secret-sandbox',
        'pro_merchant_id' => 'M-2', 'pro_secret_key' => 'super-secret-live',
        'type' => 'production',
    ]);
    StripeSetting::create(['id' => 1, 'publish_key' => 'pk_live_x', 'secret_key' => 'sk_live_realone', 'webhook_secret' => 'whsec_realone']);

    $response = $this->actingAs($admin, 'admin')->get('/admin/payment-setting')->assertOk();
    $body = $response->getContent();

    foreach (['super-secret-sandbox', 'super-secret-live', 'sk_live_realone', 'whsec_realone'] as $secret) {
        expect($body)->not->toContain($secret);
    }

    // The masked tail is enough to tell one key from another. Checked on the
    // Inertia props, since the HTML payload escapes the bullet characters.
    $response->assertInertia(fn ($page) => $page
        ->where('senangpay.pro_secret_key', '••••••••live')
        ->where('stripe.secret_key', '••••••••lone'));
});

it('keeps the stored secret when the field is left blank', function () {
    $admin = queueAdmin('payment-setting');
    SenangPaySetting::create([
        'merchant_id' => 'M-1', 'secret_key' => 'keep-me',
        'pro_merchant_id' => 'M-2', 'pro_secret_key' => 'keep-me-too', 'type' => 'sandbox',
    ]);

    $this->actingAs($admin, 'admin')->put('/admin/payment-setting/senangpay', [
        'type' => 'production', 'merchant_id' => 'M-1', 'pro_merchant_id' => 'M-2',
        'secret_key' => '', 'pro_secret_key' => '',
    ])->assertRedirect();

    $row = SenangPaySetting::current();

    expect($row->type)->toBe('production')
        ->and($row->secret_key)->toBe('keep-me')
        ->and($row->pro_secret_key)->toBe('keep-me-too');
});

it('replaces a secret when one is actually supplied', function () {
    $admin = queueAdmin('payment-setting');
    BayarcashSetting::create(['type' => 'sandbox', 'sandbox_api_token' => 'old-token']);

    $this->actingAs($admin, 'admin')->put('/admin/payment-setting/bayarcash', [
        'type' => 'sandbox', 'sandbox_api_token' => 'new-token',
    ])->assertRedirect();

    expect(BayarcashSetting::current()->sandbox_api_token)->toBe('new-token');
});

// --- shipping ------------------------------------------------------------

it('upserts postage and COD on country plus zone', function () {
    $admin = queueAdmin('delivery-charge');
    $country = ListCountry::create(['name' => 'Malaysia', 'sign' => 'MYR', 'rate' => 1, 'phone_code' => '+60', 'status' => 1]);

    foreach ([6.50, 7.50] as $rate) {
        $this->actingAs($admin, 'admin')->post('/admin/delivery-charge/postage', [
            'country_id' => $country->id, 'shipping_zone' => 1,
            'currency' => 'MYR', 'first_kilo' => $rate, 'next_kilo' => 3.00,
        ])->assertRedirect();
    }

    $this->actingAs($admin, 'admin')->post('/admin/delivery-charge/cod', [
        'country_id' => $country->id, 'shipping_zone' => '1',
        'benchmark_amount' => 100, 'cod_fee_below' => 10, 'cod_fee_above' => 8,
    ]);

    expect(PostageCost::count())->toBe(1)
        ->and((float) PostageCost::first()->first_kilo)->toBe(7.50)
        ->and(CodCharge::count())->toBe(1);
});

// --- countries & states --------------------------------------------------

it('adds a country switched off so postage can be set first', function () {
    $admin = queueAdmin('list-country');

    $this->actingAs($admin, 'admin')->post('/admin/countries', [
        'name' => 'Singapore', 'sign' => 'SGD', 'rate' => 0.30, 'phone_code' => '+65',
    ])->assertRedirect();

    expect(ListCountry::where('name', 'Singapore')->value('status'))->toBe(ListCountry::STATUS_INACTIVE);
});

it('requires a rate, because it is money on every order', function () {
    $admin = queueAdmin('list-country');

    $this->actingAs($admin, 'admin')->post('/admin/countries', [
        'name' => 'Brunei', 'sign' => 'BND', 'rate' => '', 'phone_code' => '+673',
    ])->assertSessionHasErrors('rate');
});

it('saves a state with an explicit shipping zone', function () {
    $admin = queueAdmin('list-country');
    $country = ListCountry::create(['name' => 'Malaysia', 'sign' => 'MYR', 'rate' => 1, 'phone_code' => '+60', 'status' => 1]);

    $this->actingAs($admin, 'admin')->post("/admin/countries/{$country->id}/states", [
        'state_code' => 'SBH', 'name' => 'Sabah', 'shipping_zone' => StateSetting::ZONE_EAST,
    ])->assertRedirect();

    expect(StateSetting::where('state_code', 'SBH')->value('shipping_zone'))->toBe(StateSetting::ZONE_EAST);
});

it('rejects a shipping zone outside the two that exist', function () {
    $admin = queueAdmin('list-country');
    $country = ListCountry::create(['name' => 'Malaysia', 'sign' => 'MYR', 'rate' => 1, 'phone_code' => '+60', 'status' => 1]);

    $this->actingAs($admin, 'admin')->post("/admin/countries/{$country->id}/states", [
        'state_code' => 'XXX', 'name' => 'Nowhere', 'shipping_zone' => 9,
    ])->assertSessionHasErrors('shipping_zone');
});

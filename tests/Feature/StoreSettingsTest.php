<?php

use App\Models\StoreSetting;
use App\Services\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::forget(StoreSettings::CACHE_KEY);
});

it('reads settings as a key/value map', function () {
    StoreSetting::create(['setting_key' => 'store_name', 'setting_value' => 'Shaniena', 'updated_at' => now()]);

    expect(app(StoreSettings::class)->get('store_name'))->toBe('Shaniena');
});

it('returns the default for a missing key', function () {
    expect(app(StoreSettings::class)->get('missing', 'fallback'))->toBe('fallback');
});

it('treats only the string 1 as enabled', function () {
    StoreSetting::create(['setting_key' => 'cod_enabled', 'setting_value' => '1', 'updated_at' => now()]);
    StoreSetting::create(['setting_key' => 'stripe_enabled', 'setting_value' => '0', 'updated_at' => now()]);

    $settings = app(StoreSettings::class);

    expect($settings->enabled('cod_enabled'))->toBeTrue()
        ->and($settings->enabled('stripe_enabled'))->toBeFalse()
        ->and($settings->enabled('never_set'))->toBeFalse();
});

it('serves repeat reads from cache without re-querying', function () {
    StoreSetting::create(['setting_key' => 'store_name', 'setting_value' => 'Shaniena', 'updated_at' => now()]);

    $settings = app(StoreSettings::class);
    $settings->all();

    // Change the row behind the cache; the cached copy must still be served.
    DB::table('store_settings')->where('setting_key', 'store_name')->update(['setting_value' => 'Changed']);

    expect($settings->get('store_name'))->toBe('Shaniena');
});

it('invalidates the cache when a setting is written', function () {
    StoreSetting::create(['setting_key' => 'store_name', 'setting_value' => 'Shaniena', 'updated_at' => now()]);

    $settings = app(StoreSettings::class);
    $settings->all();

    $settings->set('store_name', 'Shaniena V3');

    expect($settings->get('store_name'))->toBe('Shaniena V3')
        ->and(app(StoreSettings::class)->get('store_name'))->toBe('Shaniena V3');
});

it('creates a row for a key that does not exist yet', function () {
    app(StoreSettings::class)->set('new_key', 'value');

    expect(StoreSetting::where('setting_key', 'new_key')->value('setting_value'))->toBe('value');
});

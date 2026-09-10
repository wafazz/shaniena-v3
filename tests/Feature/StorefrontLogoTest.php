<?php

use App\Models\ImageSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * The logo HQ uploads in Settings -> Logo Setting, as the storefront sees it.
 *
 * The upload screen was built long before anything on the shop read from it:
 * the only consumer was the AWB label PDF, so uploading a logo changed the
 * paperwork and left the header showing the store name as text.
 */
function uploadLogo(string $name = 'logo.png'): ImageSetting
{
    Storage::fake('public');

    $admin = adminWith(['logo-setting']);

    test()->actingAs($admin, 'admin')
        ->post('/admin/logo-setting', ['image' => UploadedFile::fake()->image($name)])
        ->assertSessionHasNoErrors();

    return ImageSetting::logos()->latest('id')->firstOrFail();
}

it('sends no logo to the storefront until one is uploaded', function () {
    sellingCountry();

    // The themes fall back to the store name as text, which is what they
    // rendered before there was a logo at all.
    $this->get('/')->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->where('shop.footer.logo', null),
    );
});

it('sends the active logo to every storefront page', function () {
    sellingCountry();
    $logo = uploadLogo();

    $this->get('/')->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->where(
            'shop.footer.logo',
            Storage::disk('public')->url($logo->image_path),
        ),
    );
});

it('shows the new logo as soon as HQ switches it, not when the cache expires', function () {
    sellingCountry();

    uploadLogo('first.png');
    $this->get('/')->assertOk();

    // Warm cache from the request above. Without the flush in
    // LogoSettingController the shop keeps serving the old logo for ten
    // minutes, which reads as an upload that silently did nothing.
    $second = uploadLogo('second.png');

    $admin = adminWith(['logo-setting']);
    $this->actingAs($admin, 'admin')
        ->post("/admin/logo-setting/{$second->id}/default")
        ->assertSessionHasNoErrors();

    $this->get('/')->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->where(
            'shop.footer.logo',
            Storage::disk('public')->url($second->image_path),
        ),
    );
});

it('caches the lookup instead of querying it on every page', function () {
    sellingCountry();
    uploadLogo();

    $this->get('/')->assertOk();

    // The value is read on every storefront page; the query behind it should
    // run once per window, not once per view.
    expect(cache()->has(ImageSetting::LOGO_CACHE_KEY))->toBeTrue();
});

// --- the console -------------------------------------------------------------

it('brands the admin sign-in screen, which nobody is signed in to yet', function () {
    $logo = uploadLogo();
    auth('admin')->logout();

    // The shared `store` prop has to reach a guest request, or the login
    // screen is the one console page that stays anonymous.
    $this->get('/admin/login')->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->where(
            'store.logo',
            Storage::disk('public')->url($logo->image_path),
        ),
    );
});

it('brands the console sidebar once someone is signed in', function () {
    $logo = uploadLogo();
    $admin = adminWith(['dashboard']);

    $this->actingAs($admin, 'admin')->get('/admin/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where(
            'store.logo',
            Storage::disk('public')->url($logo->image_path),
        ),
    );
});

it('leaves the console on the store name when no logo is uploaded', function () {
    $admin = adminWith(['dashboard']);

    $this->actingAs($admin, 'admin')->get('/admin/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page->where('store.logo', null),
    );
});

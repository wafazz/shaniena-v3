<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\HandleStorefrontRequests;
use App\Models\ImageSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class LogoSettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Logo', [
            'logos' => ImageSetting::query()->logos()->latest('id')->get()->map(fn (ImageSetting $image) => [
                'id' => $image->id,
                'url' => Storage::disk('public')->url($image->image_path),
                'is_default' => $image->isDefault(),
                'uploaded_at' => $image->created_at?->format('j M Y, h:iA'),
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // No SVG. It is XML, it can carry a <script>, and the logo is served
        // from the storefront's own origin — that is stored XSS. Laravel's
        // `image` rule already rejects it without `allow_svg`, so listing svg
        // in `mimes` only made the failure message confusing.
        $request->validate(['image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048']]);

        $logo = ImageSetting::create([
            'use_type' => ImageSetting::TYPE_LOGO,
            'image_path' => $request->file('image')->store('logos', 'public'),
            'sorting' => 0,
        ]);

        // First logo uploaded becomes the active one — otherwise the header
        // would render nothing until someone remembered to pick it.
        if (ImageSetting::logos()->count() === 1) {
            $logo->makeDefault();
            $this->flushStorefront();
        }

        return back()->with('success', 'Logo uploaded.');
    }

    public function makeDefault(ImageSetting $logo): RedirectResponse
    {
        $logo->makeDefault();
        $this->flushStorefront();

        return back()->with('success', 'Logo updated across the storefront.');
    }

    public function destroy(ImageSetting $logo): RedirectResponse
    {
        abort_if($logo->isDefault(), 422, 'Pick another logo as the active one first.');

        Storage::disk('public')->delete($logo->image_path);
        $logo->delete();

        return back()->with('success', 'Logo removed.');
    }

    /**
     * The storefront caches the active logo for ten minutes. Without this the
     * success message above ("updated across the storefront") stays untrue for
     * most of that window, which reads as a broken upload.
     *
     * Only the two paths that change which logo is active need it: deleting a
     * non-default logo leaves the storefront alone, and destroy() already
     * refuses to delete the active one.
     */
    private function flushStorefront(): void
    {
        Cache::forget(HandleStorefrontRequests::LOGO_CACHE_KEY);
    }
}

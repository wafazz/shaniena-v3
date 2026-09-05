<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImageSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $request->validate(['image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048']]);

        $logo = ImageSetting::create([
            'use_type' => ImageSetting::TYPE_LOGO,
            'image_path' => $request->file('image')->store('logos', 'public'),
            'sorting' => 0,
        ]);

        // First logo uploaded becomes the active one — otherwise the header
        // would render nothing until someone remembered to pick it.
        if (ImageSetting::logos()->count() === 1) {
            $logo->makeDefault();
        }

        return back()->with('success', 'Logo uploaded.');
    }

    public function makeDefault(ImageSetting $logo): RedirectResponse
    {
        $logo->makeDefault();

        return back()->with('success', 'Logo updated across the storefront.');
    }

    public function destroy(ImageSetting $logo): RedirectResponse
    {
        abort_if($logo->isDefault(), 422, 'Pick another logo as the active one first.');

        Storage::disk('public')->delete($logo->image_path);
        $logo->delete();

        return back()->with('success', 'Logo removed.');
    }
}

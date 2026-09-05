<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Catalogue/Brands', [
            'brands' => Brand::query()->orderBy('name')->get()->map(fn (Brand $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'image' => $b->image ? Storage::disk('public')->url($b->image) : null,
                'products' => $b->products()->count(),
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $brand = Brand::create($this->validated($request) + ['description' => '', 'image' => '']);
        $this->attachImage($request, $brand);

        Activity::record((int) $request->user('admin')->getKey(), "Created brand {$brand->name}", "brands|{$brand->id}", 'catalogue_activity');

        return back()->with('success', "{$brand->name} added.");
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $brand->update($this->validated($request, $brand));
        $this->attachImage($request, $brand);

        return back()->with('success', "{$brand->name} updated.");
    }

    public function destroy(Request $request, Brand $brand): RedirectResponse
    {
        abort_if($brand->products()->exists(), 422, 'Move or remove this brand\'s products first.');

        $name = $brand->name;
        $brand->delete();

        Activity::record((int) $request->user('admin')->getKey(), "Deleted brand {$name}", "brands|{$brand->id}", 'catalogue_activity');

        return back()->with('success', "{$name} removed.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Brand $brand = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('brands', 'slug')->ignore($brand?->id)],
        ]);
    }

    private function attachImage(Request $request, Brand $brand): void
    {
        $request->validate(['image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);

        if ($file = $request->file('image')) {
            if ($brand->image) {
                Storage::disk('public')->delete($brand->image);
            }

            $brand->update(['image' => $file->store('brands', 'public')]);
        }
    }
}

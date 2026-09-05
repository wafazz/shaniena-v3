<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()->orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('Admin/Catalogue/Categories', [
            'categories' => $categories->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'parent_id' => $c->parent_id,
                'parent' => $categories->firstWhere('id', $c->parent_id)?->name,
                'sort_order' => (int) $c->sort_order,
                'image' => $c->image ? Storage::disk('public')->url($c->image) : null,
                'products' => $c->products()->count(),
            ])->all(),
            'parents' => $categories->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = Category::create($this->validated($request) + ['description' => '', 'image' => '']);
        $this->attachImage($request, $category);

        Activity::record((int) $request->user('admin')->getKey(), "Created category {$category->name}", "categories|{$category->id}", 'catalogue_activity');

        return back()->with('success', "{$category->name} added.");
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));
        $this->attachImage($request, $category);

        return back()->with('success', "{$category->name} updated.");
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        // Soft delete: products still reference the category, and the source
        // never hard-deleted one either.
        abort_if($category->products()->exists(), 422, 'Move or remove this category\'s products first.');

        $name = $category->name;
        $category->delete();

        Activity::record((int) $request->user('admin')->getKey(), "Deleted category {$name}", "categories|{$category->id}", 'catalogue_activity');

        return back()->with('success', "{$name} removed.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($category?->id)],
            // A category cannot be its own parent, which the source allowed.
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id'), Rule::notIn([$category?->id])],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
    }

    private function attachImage(Request $request, Category $category): void
    {
        $request->validate(['image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);

        if ($file = $request->file('image')) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }

            $category->update(['image' => $file->store('categories', 'public')]);
        }
    }
}

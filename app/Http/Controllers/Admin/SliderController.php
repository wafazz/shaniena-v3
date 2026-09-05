<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SliderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Content/Sliders', [
            'sliders' => Slider::query()->ordered()->get()->map(fn (Slider $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'link_url' => $s->link_url,
                'image' => Storage::disk('public')->url($s->image),
                'sort_order' => (int) $s->sort_order,
                'status' => (bool) $s->status,
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'link_url' => ['nullable', 'url', 'max:500'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        Slider::create([
            'title' => $data['title'] ?? null,
            'link_url' => $data['link_url'] ?? null,
            'image' => $request->file('image')->store('sliders', 'public'),
            'sort_order' => (int) Slider::max('sort_order') + 1,
            'status' => true,
        ]);

        return back()->with('success', 'Slide added.');
    }

    public function update(Request $request, Slider $slider): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'link_url' => ['nullable', 'url', 'max:500'],
            'status' => ['required', 'boolean'],
        ]);

        $slider->update($data);

        return back()->with('success', 'Slide updated.');
    }

    /** Drag-reorder: the whole running order arrives at once. */
    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'exists:sliders,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['order'] as $position => $id) {
                Slider::whereKey($id)->update(['sort_order' => $position + 1, 'updated_at' => now()]);
            }
        });

        return back()->with('success', 'Slide order saved.');
    }

    public function destroy(Slider $slider): RedirectResponse
    {
        Storage::disk('public')->delete($slider->image);
        $slider->delete();

        return back()->with('success', 'Slide removed.');
    }
}

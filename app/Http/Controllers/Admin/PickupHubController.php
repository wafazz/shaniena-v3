<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PickupHub;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PickupHubController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Logistics/PickupHubs', [
            'hubs' => PickupHub::query()->withCount('staff')->orderBy('hub_name')->get()->map(fn (PickupHub $hub) => [
                'id' => $hub->id,
                'hub_code' => $hub->hub_code,
                'hub_name' => $hub->hub_name,
                'contact_person' => $hub->contact_person,
                'phone' => $hub->phone,
                'email' => $hub->email,
                'city' => $hub->city,
                'state' => $hub->state,
                'postcode' => $hub->postcode,
                'status' => $hub->status,
                'staff' => $hub->staff_count,
                'orders' => $hub->orders()->count(),
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hub = PickupHub::create($this->validated($request));

        return back()->with('success', "{$hub->hub_name} added.");
    }

    public function update(Request $request, PickupHub $hub): RedirectResponse
    {
        $hub->update($this->validated($request, $hub));

        return back()->with('success', "{$hub->hub_name} updated.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?PickupHub $hub = null): array
    {
        return $request->validate([
            'hub_code' => ['required', 'string', 'max:50', Rule::unique('pickup_hubs', 'hub_code')->ignore($hub?->id)],
            'hub_name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in([PickupHub::STATUS_ACTIVE, PickupHub::STATUS_INACTIVE])],
        ]);
    }
}

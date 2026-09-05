<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AllCountry;
use App\Models\ListCountry;
use App\Models\StateSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CountrySettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Countries', [
            'countries' => ListCountry::query()->orderBy('name')->get()->map(fn (ListCountry $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'sign' => $c->sign,
                'rate' => (float) $c->rate,
                'phone_code' => $c->phone_code,
                'status' => (int) $c->status,
                'states' => $c->states()->count(),
            ])->all(),
            // The world list, minus what is already being sold into.
            'available' => AllCountry::query()
                ->whereNotIn('name', ListCountry::query()->pluck('name'))
                ->orderBy('name')
                ->get(['id', 'name', 'phone_code']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('list_country', 'name')],
            'sign' => ['required', 'string', 'max:50'],
            // The MYR conversion rate fills customer_orders.to_myr_rate at
            // checkout, so it is money and must be entered, never defaulted.
            'rate' => ['required', 'numeric', 'min:0.0001'],
            'phone_code' => ['required', 'string', 'max:255'],
        ]);

        ListCountry::create($data + ['status' => ListCountry::STATUS_INACTIVE]);

        return back()->with('success', "{$data['name']} added. Set its postage and switch it on when ready.");
    }

    public function update(Request $request, ListCountry $country): RedirectResponse
    {
        $data = $request->validate([
            'sign' => ['required', 'string', 'max:50'],
            'rate' => ['required', 'numeric', 'min:0.0001'],
            'status' => ['required', Rule::in([ListCountry::STATUS_INACTIVE, ListCountry::STATUS_ACTIVE])],
        ]);

        $country->update($data);

        return back()->with('success', "{$country->name} updated.");
    }

    public function states(ListCountry $country): Response
    {
        return Inertia::render('Admin/Settings/States', [
            'country' => ['id' => $country->id, 'name' => $country->name],
            'states' => $country->states()->orderBy('name')->get()->map(fn (StateSetting $s) => [
                'id' => $s->id,
                'state_code' => $s->state_code,
                'name' => $s->name,
                'shipping_zone' => (int) $s->shipping_zone,
            ])->all(),
            'zones' => [
                ['value' => StateSetting::ZONE_WEST, 'label' => 'Zone 1 — Peninsular'],
                ['value' => StateSetting::ZONE_EAST, 'label' => 'Zone 2 — Sabah / Sarawak / Labuan'],
            ],
        ]);
    }

    public function saveState(Request $request, ListCountry $country): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'state_code' => ['required', 'string', 'max:3'],
            'name' => ['required', 'string', 'max:40'],
            // Drives postage AND the COD benchmark fee — money, so it is
            // always an explicit choice, never inferred.
            'shipping_zone' => ['required', Rule::in([StateSetting::ZONE_WEST, StateSetting::ZONE_EAST])],
        ]);

        StateSetting::updateOrCreate(
            ['id' => $data['id'] ?? null, 'country_id' => $country->id],
            ['state_code' => $data['state_code'], 'name' => $data['name'], 'shipping_zone' => $data['shipping_zone']],
        );

        return back()->with('success', "{$data['name']} saved.");
    }
}

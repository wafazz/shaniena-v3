<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CodCharge;
use App\Models\ListCountry;
use App\Models\PostageCost;
use App\Models\StateSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Postage and COD fees, per country and shipping zone.
 *
 * Note the inherited inconsistency: postage_cost.shipping_zone is an integer
 * and cod_charges.shipping_zone is a string. Both are kept as the source
 * defines them, so the forms cast on the way in.
 */
class ShippingCostController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Settings/ShippingCost', [
            'countries' => ListCountry::query()->orderBy('name')->get()->map(fn (ListCountry $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'sign' => $c->sign,
                'active' => $c->isActive(),
            ])->all(),
            'zones' => [
                ['value' => StateSetting::ZONE_WEST, 'label' => 'Zone 1 — Peninsular Malaysia'],
                ['value' => StateSetting::ZONE_EAST, 'label' => 'Zone 2 — Sabah / Sarawak / Labuan'],
            ],
            'postage' => PostageCost::query()->get()->map(fn (PostageCost $p) => [
                'id' => $p->id,
                'country_id' => (int) $p->country_id,
                'shipping_zone' => (int) $p->shipping_zone,
                'currency' => $p->currency,
                'first_kilo' => (float) $p->first_kilo,
                'next_kilo' => (float) $p->next_kilo,
            ])->all(),
            'cod' => CodCharge::query()->get()->map(fn (CodCharge $c) => [
                'id' => $c->id,
                'country_id' => (int) $c->country_id,
                'shipping_zone' => (string) $c->shipping_zone,
                'benchmark_amount' => (float) $c->benchmark_amount,
                'cod_fee_below' => (float) $c->cod_fee_below,
                'cod_fee_above' => (float) $c->cod_fee_above,
            ])->all(),
        ]);
    }

    public function savePostage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer', Rule::exists('list_country', 'id')],
            'shipping_zone' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'max:10'],
            'first_kilo' => ['required', 'numeric', 'min:0'],
            'next_kilo' => ['required', 'numeric', 'min:0'],
        ]);

        PostageCost::updateOrCreate(
            ['country_id' => $data['country_id'], 'shipping_zone' => $data['shipping_zone']],
            $data,
        );

        return back()->with('success', 'Postage saved.');
    }

    public function saveCod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer', Rule::exists('list_country', 'id')],
            'shipping_zone' => ['required', 'string', 'max:10'],
            'benchmark_amount' => ['required', 'numeric', 'min:0'],
            'cod_fee_below' => ['required', 'numeric', 'min:0'],
            'cod_fee_above' => ['required', 'numeric', 'min:0'],
        ]);

        CodCharge::updateOrCreate(
            ['country_id' => $data['country_id'], 'shipping_zone' => $data['shipping_zone']],
            $data,
        );

        return back()->with('success', 'COD charge saved.');
    }
}

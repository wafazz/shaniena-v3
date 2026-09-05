<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Middleware\HandleStorefrontRequests;
use App\Models\Cart;
use App\Models\ListCountry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The country gate.
 *
 * The source made this the site root and bounced every other storefront page
 * back to it until a cookie was set — so a crawler, and any customer arriving
 * on a shared product link, hit a country picker instead of the page they
 * asked for. Here it is a page of its own: shoppers land on the shop, and a
 * single-country store never sees it at all.
 */
class CountryController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Shop/SelectCountry', [
            'countries' => ListCountry::query()->active()->orderBy('name')
                ->get(['id', 'name', 'sign'])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer', Rule::exists('list_country', 'id')->where('status', ListCountry::STATUS_ACTIVE)],
        ]);

        return redirect()
            ->intended('/')
            // 60 days, as the source set it.
            ->withCookie(cookie(HandleStorefrontRequests::COUNTRY_COOKIE, (string) $data['country_id'], 60 * 24 * 60));
    }

    /**
     * Switching country also empties the basket. The source left it, so a cart
     * priced in MYR kept its old currency_sign and prices after the switch.
     */
    public function change(Request $request): RedirectResponse
    {
        Cart::query()
            ->where('session_id', HandleStorefrontRequests::cartToken($request))
            ->whereIn('status', Cart::STATUS_ACTIVE)
            ->delete();

        return redirect('/select-country')
            ->withoutCookie(HandleStorefrontRequests::COUNTRY_COOKIE);
    }
}

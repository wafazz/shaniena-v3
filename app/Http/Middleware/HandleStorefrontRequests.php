<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\ListCountry;
use App\Services\StoreSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storefront-wide state: the chosen selling country, the nav's brand and
 * category dropdowns, the cart badge and the footer's store settings.
 *
 * The source rebuilt all of this with raw queries at the top of every page.
 */
class HandleStorefrontRequests
{
    /** The country cookie the source set, kept so returning shoppers keep theirs. */
    public const COUNTRY_COOKIE = 'country_id';

    /**
     * The basket is keyed to this, not to the session id.
     *
     * The source used the raw session id, so signing in — which regenerates
     * the session — silently orphaned the customer's cart, and so did a
     * session timeout mid-shop. A dedicated token survives both.
     */
    public const CART_COOKIE = 'cart_token';

    private const CART_COOKIE_DAYS = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->cookie(self::CART_COOKIE);
        $issued = false;

        if (! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            $token = Str::random(40);
            $issued = true;
        }

        $request->attributes->set('storefront.cart_token', $token);
        $request->attributes->set('storefront.country', $this->resolveCountry($request));

        $response = $next($request);

        return $issued
            ? $response->withCookie(cookie(self::CART_COOKIE, $token, 60 * 24 * self::CART_COOKIE_DAYS))
            : $response;
    }

    public static function cartToken(Request $request): string
    {
        return (string) $request->attributes->get('storefront.cart_token');
    }

    /** Nav data changes rarely and is read on every page — cache it. */
    public static function navigation(): array
    {
        return Cache::remember('storefront:nav', 600, fn () => [
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name', 'slug'])->all(),
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug'])->all(),
        ]);
    }

    public static function cartCount(Request $request): int
    {
        return (int) Cart::query()
            ->where('session_id', self::cartToken($request))
            ->active()
            ->sum('quantity');
    }

    /** @return array<string, mixed> */
    public static function footer(StoreSettings $settings): array
    {
        return [
            'name' => $settings->get('store_name', 'Shaniena'),
            'email' => $settings->get('store_email'),
            'phone' => $settings->get('store_phone'),
            'address' => $settings->get('store_address'),
            'facebook' => $settings->get('facebook_url'),
            'instagram' => $settings->get('instagram_url'),
            'whatsapp' => $settings->get('whatsapp_number'),
        ];
    }

    private function resolveCountry(Request $request): ?ListCountry
    {
        $id = (int) $request->cookie(self::COUNTRY_COOKIE);

        $country = $id
            ? ListCountry::query()->active()->find($id)
            : null;

        // Falling back to the only active country means a single-country shop
        // never has to show the country gate at all.
        if (! $country) {
            $active = ListCountry::query()->active()->get();
            $country = $active->count() === 1 ? $active->first() : null;
        }

        return $country;
    }
}

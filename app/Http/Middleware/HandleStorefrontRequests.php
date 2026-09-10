<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\ImageSetting;
use App\Models\ListCountry;
use App\Services\Storefront\Visitors;
use App\Services\StoreSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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

    /** Bumped by LogoSettingController whenever the active logo changes. */
    public const LOGO_CACHE_KEY = 'storefront:logo';

    private const LOGO_CACHE_TTL = 600;

    public function handle(Request $request, Closure $next): Response
    {
        // The console has no basket, and this runs on the whole web group.
        if ($request->is('admin', 'admin/*')) {
            return $next($request);
        }

        $token = (string) $request->cookie(self::CART_COOKIE);
        $issued = false;

        if (! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            $token = Str::random(40);
            $issued = true;
        }

        $request->attributes->set('storefront.cart_token', $token);
        $request->attributes->set('storefront.country', $this->resolveCountry($request));

        $response = $next($request);

        // Counted after the response, and only for ordinary page views: the
        // table this fills had never been written to by anything, so the
        // migrated visitor tiles have always read zero.
        if (Visitors::shouldRecord($request) && $response->getStatusCode() < 400) {
            app(Visitors::class)->record($request);
        }

        if ($issued) {
            // setCookie(), not withCookie(): the latter is only on Laravel's
            // own Response, so a streamed or file response threw here.
            $response->headers->setCookie(
                cookie(self::CART_COOKIE, $token, 60 * 24 * self::CART_COOKIE_DAYS),
            );
        }

        return $response;
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
            'logo' => self::logo(),
        ];
    }

    /**
     * The active logo's URL, or null while none has been uploaded — in which
     * case the themes fall back to the store name as text, exactly as they
     * rendered before. Cached like the nav: this is read on every storefront
     * page and changes about as often as the shop is rebranded.
     */
    public static function logo(): ?string
    {
        $url = Cache::remember(self::LOGO_CACHE_KEY, self::LOGO_CACHE_TTL, function () {
            $path = ImageSetting::query()->logos()->where('sorting', 1)->value('image_path');

            // '' rather than null as the "no logo" marker: Cache::remember
            // reads a cached null as a miss, so a shop that has never
            // uploaded one would re-run this query on every page.
            return $path ? Storage::disk('public')->url($path) : '';
        });

        return $url ?: null;
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

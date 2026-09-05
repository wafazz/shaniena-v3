<?php

namespace Tests;

use App\Http\Middleware\HandleStorefrontRequests;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Carry a response's cookies into the next request, the way a browser does.
     *
     * Laravel's test client does not send response cookies back, so without
     * this every call starts a fresh session and a fresh cart token — and the
     * storefront's cart, checkout and country gate all span several requests.
     */
    /**
     * Carry the storefront's cart and country cookies into the next request,
     * the way a browser would.
     *
     * Only those two: they are exempt from cookie encryption, and nothing on
     * the shop depends on the session surviving between requests — the basket
     * is keyed to the cart token precisely so it does not have to.
     */
    public function keep(TestResponse $response): TestResponse
    {
        $carried = [
            HandleStorefrontRequests::CART_COOKIE,
            HandleStorefrontRequests::COUNTRY_COOKIE,
        ];

        foreach ($response->headers->getCookies() as $cookie) {
            if (in_array($cookie->getName(), $carried, true) && (string) $cookie->getValue() !== '') {
                $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
            }
        }

        return $response;
    }

    //
}

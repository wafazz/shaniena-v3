<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Middleware\HandleStorefrontRequests;
use App\Models\ListCountry;
use App\Models\OrderTempData;
use App\Models\StateSetting;
use App\Services\Payments\PaymentGateways;
use App\Services\Storefront\Basket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checkout.
 *
 * Totals are computed here, from the database, on every request. The source
 * computed them in the view, stashed them in $_SESSION and let the payment
 * controllers bill whatever was there.
 */
class CheckoutController extends Controller
{
    /** Address fields the source persisted for 30 days on consent. */
    private const REMEMBERED = [
        'first_name', 'last_name', 'address_1', 'address_2',
        'city', 'state', 'postcode', 'phone', 'email',
    ];

    public function __construct(
        private Basket $basket,
        private PaymentGateways $gateways,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $country = $request->attributes->get('storefront.country');
        $sessionId = HandleStorefrontRequests::cartToken($request);

        if (! $country) {
            return redirect('/select-country');
        }

        $draft = OrderTempData::forSession($sessionId)->latest('id')->first();
        $state = $draft->state ?? null;

        return Inertia::render('Shop/Checkout', [
            'summary' => $this->basket->summary($sessionId, $country, $state, $request->boolean('cod')),
            'address' => $this->rememberedAddress($request, $draft),
            'states' => StateSetting::query()
                ->where('country_id', $country->id)->orderBy('name')->pluck('name')->all(),
            'country' => ['id' => $country->id, 'name' => $country->name, 'sign' => $country->sign],
            // A channel is offered only if it is switched on AND configured;
            // the source rendered a SenangPay button whose settings row did
            // not exist, and computed $senangpayEnabled without ever using it.
            'payments' => $this->gateways->availability(),
        ]);
    }

    /**
     * Save the address and re-price. Returns the recalculated summary so the
     * customer sees postage before choosing how to pay.
     */
    public function address(Request $request): RedirectResponse
    {
        $country = $request->attributes->get('storefront.country');
        abort_unless($country, 400);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'address_1' => ['required', 'string', 'max:255'],
            'address_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            // A country with states configured must be given one of them; a
            // country without takes free text. Keying on whether zones exist
            // rather than on Malaysia's id, as the source did.
            'state' => $this->hasStates($country)
                ? ['required', Rule::exists('state', 'name')->where('country_id', $country->id)]
                : ['required', 'string', 'max:255'],
            'postcode' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:150'],
            'remark' => ['nullable', 'string', 'max:1000'],
            'courier_service' => ['required', 'string', 'max:255'],
            'remember' => ['boolean'],
        ]);

        $sessionId = HandleStorefrontRequests::cartToken($request);
        $summary = $this->basket->summary($sessionId, $country, $data['state']);

        abort_if($summary['items'] === [], 400, 'Your cart is empty.');

        OrderTempData::updateOrCreate(
            ['session_id' => $sessionId],
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'add_1' => $data['address_1'],
                'add_2' => $data['address_2'] ?? '',
                'city' => $data['city'],
                'state' => $data['state'],
                'postcode' => $data['postcode'],
                'country_name' => $country->name,
                'country_id' => $country->id,
                'phone' => $data['phone'],
                'email' => $data['email'],
                'remark' => $data['remark'] ?? '',
                'method' => $data['courier_service'],
                'currency_sign' => $country->sign,
                // Stored for reference only — the charge is recomputed at
                // payment time and never read back from here.
                'amount' => $summary['total'],
                'shipping_cost' => $summary['postage'],
                'status' => 0,
            ],
        );

        $response = back()->with('success', 'Delivery details saved.');

        // 30-day address memory, only with consent, exactly as the source.
        if ($request->boolean('remember')) {
            foreach (self::REMEMBERED as $field) {
                $response->withCookie(cookie("checkout_{$field}", (string) ($data[$field] ?? ''), 60 * 24 * 30));
            }
        }

        return $response;
    }

    private function hasStates(ListCountry $country): bool
    {
        return StateSetting::query()->where('country_id', $country->id)->exists();
    }

    /** @return array<string, string> */
    private function rememberedAddress(Request $request, ?OrderTempData $draft): array
    {
        $fromDraft = [
            'first_name' => $draft->first_name ?? null,
            'last_name' => $draft->last_name ?? null,
            'address_1' => $draft->add_1 ?? null,
            'address_2' => $draft->add_2 ?? null,
            'city' => $draft->city ?? null,
            'state' => $draft->state ?? null,
            'postcode' => $draft->postcode ?? null,
            'phone' => $draft->phone ?? null,
            'email' => $draft->email ?? null,
        ];

        return collect(self::REMEMBERED)
            ->mapWithKeys(fn (string $field) => [
                $field => $fromDraft[$field] ?: (string) $request->cookie("checkout_{$field}", ''),
            ])
            ->put('remark', $draft->remark ?? '')
            ->put('courier_service', $draft->method ?? '')
            ->all();
    }
}

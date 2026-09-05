<?php

namespace App\Services\Storefront;

use App\Models\Cart;
use App\Models\CodCharge;
use App\Models\CountryPrice;
use App\Models\ListCountry;
use App\Models\PostageCost;
use App\Models\StateSetting;
use Illuminate\Support\Collection;

/**
 * The authoritative basket total.
 *
 * This is the single most important difference from the source. There, the
 * subtotal, postage and COD fee were computed *inside the checkout view* and
 * written to $_SESSION, then read back by the payment controllers and sent to
 * the gateway. Anything that could render the checkout page could therefore
 * decide what the customer was charged.
 *
 * Nothing here reads a total from the request. Prices come from
 * list_country_product_price at the moment of calculation, postage from the
 * state's shipping zone, and the COD fee from the benchmark table.
 */
class Basket
{
    public function __construct(private Catalogue $catalogue) {}

    /** @return Collection<int, Cart> */
    public function lines(string $sessionId): Collection
    {
        return Cart::query()
            ->where('session_id', $sessionId)
            ->whereIn('status', Cart::STATUS_ACTIVE)
            ->with(['product:id,name,slug,weight', 'variant:id,variant_name,sku,max_purchase'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Everything the checkout needs, priced from the database.
     *
     * @return array<string, mixed>
     */
    public function summary(string $sessionId, ?ListCountry $country, ?string $state = null, bool $cod = false): array
    {
        $lines = $this->lines($sessionId);
        $prices = $this->catalogue->prices($lines->pluck('p_id'), $country);

        $items = $lines->map(function (Cart $line) use ($prices) {
            // Live price, not the one captured when the item was added — the
            // customer pays what the product costs now.
            $unit = (float) ($prices->get($line->p_id)?->sale_price ?? $line->price);

            return [
                'id' => $line->id,
                'product_id' => $line->p_id,
                'variant_id' => $line->pv_id,
                'name' => $line->product?->name ?? 'Product removed',
                'slug' => $line->product?->slug,
                'variant' => $line->variant?->variant_name,
                'quantity' => (int) $line->quantity,
                'unit_price' => round($unit, 2),
                'line_total' => round($unit * (int) $line->quantity, 2),
                'weight' => (int) $line->total_weight,
                'max_purchase' => (int) ($line->variant?->max_purchase ?: 99),
            ];
        });

        $subtotal = round($items->sum('line_total'), 2);
        $weightGrams = (int) $items->sum('weight');
        $zone = $this->zoneFor($country, $state);
        $postage = $this->postage($country, $zone, $weightGrams);
        $codFee = $cod ? $this->codFee($country, $zone, $subtotal) : 0.0;

        return [
            'items' => $items->values()->all(),
            'currency' => $country?->sign ?? 'MYR',
            'weight_grams' => $weightGrams,
            'shipping_zone' => $zone,
            'subtotal' => $subtotal,
            'postage' => $postage,
            'cod_fee' => $codFee,
            'total' => round($subtotal + $postage + $codFee, 2),
            // Postage can only be worked out once we know the destination.
            'postage_known' => $zone !== null,
        ];
    }

    /**
     * A country with zoned states needs one before postage can be worked out;
     * one without is a single zone.
     *
     * Decided by whether states are configured, not by hardcoding Malaysia's
     * id — the source keyed on `country == "1"` in several places, which breaks
     * the moment a second zoned country is added.
     */
    public function zoneFor(?ListCountry $country, ?string $state): ?int
    {
        if (! $country) {
            return null;
        }

        $hasZonedStates = StateSetting::query()->where('country_id', $country->id)->exists();

        if (! $hasZonedStates) {
            return StateSetting::ZONE_WEST;
        }

        if (! $state) {
            return null;
        }

        return StateSetting::query()
            ->where('country_id', $country->id)
            ->where('name', $state)
            ->value('shipping_zone');
    }

    /**
     * First kilo flat, then every additional kilo rounded UP to the next whole
     * kilo. Mirrors calculatePostage() exactly, including the ceil().
     */
    public function postage(?ListCountry $country, ?int $zone, int $weightGrams): float
    {
        if (! $country || $zone === null) {
            return 0.0;
        }

        $rate = PostageCost::query()
            ->where('country_id', $country->id)
            ->where('shipping_zone', $zone)
            ->first();

        if (! $rate) {
            return 0.0;
        }

        $kg = $weightGrams / 1000;

        return $kg <= 1
            ? round((float) $rate->first_kilo, 2)
            : round((float) $rate->first_kilo + ceil($kg - 1) * (float) $rate->next_kilo, 2);
    }

    /** Under the benchmark pays the lower fee; at or above it, the higher. */
    public function codFee(?ListCountry $country, ?int $zone, float $subtotal): float
    {
        if (! $country || $zone === null) {
            return 0.0;
        }

        $charge = CodCharge::query()
            ->where('country_id', $country->id)
            ->where('shipping_zone', (string) $zone)
            ->first();

        return $charge ? round($charge->feeForSubtotal($subtotal), 2) : 0.0;
    }

    /** @return array<string, mixed>|null */
    public function priceFor(int $productId, ?ListCountry $country): ?CountryPrice
    {
        return $this->catalogue->prices([$productId], $country)->get($productId);
    }
}

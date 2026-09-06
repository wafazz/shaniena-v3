<?php

namespace App\Services\Storefront;

use App\Models\Cart;
use App\Models\CountryPrice;
use App\Models\ListCountry;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Price and stock resolution for the storefront.
 *
 * The source repeated this in every view with per-row queries: getPriceOnCountry()
 * per card, stockBalanceByVariant() per variant, getAllProductImage() per product.
 * Everything here is batched.
 */
class Catalogue
{
    /**
     * Physical stock per variant: received minus dispatched, minus whatever is
     * sitting in anyone's live basket. Mirrors stockBalanceByVariant().
     *
     * @param  iterable<int>  $variantIds
     * @return array<int, int>
     */
    public function stockByVariant(iterable $variantIds): array
    {
        $ids = collect($variantIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $ledger = DB::table('stock_control')
            ->whereIn('pv_id', $ids)
            ->whereNull('deleted_at')
            ->groupBy('pv_id')
            ->pluck(DB::raw('SUM(stock_in) - SUM(stock_out)'), 'pv_id');

        $reserved = DB::table('cart')
            ->whereIn('pv_id', $ids)
            ->whereIn('status', Cart::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->groupBy('pv_id')
            ->pluck(DB::raw('SUM(quantity)'), 'pv_id');

        return $ids->mapWithKeys(fn ($id) => [
            (int) $id => (int) ($ledger[$id] ?? 0) - (int) ($reserved[$id] ?? 0),
        ])->all();
    }

    /**
     * Customer-facing prices, keyed by product id.
     *
     * @param  iterable<int>  $productIds
     * @return Collection<int, CountryPrice>
     */
    public function prices(iterable $productIds, ?ListCountry $country): Collection
    {
        if (! $country) {
            return collect();
        }

        return CountryPrice::query()
            ->where('country_id', $country->id)
            ->whereIn('product_id', collect($productIds)->unique()->values())
            ->get()
            ->keyBy('product_id');
    }

    /**
     * Card shape, shared by the homepage rows, listings and search.
     *
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    public function cards(Collection $products, ?ListCountry $country): array
    {
        if ($products->isEmpty()) {
            return [];
        }

        $prices = $this->prices($products->pluck('id'), $country);
        $stock = $this->stockByVariant($products->flatMap->variants->pluck('id'));

        return $products->map(function (Product $product) use ($prices, $stock, $country) {
            $price = $prices->get($product->id);
            $sellable = $product->variants->filter(fn ($v) => ($stock[$v->id] ?? 0) > 0)->values();
            $inStock = $sellable->isNotEmpty();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'image' => $this->imageUrl($product),
                'currency' => $country?->sign ?? 'MYR',
                'price' => $price ? number_format((float) $price->sale_price, 2) : null,
                'was' => $price && $price->isDiscounted()
                    ? number_format((float) $price->market_price, 2)
                    : null,
                'in_stock' => $inStock,
                // Only set where the choice is not the customer's to make: one
                // sellable variant means the card can add straight to the
                // basket. A product with a size or a shade still goes to its
                // own page, because picking one for them is how the wrong item
                // ends up in an order.
                'default_variant_id' => $sellable->count() === 1 ? (int) $sellable->first()->id : null,
            ];
        })->values()->all();
    }

    public function imageUrl(Product $product): ?string
    {
        $first = $product->relationLoaded('images')
            ? $product->images->first()
            : $product->images()->first();

        return $first ? Storage::disk('public')->url($first->image) : null;
    }

    /**
     * The variant a product page should open on: the first in stock, in id
     * order, falling back to the first variant when none are. Mirrors the
     * source's auto-select loop.
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @param  array<int, int>  $stock
     */
    public function defaultVariant(Collection $variants, array $stock): ?ProductVariant
    {
        return $variants->first(fn (ProductVariant $v) => ($stock[$v->id] ?? 0) > 0)
            ?? $variants->first();
    }
}

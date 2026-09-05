<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Storefront\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private Catalogue $catalogue) {}

    public function __invoke(Request $request, Product $product): Response
    {
        abort_unless($product->status, 404);

        $country = $request->attributes->get('storefront.country');
        $product->load(['images', 'brand:id,name,slug', 'category:id,name,slug', 'variants']);

        $stock = $this->catalogue->stockByVariant($product->variants->pluck('id'));
        $price = $this->catalogue->prices([$product->id], $country)->get($product->id);
        $default = $this->catalogue->defaultVariant($product->variants, $stock);

        return Inertia::render('Shop/Product', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'brand' => $product->brand?->only(['id', 'name', 'slug']),
                'category' => $product->category?->only(['id', 'name', 'slug']),
                'images' => $product->images
                    ->map(fn ($i) => Storage::disk('public')->url($i->image))->values()->all(),
                'currency' => $country?->sign ?? 'MYR',
                'price' => $price ? number_format((float) $price->sale_price, 2) : null,
                'was' => $price && $price->isDiscounted()
                    ? number_format((float) $price->market_price, 2)
                    : null,
            ],
            // Every variant carries its own stock and purchase cap, so the page
            // can switch without another round trip.
            'variants' => $product->variants->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'name' => $v->variant_name ?: $v->sku,
                'sku' => $v->sku,
                'stock' => max(0, $stock[$v->id] ?? 0),
                'max_purchase' => max(1, (int) $v->max_purchase),
            ])->values()->all(),
            'defaultVariantId' => $default?->id,
            'related' => $this->catalogue->cards(
                Product::query()
                    ->where('status', true)
                    ->where('id', '!=', $product->id)
                    ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
                    ->with(['images', 'variants'])
                    ->inRandomOrder()
                    ->limit(4)
                    ->get(),
                $country,
            ),
        ]);
    }
}

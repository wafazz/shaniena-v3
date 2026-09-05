<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CountryPrice;
use App\Models\ListCountry;
use App\Models\Product;
use App\Services\Storefront\Catalogue;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Category, brand, promo and search listings.
 *
 * The source ran these with no LIMIT at all — a category with 4,000 products
 * rendered all 4,000 — and sorted `ORDER BY created_at` with no direction, so
 * every listing led with the oldest stock in the catalogue.
 */
class CatalogueController extends Controller
{
    private const PER_PAGE = 24;

    public function __construct(private Catalogue $catalogue) {}

    public function category(Request $request, Category $category): Response
    {
        return $this->listing($request, [
            'kind' => 'category',
            'title' => $category->name,
            'heading' => "Everything in {$category->name}",
        ], fn (Builder $q) => $q->where('category_id', $category->id));
    }

    public function brand(Request $request, Brand $brand): Response
    {
        return $this->listing($request, [
            'kind' => 'brand',
            'title' => $brand->name,
            'heading' => "Everything by {$brand->name}",
        ], fn (Builder $q) => $q->where('brand_id', $brand->id));
    }

    public function promos(Request $request): Response
    {
        $country = $request->attributes->get('storefront.country');

        return $this->listing($request, [
            'kind' => 'promos',
            'title' => 'Promos',
            'heading' => 'On offer right now',
        ], function (Builder $q) use ($country) {
            if (! $country) {
                return $q->whereRaw('1 = 0');
            }

            return $q->whereIn('id', CountryPrice::query()
                ->where('country_id', $country->id)
                ->whereColumn('market_price', '>', 'sale_price')
                ->select('product_id'));
        });
    }

    public function search(Request $request): Response
    {
        $term = trim((string) $request->query('q', ''));

        return $this->listing($request, [
            'kind' => 'search',
            'title' => $term === '' ? 'Search' : "Search: {$term}",
            'heading' => $term === '' ? 'Search the shop' : "Results for “{$term}”",
            'term' => $term,
        ], fn (Builder $q) => $term === ''
            ? $q->whereRaw('1 = 0')
            : $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$term}%")
                ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', "%{$term}%"))));
    }

    /** @param  array<string, mixed>  $meta */
    private function listing(Request $request, array $meta, callable $scope): Response
    {
        $country = $request->attributes->get('storefront.country');
        $sort = in_array($request->query('sort'), ['price-asc', 'price-desc', 'name'], true)
            ? $request->query('sort')
            : 'newest';

        $products = Product::query()
            ->where('status', true)
            ->with(['images', 'variants'])
            ->tap($scope)
            ->tap(fn (Builder $q) => $this->applySort($q, $sort, $country))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Shop/Listing', [
            'meta' => $meta + ['sort' => $sort],
            'products' => $this->catalogue->cards($products->getCollection(), $country),
            'paging' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    private function applySort(Builder $query, string $sort, ?ListCountry $country): void
    {
        if (in_array($sort, ['price-asc', 'price-desc'], true) && $country) {
            $query
                ->leftJoin('list_country_product_price as cp', function ($join) use ($country) {
                    $join->on('cp.product_id', '=', 'products.id')->where('cp.country_id', $country->id);
                })
                ->orderBy('cp.sale_price', $sort === 'price-asc' ? 'asc' : 'desc')
                ->select('products.*');

            return;
        }

        // Newest first. The source had no direction at all, so it was oldest.
        $sort === 'name'
            ? $query->orderBy('name')
            : $query->latest('products.id');
    }
}

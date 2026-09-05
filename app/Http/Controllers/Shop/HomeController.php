<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Category;
use App\Models\ListCountry;
use App\Models\Product;
use App\Services\Storefront\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(private Catalogue $catalogue) {}

    public function __invoke(Request $request): Response
    {
        $country = $request->attributes->get('storefront.country');

        return Inertia::render('Shop/Home', [
            'categories' => Category::query()
                ->orderBy('sort_order')->orderBy('name')->limit(8)
                ->get(['id', 'name', 'slug'])->all(),
            // Newest first. The source sorted `ORDER BY created_at` ascending
            // with no direction, so its "New Arrival" row showed the oldest
            // products in the catalogue.
            'newArrivals' => $this->catalogue->cards(
                Product::query()->where('status', true)->with(['images', 'variants'])->latest('id')->limit(8)->get(),
                $country,
            ),
            'bestSellers' => $this->catalogue->cards($this->bestSellers(), $country),
            'promos' => $this->catalogue->cards($this->promos($country), $country),
        ]);
    }

    /** Top sellers by paid cart quantity. */
    /**
     * Two queries on purpose: MariaDB rejects LIMIT inside an IN subquery
     * ("doesn't yet support 'LIMIT & IN/ALL/ANY/SOME subquery'"), so the ids
     * are resolved first and the ordering replayed with FIELD().
     */
    private function bestSellers()
    {
        $ids = DB::table('cart')
            ->where('status', Cart::STATUS_PAID)
            ->groupBy('p_id')
            ->orderByRaw('SUM(quantity) DESC')
            ->limit(8)
            ->pluck('p_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->where('status', true)
            ->with(['images', 'variants'])
            ->whereIn('id', $ids)
            ->orderByRaw('FIELD(id, '.$ids->implode(',').')')
            ->get();
    }

    /** Anything whose sale price undercuts its market price in this country. */
    private function promos(?ListCountry $country)
    {
        if (! $country) {
            return collect();
        }

        return Product::query()
            ->where('status', true)
            ->with(['images', 'variants'])
            ->join('list_country_product_price as p', 'p.product_id', '=', 'products.id')
            ->where('p.country_id', $country->id)
            ->whereColumn('p.market_price', '>', 'p.sale_price')
            ->orderByRaw('(p.market_price - p.sale_price) DESC')
            ->limit(20)
            ->select('products.*')
            ->get();
    }
}

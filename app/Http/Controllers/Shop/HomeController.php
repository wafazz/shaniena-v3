<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Category;
use App\Models\ListCountry;
use App\Models\Product;
use App\Models\Slider;
use App\Services\Storefront\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(private Catalogue $catalogue) {}

    public function __invoke(Request $request): Response
    {
        $country = $request->attributes->get('storefront.country');

        return Inertia::render('Shop/Home', [
            // The source's hero was three hardcoded .webp files inside a
            // commented-out block, so the console's Slider Setting screen has
            // been managing a table the storefront never read.
            'slides' => Slider::query()->active()->ordered()->get()
                ->values()
                ->map(fn (Slider $slide, int $i) => [
                    'id' => $slide->id,
                    'title' => $slide->title ?: null,
                    'link' => $slide->link_url ?: null,
                    'image' => Storage::disk('public')->url($slide->image),
                    'first' => $i === 0,
                ])->all(),
            'categories' => Category::query()
                ->withCount(['products' => fn ($q) => $q->where('status', true)])
                ->orderBy('sort_order')->orderBy('name')->limit(8)
                ->get(['id', 'name', 'slug', 'image'])
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'count' => (int) $category->products_count,
                    // Ashion drives these tiles off a background photo; without
                    // one the tile is a 314px void.
                    'image' => filled($category->image)
                        ? Storage::disk('public')->url($category->image)
                        : null,
                ])->all(),
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
            // Bound, not interpolated. These ids come from the database, so
            // this was never exploitable — but it was the only interpolated
            // SQL left in the codebase, and it is a poor pattern to leave as
            // precedent for the next person who needs a FIELD() replay.
            ->orderByRaw('FIELD(id, '.$ids->map(fn () => '?')->implode(',').')', $ids->all())
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

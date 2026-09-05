<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Activity;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CountryPrice;
use App\Models\ListCountry;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One component for create and edit.
 *
 * The source kept new-product.php and update-product.php as near-identical
 * 400-line files that had already drifted apart — different placeholders,
 * different required rules, different image-limit copy.
 */
class ProductController extends Controller
{
    private const IMAGE_DISK = 'public';

    private const PER_PAGE = 25;

    /**
     * The catalogue.
     *
     * The source had no product list at all — finding a product meant opening
     * Stock Control, which lists variants, not products, and cannot show a
     * product that has none yet.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'category' => $request->filled('category') ? (int) $request->query('category') : null,
            'brand' => $request->filled('brand') ? (int) $request->query('brand') : null,
            'status' => in_array($request->query('status'), ['1', '0'], true)
                ? (int) $request->query('status')
                : null,
        ];

        return Inertia::render('Admin/Products/Index', [
            'filters' => $filters,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'products' => fn () => $this->paginate($filters),
        ]);
    }

    /** @param  array<string, mixed>  $filters  @return array<string, mixed> */
    private function paginate(array $filters): array
    {
        $products = Product::query()
            ->with([
                'category:id,name',
                'brand:id,name',
                'variants' => fn ($q) => $q->select('id', 'product_id', 'price_retail', 'price_sale', 'stock', 'status'),
                'images' => fn ($q) => $q->orderBy('id')->limit(1),
            ])
            ->when($filters['search'] !== '', fn ($q) => $q->where(function ($inner) use ($filters) {
                $inner
                    ->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('slug', 'like', '%'.$filters['search'].'%')
                    // A SKU is what someone reads off a box, so search it too.
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', '%'.$filters['search'].'%'));
            }))
            ->when($filters['category'], fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['brand'], fn ($q, $id) => $q->where('brand_id', $id))
            ->when($filters['status'] !== null, fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return [
            'data' => $products->getCollection()->map(function (Product $product) {
                $prices = $product->variants
                    ->map(fn ($v) => (float) ($v->price_sale > 0 ? $v->price_sale : $v->price_retail))
                    ->filter()
                    ->values();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'type' => $product->type,
                    'category' => $product->category?->name,
                    'brand' => $product->brand?->name,
                    'status' => (bool) $product->status,
                    'image' => $product->images->first()?->image,
                    'variants' => $product->variants->count(),
                    'stock' => (int) $product->variants->sum('stock'),
                    'price_from' => $prices->min(),
                    'price_to' => $prices->max(),
                ];
            })->all(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ];
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Form', $this->formProps(null));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($request->safe()->only([
                'name', 'slug', 'description', 'category_id', 'brand_id',
                'weight', 'length', 'width', 'height', 'type', 'price_capital', 'status',
            ]));

            $this->syncVariants($product, $request->validated('variants'));
            $this->syncPrices($product, $request->validated('prices') ?? []);
            $this->storeImages($product, $request->file('images') ?? []);

            return $product;
        });

        Activity::record((int) $request->user('admin')->getKey(), "Created product {$product->name}", "products|{$product->id}", 'product_activity');

        return redirect()->route('admin.products.edit', $product)->with('success', "{$product->name} saved.");
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Form', $this->formProps($product));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product) {
            $product->update($request->safe()->only([
                'name', 'slug', 'description', 'category_id', 'brand_id',
                'weight', 'length', 'width', 'height', 'type', 'price_capital', 'status',
            ]));

            $this->syncVariants($product, $request->validated('variants'));
            $this->syncPrices($product, $request->validated('prices') ?? []);
            $this->pruneImages($product, $request->validated('keep_images') ?? []);
            $this->storeImages($product, $request->file('images') ?? []);
        });

        Activity::record((int) $request->user('admin')->getKey(), "Updated product {$product->name}", "products|{$product->id}", 'product_activity');

        return back()->with('success', "{$product->name} saved.");
    }

    /** @return array<string, mixed> */
    private function formProps(?Product $product): array
    {
        $countries = ListCountry::query()->active()->orderBy('name')->get();

        return [
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'category_id' => $product->category_id,
                'brand_id' => $product->brand_id,
                'weight' => $product->weight,
                'length' => $product->length,
                'width' => $product->width,
                'height' => $product->height,
                'type' => $product->type ?: Product::TYPE_SIMPLE,
                'price_capital' => (float) $product->price_capital,
                'status' => (bool) $product->status,
                'variants' => $product->variants()->orderBy('id')->get()->map(fn (ProductVariant $v) => [
                    'id' => $v->id,
                    'variant_name' => $v->variant_name,
                    'sku' => $v->sku,
                    'price_retail' => (float) $v->price_retail,
                    'price_sale' => (float) $v->price_sale,
                    'max_purchase' => (int) $v->max_purchase,
                ])->all(),
                'images' => $product->images()->orderBy('id')->get()->map(fn (ProductImage $i) => [
                    'id' => $i->id,
                    'url' => Storage::disk(self::IMAGE_DISK)->url($i->image),
                ])->all(),
                'prices' => CountryPrice::query()
                    ->where('product_id', $product->id)
                    ->get()
                    ->keyBy('country_id')
                    ->map(fn (CountryPrice $p) => [
                        'market_price' => (float) $p->market_price,
                        'sale_price' => (float) $p->sale_price,
                    ])->all(),
            ] : null,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'countries' => $countries->map(fn (ListCountry $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'sign' => $c->sign,
            ])->all(),
        ];
    }

    private function syncVariants(Product $product, array $variants): void
    {
        $kept = [];

        foreach ($variants as $row) {
            $variant = $product->variants()->updateOrCreate(
                ['id' => $row['id'] ?? null],
                [
                    'variant_name' => $row['variant_name'] ?? null,
                    'sku' => $row['sku'],
                    'price_retail' => $row['price_retail'],
                    'price_sale' => $row['price_sale'],
                    'max_purchase' => $row['max_purchase'],
                ],
            );

            $kept[] = $variant->id;
        }

        // Variants removed in the form are soft-deleted, never hard-deleted —
        // historical cart and order rows still point at them.
        $product->variants()->whereNotIn('id', $kept)->delete();
    }

    private function syncPrices(Product $product, array $prices): void
    {
        foreach ($prices as $countryId => $pair) {
            CountryPrice::updateOrCreate(
                ['product_id' => $product->id, 'country_id' => (int) $countryId],
                ['market_price' => $pair['market_price'], 'sale_price' => $pair['sale_price']],
            );
        }
    }

    /** @param  list<UploadedFile>  $files */
    private function storeImages(Product $product, array $files): void
    {
        foreach ($files as $file) {
            $product->images()->create([
                'image' => $file->store("products/{$product->id}", self::IMAGE_DISK),
            ]);
        }
    }

    private function pruneImages(Product $product, array $keepIds): void
    {
        $product->images()
            ->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds))
            ->get()
            ->each(function (ProductImage $image) {
                Storage::disk(self::IMAGE_DISK)->delete($image->image);
                $image->delete();
            });
    }
}

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

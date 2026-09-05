<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\CountryPrice;
use App\Models\ListCountry;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function productPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Hydra Glow Cleanser',
        'slug' => 'hydra-glow-cleanser',
        'description' => 'A gentle daily cleanser.',
        'category_id' => Category::factory()->create()->id,
        'brand_id' => Brand::factory()->create()->id,
        'weight' => 150, 'length' => 100, 'width' => 80, 'height' => 60,
        'type' => 'simple',
        'price_capital' => 22.50,
        'status' => true,
        'variants' => [
            ['id' => null, 'variant_name' => 'Default', 'sku' => 'HGC-100', 'price_retail' => 59.90, 'price_sale' => 49.90, 'max_purchase' => 5],
        ],
        'prices' => [],
    ], $overrides);
}

it('creates a product with its variant', function () {
    $admin = queueAdmin('new-product');

    $this->actingAs($admin, 'admin')->post('/admin/products', productPayload())->assertRedirect();

    $product = Product::where('slug', 'hydra-glow-cleanser')->first();

    expect($product)->not->toBeNull()
        ->and($product->weight)->toBe(150)
        ->and($product->variants()->count())->toBe(1)
        ->and($product->variants()->first()->sku)->toBe('HGC-100');
});

it('rejects a duplicate slug', function () {
    $admin = queueAdmin('new-product');
    $this->actingAs($admin, 'admin')->post('/admin/products', productPayload());

    $this->actingAs($admin, 'admin')->post('/admin/products', productPayload(['name' => 'Another']))
        ->assertSessionHasErrors('slug');
});

it('requires a name on every variant of a variable product', function () {
    $admin = queueAdmin('new-product');

    $this->actingAs($admin, 'admin')->post('/admin/products', productPayload([
        'type' => 'variable',
        'variants' => [['id' => null, 'variant_name' => '', 'sku' => 'X-1', 'price_retail' => 10, 'price_sale' => 9, 'max_purchase' => 1]],
    ]))->assertSessionHasErrors('variants.0.variant_name');
});

it('writes customer-facing prices to list_country_product_price', function () {
    $admin = queueAdmin('new-product');
    $country = ListCountry::create(['name' => 'Malaysia', 'sign' => 'MYR', 'rate' => 1, 'phone_code' => '+60', 'status' => 1]);

    $this->actingAs($admin, 'admin')->post('/admin/products', productPayload([
        'prices' => [$country->id => ['market_price' => 79.90, 'sale_price' => 59.90]],
    ]));

    $price = CountryPrice::where('country_id', $country->id)->first();

    expect((float) $price->market_price)->toBe(79.90)
        ->and((float) $price->sale_price)->toBe(59.90)
        ->and($price->isDiscounted())->toBeTrue();
});

it('soft-deletes a variant dropped from the form, never hard-deletes it', function () {
    $admin = queueAdmin('new-product');
    $this->actingAs($admin, 'admin')->post('/admin/products', productPayload([
        'type' => 'variable',
        'variants' => [
            ['id' => null, 'variant_name' => '50ml', 'sku' => 'A-50', 'price_retail' => 10, 'price_sale' => 9, 'max_purchase' => 1],
            ['id' => null, 'variant_name' => '100ml', 'sku' => 'A-100', 'price_retail' => 18, 'price_sale' => 16, 'max_purchase' => 1],
        ],
    ]));

    $product = Product::where('slug', 'hydra-glow-cleanser')->first();
    $keep = $product->variants()->orderBy('id')->first();

    $this->actingAs($admin, 'admin')->put("/admin/products/{$product->id}", productPayload([
        'type' => 'variable',
        'variants' => [
            ['id' => $keep->id, 'variant_name' => '50ml', 'sku' => 'A-50', 'price_retail' => 10, 'price_sale' => 9, 'max_purchase' => 1],
        ],
    ]))->assertSessionHasNoErrors();

    // Historical cart and order rows still point at the removed variant.
    expect($product->variants()->count())->toBe(1)
        ->and(ProductVariant::withTrashed()->where('product_id', $product->id)->count())->toBe(2);
});

it('caps uploads at five images', function () {
    Storage::fake('public');
    $admin = queueAdmin('new-product');

    $this->actingAs($admin, 'admin')->post('/admin/products', productPayload([
        'images' => array_map(fn ($i) => UploadedFile::fake()->image("shot{$i}.jpg"), range(1, 6)),
    ]))->assertSessionHasErrors('images');
});

it('stores uploaded images against the product', function () {
    Storage::fake('public');
    $admin = queueAdmin('new-product');

    $this->actingAs($admin, 'admin')->post('/admin/products', productPayload([
        'images' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.jpg')],
    ]));

    $product = Product::where('slug', 'hydra-glow-cleanser')->first();

    expect($product->images()->count())->toBe(2);
    Storage::disk('public')->assertExists($product->images()->first()->image);
});

it('403s an operator without the product slug', function () {
    $this->actingAs(queueAdmin('dashboard'), 'admin')->get('/admin/new-product')->assertForbidden();
});

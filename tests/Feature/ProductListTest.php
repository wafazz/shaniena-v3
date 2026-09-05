<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists products with their stock and price range', function () {
    $admin = adminWith(['product-list']);

    $product = Product::factory()->named('Hydrating Toner')->create();

    ProductVariant::create([
        'product_id' => $product->id, 'variant_name' => '100ml', 'sku' => 'TN-100',
        'price_retail' => 59.00, 'price_sale' => 0, 'stock' => 12, 'max_purchase' => 5, 'status' => 1,
    ]);
    ProductVariant::create([
        'product_id' => $product->id, 'variant_name' => '250ml', 'sku' => 'TN-250',
        'price_retail' => 99.00, 'price_sale' => 89.00, 'stock' => 3, 'max_purchase' => 5, 'status' => 1,
    ]);

    $this->actingAs($admin, 'admin')
        ->get('/admin/product-list')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Products/Index')
            ->where('products.data.0.name', 'Hydrating Toner')
            ->where('products.data.0.variants', 2)
            ->where('products.data.0.stock', 15)
            // The sale price is the one a customer pays, so it sets the range.
            ->where('products.data.0.price_from', 59)
            ->where('products.data.0.price_to', 89));
});

it('finds a product by its variant SKU', function () {
    $admin = adminWith(['product-list']);

    $wanted = Product::factory()->named('Night Cream')->create();
    ProductVariant::create([
        'product_id' => $wanted->id, 'variant_name' => '50g', 'sku' => 'NC-50-GOLD',
        'price_retail' => 120.00, 'price_sale' => 0, 'stock' => 4, 'max_purchase' => 5, 'status' => 1,
    ]);

    Product::factory()->named('Day Cream')->create();

    // The number on the box is what someone has in front of them.
    $this->actingAs($admin, 'admin')
        ->get('/admin/product-list?search=NC-50')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Night Cream'));
});

it('filters by category, brand and status', function () {
    $admin = adminWith(['product-list']);

    $category = Category::factory()->create(['name' => 'Skincare']);
    $brand = Brand::factory()->create(['name' => 'Rozeyana']);

    Product::factory()->named('In both')->create([
        'category_id' => $category->id, 'brand_id' => $brand->id, 'status' => 1,
    ]);
    Product::factory()->named('Wrong category')->create(['brand_id' => $brand->id, 'status' => 1]);
    Product::factory()->named('Hidden')->create([
        'category_id' => $category->id, 'brand_id' => $brand->id, 'status' => 0,
    ]);

    $this->actingAs($admin, 'admin')
        ->get("/admin/product-list?category={$category->id}&brand={$brand->id}&status=1")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'In both'));

    // status=0 must mean hidden, not "no filter" — a falsy id is a real value.
    $this->actingAs($admin, 'admin')
        ->get('/admin/product-list?status=0')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Hidden'));
});

it('shows a product that has no variants yet', function () {
    $admin = adminWith(['product-list']);

    // Stock Control, which the source used as the product list, lists variants
    // — so a freshly created product was invisible until one was added.
    Product::factory()->named('Just created')->create();

    $this->actingAs($admin, 'admin')
        ->get('/admin/product-list')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.stock', 0)
            ->where('products.data.0.price_from', null));
});

it('pages rather than loading the whole catalogue', function () {
    $admin = adminWith(['product-list']);

    Product::factory()->count(30)->create();

    $this->actingAs($admin, 'admin')
        ->get('/admin/product-list')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 25)
            ->where('products.meta.total', 30)
            ->where('products.meta.last_page', 2));
});

it('keeps the catalogue behind its own grant', function () {
    $admin = adminWith(['stock-control']);

    $this->actingAs($admin, 'admin')->get('/admin/product-list')->assertForbidden();
});

<?php

use App\Models\CountryPrice;
use App\Models\ListCountry;
use App\Models\MemberHq;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RoleAccess;
use App\Models\StockControl;
use App\Services\PageAccess;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * A member of staff holding exactly the page grants named, and nothing else.
 *
 * This lives here rather than in one test file because seven of them had grown
 * their own near-identical copy, and the one that was shared made every file
 * that used it impossible to run on its own.
 */
function adminWith(array $slugs = [], int $role = MemberHq::ROLE_STAFF_ADMIN): MemberHq
{
    $user = MemberHq::create([
        'email' => 'staff'.uniqid().'@example.test',
        'password' => bcrypt('secret'),
        'sec_pin' => '1234',
        'f_name' => 'Test',
        'l_name' => 'Operator',
        'phone' => '0100000000',
        'role' => $role,
        'status' => MemberHq::STATUS_ACTIVE,
    ]);

    foreach ($slugs as $i => $slug) {
        RoleAccess::create([
            'page_url' => $slug,
            'name' => $slug,
            'allowed_user' => '['.$user->id.']',
            'sort' => $i,
        ]);
    }

    // The grant cache is keyed per user and would otherwise answer from a
    // previous test's state.
    app(PageAccess::class)->flushFor($user->id);

    return $user;
}

/**
 * Whether the app is serving built assets rather than Vite's dev server.
 *
 * `npm run dev` writes `public/hot`, and Laravel then emits dev-server URLs
 * for every asset. Tests that assert on the built filenames — which bundle is
 * served, that the console's chunk is absent — are asserting something that is
 * simply not true while a developer has Vite running, and would fail on their
 * machine and nowhere else. They skip instead, and still run in CI and on a
 * deploy, where nothing has written that file.
 */
function servingBuiltAssets(): bool
{
    return ! is_file(public_path('hot')) && is_file(public_path('build/manifest.json'));
}

function shopCountry(): ListCountry
{
    // Not firstOrCreate on an id: `id` is not fillable, so the row would be
    // created with whatever the auto-increment happened to be.
    return ListCountry::firstOrCreate(
        ['name' => 'Malaysia'],
        ['sign' => 'MYR', 'rate' => 1, 'phone_code' => '+60', 'status' => ListCountry::STATUS_ACTIVE],
    );
}

function sellable(string $name = 'Hydra Glow Cleanser', int $stock = 50, float $sale = 59.90, int $cap = 5): array
{
    $country = shopCountry();
    $product = Product::factory()->named($name)->create(['weight' => 500]);
    $variant = ProductVariant::create([
        'product_id' => $product->id, 'variant_name' => '50ml', 'sku' => 'SKU-'.$product->id,
        'price_retail' => 79.90, 'price_sale' => $sale, 'max_purchase' => $cap,
    ]);

    StockControl::create([
        'p_id' => $product->id, 'pv_id' => $variant->id,
        'stock_in' => $stock, 'stock_out' => 0, 'comment' => 'Opening',
    ]);

    CountryPrice::create([
        'product_id' => $product->id, 'country_id' => $country->id,
        'market_price' => 79.90, 'sale_price' => $sale,
    ]);

    return [$product, $variant];
}

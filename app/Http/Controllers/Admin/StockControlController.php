<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockControl;
use App\Services\StoreSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockControlController extends Controller
{
    private const PER_PAGE = 25;

    /**
     * The source hardcoded 101 in two places, so anything at or under 100
     * showed red — permanently, for slow-moving lines. Configurable now,
     * defaulting to the old value so behaviour is unchanged until it's set.
     */
    private const DEFAULT_LOW_STOCK = 101;

    public function index(Request $request, StoreSettings $settings): Response
    {
        $search = trim((string) $request->query('search', ''));

        // Server-side, unlike the source's jQuery DataTables, which rendered
        // every product and every variant into the DOM before paging them.
        $products = Product::query()
            ->with(['variants' => fn ($q) => $q->orderBy('id')])
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$search}%"));
            }))
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $variantIds = $products->getCollection()->flatMap->variants->pluck('id')->all();

        return Inertia::render('Admin/Products/StockControl', [
            'filters' => ['search' => $search],
            'lowStockThreshold' => (int) $settings->get('low_stock_threshold', (string) self::DEFAULT_LOW_STOCK),
            'products' => [
                'data' => $this->present($products->getCollection(), $variantIds),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'total' => $products->total(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ],
            ],
            'can' => [
                'adjustStock' => $request->user('admin')->can('perform', 'button-add-deduct-stock'),
                'deleteProduct' => $request->user('admin')->can('perform', 'button-delete-product'),
            ],
        ]);
    }

    public function adjust(Request $request, ProductVariant $variant): RedirectResponse
    {
        abort_unless($request->user('admin')->can('perform', 'button-add-deduct-stock'), 403);

        $data = $request->validate([
            'type' => ['required', 'in:add,deduct'],
            'quantity' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:255'],
        ]);

        $admin = $request->user('admin');
        $direction = $data['type'] === 'add' ? 'ADDED' : 'DEDUCTED';

        DB::transaction(function () use ($variant, $data, $admin, $direction) {
            // The ledger is append-only — a movement row, never a mutated
            // balance. That part of the source is correct and stays.
            StockControl::create([
                'p_id' => $variant->product_id,
                'pv_id' => $variant->id,
                'stock_in' => $data['type'] === 'add' ? $data['quantity'] : 0,
                'stock_out' => $data['type'] === 'deduct' ? $data['quantity'] : 0,
                'comment' => ($data['comment'] ?? null) ?: "Updated stock ({$direction}) by ({$admin->id} : {$admin->f_name})",
            ]);

            Activity::record(
                (int) $admin->getKey(),
                "{$direction} {$data['quantity']} of variant {$variant->id}",
                "stock_control|{$variant->id}",
                'stock_activity',
            );
        });

        return back()->with('success', "Stock {$direction} for ".($variant->sku ?: "variant {$variant->id}").'.');
    }

    /** @return list<array<string, mixed>> */
    private function present($products, array $variantIds): array
    {
        // Two grouped queries for the whole page, replacing a per-variant
        // balance query inside the view.
        $balances = StockControl::query()
            ->whereIn('pv_id', $variantIds)
            ->groupBy('pv_id')
            ->pluck(DB::raw('SUM(stock_in) - SUM(stock_out)'), 'pv_id');

        $sold = Cart::query()
            ->whereIn('pv_id', $variantIds)
            ->where('status', Cart::STATUS_PAID)
            ->groupBy('pv_id')
            ->pluck(DB::raw('SUM(quantity)'), 'pv_id');

        return $products->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'status' => (bool) $product->status,
            'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'label' => $variant->variant_name ?: 'Default',
                'sku' => $variant->sku,
                'stock' => (int) ($balances[$variant->id] ?? 0),
                'sold' => (int) ($sold[$variant->id] ?? 0),
                'retail' => (float) $variant->price_retail,
                'sale' => (float) $variant->price_sale,
            ])->values()->all(),
        ])->values()->all();
    }
}

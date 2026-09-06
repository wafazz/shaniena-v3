<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Middleware\HandleStorefrontRequests;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Storefront\Basket;
use App\Services\Storefront\Catalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(
        private Basket $basket,
        private Catalogue $catalogue,
    ) {}

    public function show(Request $request): Response
    {
        return Inertia::render('Shop/Cart', [
            'summary' => $this->basket->summary(
                HandleStorefrontRequests::cartToken($request),
                $request->attributes->get('storefront.country'),
            ),
        ]);
    }

    /**
     * The basket as JSON, for the header drawer.
     *
     * The drawer can be opened from any page, and Inertia's shared props are
     * only the count — sending every line on every page load would put the
     * whole basket in the HTML of pages that never show it. Scoped to the cart
     * cookie exactly like the page it mirrors, so it can only ever return the
     * caller's own basket.
     */
    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->basket->summary(
            HandleStorefrontRequests::cartToken($request),
            $request->attributes->get('storefront.country'),
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $country = $request->attributes->get('storefront.country');
        $sessionId = HandleStorefrontRequests::cartToken($request);

        $product = Product::findOrFail($data['product_id']);
        $variant = ProductVariant::where('product_id', $product->id)->findOrFail($data['variant_id']);

        $existing = Cart::query()
            ->where('session_id', $sessionId)
            ->where('pv_id', $variant->id)
            ->whereIn('status', Cart::STATUS_ACTIVE)
            ->first();

        // The cap applies to what ends up in the basket, not to this request
        // alone — otherwise it is bypassed by adding one at a time.
        $wanted = (int) $data['quantity'] + (int) ($existing->quantity ?? 0);
        $cap = max(1, (int) $variant->max_purchase);
        $available = $this->catalogue->stockByVariant([$variant->id])[$variant->id] ?? 0;

        if ($wanted > $cap) {
            throw ValidationException::withMessages([
                'quantity' => "You can order at most {$cap} of this item.",
            ]);
        }

        if ($wanted > $available + (int) ($existing->quantity ?? 0)) {
            throw ValidationException::withMessages([
                'quantity' => $available > 0
                    ? "Only {$available} left in stock."
                    : 'That variant just went out of stock.',
            ]);
        }

        $price = $this->basket->priceFor($product->id, $country);

        if ($existing) {
            $existing->update([
                'quantity' => $wanted,
                'total_weight' => (int) $product->weight * $wanted,
            ]);
        } else {
            Cart::create([
                'session_id' => $sessionId,
                'p_id' => $product->id,
                'pv_id' => $variant->id,
                'quantity' => $wanted,
                'price' => $price?->sale_price ?? 0,
                'weight' => (int) $product->weight,
                'total_weight' => (int) $product->weight * $wanted,
                'currency_sign' => $country?->sign ?? 'MYR',
                'country_id' => $country?->id ?? 0,
                'status' => Cart::STATUS_UNPAID,
            ]);
        }

        return back()->with('success', "{$product->name} added to your cart.");
    }

    public function update(Request $request, Cart $line): RedirectResponse
    {
        $this->authoriseLine($request, $line);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0']]);

        if ($data['quantity'] === 0) {
            $line->delete();

            return back()->with('success', 'Item removed.');
        }

        $cap = max(1, (int) ($line->variant?->max_purchase ?: 99));

        if ($data['quantity'] > $cap) {
            throw ValidationException::withMessages([
                'quantity' => "You can order at most {$cap} of this item.",
            ]);
        }

        $line->update([
            'quantity' => $data['quantity'],
            'total_weight' => (int) $line->weight * $data['quantity'],
        ]);

        return back()->with('success', 'Cart updated.');
    }

    public function destroy(Request $request, Cart $line): RedirectResponse
    {
        $this->authoriseLine($request, $line);
        $line->delete();

        return back()->with('success', 'Item removed.');
    }

    /** A cart line belongs to one session and nobody else may touch it. */
    private function authoriseLine(Request $request, Cart $line): void
    {
        abort_unless($line->session_id === HandleStorefrontRequests::cartToken($request), 403);
    }
}

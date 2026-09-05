<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderQueues;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Find one order across every status.
 *
 * Search-first by design: at 20k+ orders the useful state before a query is
 * the field and what it matches, not a table. The source rendered an empty
 * table plus checkboxes and a "Select All" wired to a bulk bar that does not
 * exist on the page — four null dereferences on load. The checkboxes are gone
 * until bulk actions here are actually wanted (open question Q2).
 */
class OrderSearchController extends Controller
{
    private const PER_PAGE = 30;

    public function __invoke(Request $request): Response
    {
        $term = trim((string) $request->query('search', ''));

        return Inertia::render('Admin/Orders/Search', [
            'term' => $term,
            // From the one place queues are defined, so the export filter can
            // never drift from the slugs the routes actually accept.
            'queues' => collect(OrderQueues::slugs())
                ->map(fn (string $slug) => ['value' => $slug, 'label' => OrderQueues::get($slug)['title']])
                ->all(),
            'results' => $term === '' ? null : fn () => $this->search($term),
        ]);
    }

    /** @return array<string, mixed> */
    private function search(string $term): array
    {
        // An exact order id wins outright. Otherwise a digits-only term also
        // matches any phone or email containing those digits, so typing an
        // order number returned a pile of unrelated orders alongside it.
        $exactId = ctype_digit($term) && Order::whereKey((int) $term)->exists()
            ? (int) $term
            : null;

        $orders = Order::query()
            ->with(['lines' => fn ($q) => $q->active()->with('product:id,name')])
            ->when($exactId, fn ($query) => $query->whereKey($exactId))
            ->unless($exactId, fn ($query) => $query->where(function ($inner) use ($term) {
                $inner
                    ->orWhere('customer_name', 'like', "%{$term}%")
                    ->orWhere('customer_name_last', 'like', "%{$term}%")
                    ->orWhere('customer_phone', 'like', "%{$term}%")
                    ->orWhere('customer_email', 'like', "%{$term}%");
            }))
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return [
            'data' => $orders->getCollection()->map(fn (Order $order) => [
                'id' => $order->id,
                'reference' => $order->reference(),
                'customer' => $order->customerFullName(),
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
                'country' => $order->country,
                'placed_at' => $order->created_at?->format('j M Y, h:iA'),
                'total' => (float) $order->myr_value_include_postage,
                'status' => $order->status,
                'courier_service' => $order->courier_service ?: null,
                'awb_number' => $order->awb_number ?: null,
                'tracking_url' => $order->tracking_url ?: null,
                'items' => $order->lines->sum('quantity'),
            ])->all(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
        ];
    }
}

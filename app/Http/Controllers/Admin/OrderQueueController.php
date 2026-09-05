<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderQueues;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderQueueController extends Controller
{
    public function index(Request $request, string $queue): Response
    {
        abort_unless(OrderQueues::exists($queue), 404);

        $config = OrderQueues::get($queue);

        $filters = [
            'product' => trim((string) $request->query('product', '')),
            'qty' => $request->query('qty') !== null && $request->query('qty') !== ''
                ? (int) $request->query('qty')
                : null,
            'sort' => in_array($request->query('sort'), ['asc', 'desc'], true)
                ? $request->query('sort')
                : null,
        ];

        $hasFilter = $filters['product'] !== '' || $filters['qty'] !== null;

        // An archive opens empty. Paging into 18,000 completed orders is not a
        // thing anyone does; they arrive here looking for something specific.
        $showResults = $config['working'] || $hasFilter;

        return Inertia::render('Admin/Orders/Queue', [
            'queue' => [
                'slug' => $queue,
                'title' => $config['title'],
                'working' => $config['working'],
            ],
            'filters' => $filters,
            'orders' => $showResults
                ? fn () => $this->paginate($config, $filters, $request)
                : null,
            'statuses' => Order::STATUSES,
        ]);
    }

    /** @param  array<string, mixed>  $config */
    private function paginate(array $config, array $filters, Request $request): array
    {
        $query = Order::query()
            // The source ran four raw queries per variant per order inside the
            // view — several hundred on a 100-row page.
            ->with([
                'lines' => fn ($q) => $q->active()->with(['product:id,name,slug', 'variant:id,product_id,variant_name,sku']),
            ])
            ->when($config['status'] !== null, fn (Builder $q) => $q->where('status', $config['status']));

        $this->applyFilters($query, $filters);

        $orders = $query
            ->orderBy(
                $filters['sort'] ? 'total_qty' : 'created_at',
                $filters['sort'] ?: 'desc',
            )
            ->paginate($config['per_page'])
            ->withQueryString();

        return [
            'data' => $orders->getCollection()->map(fn (Order $order) => $this->present($order))->all(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
        ];
    }

    /**
     * Product-name matching now hits orders containing the product on ANY
     * line. The source used `HAVING COUNT(*) = 1 AND MAX(p.name) LIKE ...`,
     * which silently excluded every multi-item order — filtering for a product
     * hid most of the orders that actually contained it.
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['product'] !== '', fn (Builder $q) => $q->whereHas(
                'lines',
                fn ($line) => $line->active()->whereHas(
                    'product',
                    fn ($p) => $p->where('name', 'like', '%'.$filters['product'].'%'),
                ),
            ))
            ->when($filters['qty'] !== null, fn (Builder $q) => $q->where('total_qty', $filters['qty']));
    }

    /** @return array<string, mixed> */
    private function present(Order $order): array
    {
        return [
            'id' => $order->id,
            'reference' => $order->reference(),
            'customer' => $order->customerFullName(),
            'country' => $order->country,
            'placed_at' => $order->created_at?->format('j M Y, h:iA'),
            'total' => (float) $order->myr_value_include_postage,
            'currency' => $order->currency_sign,
            'status' => $order->status,
            'payment_channel' => $order->payment_channel,
            'courier_service' => $order->courier_service ?: null,
            'awb_number' => $order->awb_number ?: null,
            'tracking_url' => $order->tracking_url ?: null,
            'printed_awb' => (bool) $order->printed_awb,
            'transitions' => Order::ALLOWED_TRANSITIONS[$order->status] ?? [],
            'lines' => $order->lines->map(fn ($line) => [
                'id' => $line->id,
                'name' => $line->product?->name ?? 'Product removed',
                'variant' => $line->variant?->variant_name,
                'sku' => $line->variant?->sku,
                'quantity' => $line->quantity,
                'price' => (float) $line->price,
            ])->all(),
        ];
    }
}

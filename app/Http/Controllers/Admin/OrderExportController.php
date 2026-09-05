<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Cart;
use App\Models\Order;
use App\Services\OrderQueues;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Order export.
 *
 * Streamed and chunked: at 20k+ orders, building the whole file in memory
 * first is how an export takes the site down. Every export is written to the
 * activity log — this is a file of customers' names, addresses and phone
 * numbers leaving the building, and somebody should be able to say who took it.
 */
class OrderExportController extends Controller
{
    private const CHUNK = 500;

    private const ORDER_COLUMNS = [
        'Order', 'Placed', 'Status', 'Customer', 'Email', 'Phone',
        'Address 1', 'Address 2', 'Postcode', 'Town', 'State', 'Country',
        'Items', 'Currency', 'Goods', 'Postage', 'Total',
        'Payment', 'Courier', 'AWB', 'Last scan',
    ];

    private const ITEM_COLUMNS = [
        'Order', 'Placed', 'Status', 'Customer', 'Product', 'Variant', 'SKU',
        'Quantity', 'Unit price', 'Line total', 'Currency',
    ];

    public function __invoke(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'queue' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:255'],
            'rows' => ['nullable', Rule::in(['orders', 'items'])],
        ]);

        $byItem = ($data['rows'] ?? 'orders') === 'items';
        $status = $this->statusFor($data['queue'] ?? null);

        $query = Order::query()
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($data['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($data['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when($data['search'] ?? null, fn ($q, $term) => $q->where(function ($inner) use ($term) {
                $inner
                    ->orWhere('customer_name', 'like', "%{$term}%")
                    ->orWhere('customer_name_last', 'like', "%{$term}%")
                    ->orWhere('customer_phone', 'like', "%{$term}%")
                    ->orWhere('customer_email', 'like', "%{$term}%");
            }));

        Activity::record(
            (int) $request->user('admin')->getKey(),
            'Exported orders ('.($byItem ? 'one row per item' : 'one row per order').')'
                .$this->describeScope($data, $status),
            'customer_orders',
            'order_activity',
        );

        $filename = 'orders-'.($data['queue'] ?? 'all').'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query, $byItem) {
            $handle = fopen('php://output', 'w');

            // Excel reads a UTF-8 CSV as Latin-1 without this, which mangles
            // every accented name in the file.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $byItem ? self::ITEM_COLUMNS : self::ORDER_COLUMNS);

            $query
                ->with(['lines' => fn ($q) => $q->active()->with([
                    'product:id,name',
                    'variant:id,product_id,variant_name,sku',
                ])])
                ->orderBy('id')
                ->chunkById(self::CHUNK, function ($orders) use ($handle, $byItem) {
                    foreach ($orders as $order) {
                        foreach ($this->rowsFor($order, $byItem) as $row) {
                            fputcsv($handle, $row);
                        }
                    }

                    flush();
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** @return list<list<string>> */
    private function rowsFor(Order $order, bool $byItem): array
    {
        $status = Order::STATUSES[$order->status] ?? 'Unknown';
        $placed = $order->created_at?->format('Y-m-d H:i') ?? '';

        if (! $byItem) {
            return [[
                $order->reference(), $placed, $status,
                $order->customerFullName(), (string) $order->customer_email,
                // Prefixed so a spreadsheet keeps the leading zero and does
                // not turn a phone number into scientific notation.
                $this->text((string) $order->customer_phone),
                (string) $order->address_1, (string) $order->address_2,
                $this->text((string) $order->postcode),
                (string) $order->city, (string) $order->state, (string) $order->country,
                (string) $order->total_qty, (string) $order->currency_sign,
                number_format((float) $order->total_price, 2, '.', ''),
                number_format((float) $order->postage_cost, 2, '.', ''),
                number_format((float) $order->myr_value_include_postage, 2, '.', ''),
                (string) $order->payment_channel,
                (string) $order->courier_service,
                $this->text((string) $order->awb_number),
                (string) $order->tracking_milestone,
            ]];
        }

        return $order->lines->map(fn (Cart $line) => [
            $order->reference(), $placed, $status, $order->customerFullName(),
            $line->product?->name ?? 'Product removed',
            (string) $line->variant?->variant_name,
            (string) $line->variant?->sku,
            (string) $line->quantity,
            number_format((float) $line->price, 2, '.', ''),
            number_format($line->lineTotal(), 2, '.', ''),
            (string) $order->currency_sign,
        ])->all();
    }

    /** Keeps a spreadsheet from reformatting an identifier as a number. */
    private function text(string $value): string
    {
        return $value === '' ? '' : "\t".$value;
    }

    private function statusFor(?string $queue): ?int
    {
        if (blank($queue) || ! OrderQueues::exists($queue)) {
            return null;
        }

        return OrderQueues::get($queue)['status'];
    }

    /** @param  array<string, mixed>  $data */
    private function describeScope(array $data, ?int $status): string
    {
        $parts = [];

        if ($status !== null) {
            $parts[] = Order::STATUSES[$status] ?? 'status '.$status;
        }

        if (filled($data['from'] ?? null) || filled($data['to'] ?? null)) {
            $parts[] = ($data['from'] ?? 'the beginning').' to '.($data['to'] ?? 'today');
        }

        if (filled($data['search'] ?? null)) {
            $parts[] = 'matching "'.$data['search'].'"';
        }

        return $parts === [] ? ' — everything' : ' — '.implode(', ', $parts);
    }
}

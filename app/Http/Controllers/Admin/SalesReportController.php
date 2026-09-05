<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Revenue over a date range, broken down by day, country and courier.
 *
 * Filter state lives in the query string, so a filtered report is a shareable
 * link and the browser Back button behaves.
 */
class SalesReportController extends Controller
{
    private const EARNING = [
        Order::STATUS_NEW,
        Order::STATUS_PROCESSING,
        Order::STATUS_IN_DELIVERY,
        Order::STATUS_COMPLETED,
    ];

    public function __invoke(Request $request): Response
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($request->query('from', Carbon::today()->subDays(29)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', Carbon::today()->toDateString()))->endOfDay();

        $base = fn () => Order::query()->whereIn('status', self::EARNING)->whereBetween('created_at', [$from, $to]);

        $orders = (clone $base())->count();
        $revenue = (float) (clone $base())->sum('myr_value_include_postage');

        return Inertia::render('Admin/Reports/Sales', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'orders' => $orders,
                'revenue' => round($revenue, 2),
                'average' => $orders ? round($revenue / $orders, 2) : 0.0,
                'postage' => round((float) (clone $base())->sum('postage_cost'), 2),
            ],
            'daily' => (clone $base())
                ->groupBy('day')
                ->orderBy('day')
                ->get([
                    DB::raw('DATE(created_at) as day'),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(myr_value_include_postage) as revenue'),
                ])
                ->map(fn ($row) => [
                    'day' => $row->day,
                    'label' => Carbon::parse($row->day)->format('j M'),
                    'orders' => (int) $row->orders,
                    'revenue' => round((float) $row->revenue, 2),
                ])->all(),
            'byCountry' => (clone $base())
                ->groupBy('country')
                ->orderByDesc('revenue')
                ->get(['country', DB::raw('COUNT(*) as orders'), DB::raw('SUM(myr_value_include_postage) as revenue')])
                ->map(fn ($row) => [
                    'country' => $row->country ?: 'Unknown',
                    'orders' => (int) $row->orders,
                    'revenue' => round((float) $row->revenue, 2),
                ])->all(),
            'byCourier' => (clone $base())
                ->groupBy('courier_service')
                ->orderByDesc('orders')
                ->get(['courier_service', DB::raw('COUNT(*) as orders'), DB::raw('SUM(myr_value_include_postage) as revenue')])
                ->map(fn ($row) => [
                    'courier' => $row->courier_service ?: 'Not assigned',
                    'orders' => (int) $row->orders,
                    'revenue' => round((float) $row->revenue, 2),
                ])->all(),
        ]);
    }
}

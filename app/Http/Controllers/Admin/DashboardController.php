<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Order;
use App\Services\DashboardMetrics;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(DashboardMetrics $metrics): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'metrics' => fn () => $metrics->all(),
            'latestOrders' => fn () => Order::query()
                ->orderByDesc('created_at')
                ->limit(6)
                ->get()
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'reference' => $order->reference(),
                    'customer' => $order->customerFullName(),
                    'country' => $order->country,
                    'total' => (float) $order->myr_value_include_postage,
                    'status' => $order->status,
                    'placed_at' => $order->created_at?->format('j M Y, h:iA'),
                ])->all(),
            'activity' => fn () => Activity::query()
                ->with('user:id,f_name,l_name')
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (Activity $row) => [
                    'id' => $row->id,
                    'description' => $row->description,
                    'actor' => $row->user
                        ? trim("{$row->user->f_name} {$row->user->l_name}")
                        : 'Unknown staff',
                    'at' => $row->created_at?->format('j M Y, h:iA'),
                ])->all(),
        ]);
    }
}

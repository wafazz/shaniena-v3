<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ShipmentFailed;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Shipping\BookShipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function __construct(private BookShipment $bookShipment) {}

    public function store(Request $request, Order $order): RedirectResponse
    {
        try {
            $message = $this->bookShipment->handle($order, (int) $request->user('admin')->getKey());
        } catch (ShipmentFailed $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }

    /**
     * Book several at once. Each order is booked on its own, so one courier
     * refusal does not take the rest of the batch down with it — and no AWB
     * can end up on the wrong parcel.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*' => ['integer'],
        ]);

        $actorId = (int) $request->user('admin')->getKey();
        $booked = 0;
        $failures = [];

        foreach (Order::query()->whereIn('id', $data['orders'])->get() as $order) {
            try {
                $this->bookShipment->handle($order, $actorId);
                $booked++;
            } catch (ShipmentFailed $e) {
                $failures[] = $e->getMessage();
            }
        }

        if ($booked === 0) {
            return back()->with('error', $failures[0] ?? 'Nothing could be booked.');
        }

        $message = $booked.' order'.($booked === 1 ? '' : 's').' sent to courier.';

        if ($failures !== []) {
            $message .= ' '.count($failures).' could not be booked: '.$failures[0];
        }

        return back()->with('success', $message);
    }
}

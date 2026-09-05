<?php

namespace App\Services\Shipping;

use App\Exceptions\ShipmentFailed;
use App\Models\Activity;
use App\Models\Order;
use App\Services\AdminNavigation;
use Illuminate\Support\Facades\DB;

/**
 * Books one order with its chosen courier and records the result.
 *
 * Idempotent: an order that already has an AWB is left alone rather than
 * booked twice, which in the source meant two parcels for one order.
 */
class BookShipment
{
    public function __construct(private Couriers $couriers) {}

    public function handle(Order $order, int $actorId): string
    {
        if (filled($order->awb_number)) {
            return "Order {$order->reference()} already has AWB {$order->awb_number}.";
        }

        $courier = $this->couriers->for($order->courier_service);

        if (! $courier) {
            throw new ShipmentFailed("Order {$order->reference()} has no courier service assigned.");
        }

        if (! $courier->isConfigured()) {
            throw new ShipmentFailed("{$courier->name()} is not configured yet.");
        }

        $consignment = $courier->book($order);

        DB::transaction(function () use ($order, $consignment, $actorId) {
            $order->forceFill([
                'awb_number' => $consignment->awb,
                'tracking_url' => $consignment->trackingUrl,
                'courier_service' => $consignment->courier,
                'ship_channel' => 'Doorstep Delivery',
                'status' => Order::STATUS_PROCESSING,
            ])->save();

            Activity::record(
                $actorId,
                "Booked {$consignment->courier} for order {$order->reference()} — AWB {$consignment->awb}",
                "customer_orders|{$order->id}",
                'shipping_activity',
            );
        });

        app(AdminNavigation::class)->flushCounts();

        return "Order {$order->reference()} booked with {$consignment->courier}. AWB {$consignment->awb}.";
    }
}

<?php

namespace App\Contracts;

use App\Models\Order;

/**
 * One courier.
 *
 * Every gateway books exactly one order at a time and returns the consignment
 * it was given. The source booked in batches and then matched AWBs back to
 * orders by array position — if the courier returned items in a different
 * order, AWBs landed on the wrong parcels.
 */
interface ShippingGateway
{
    /** The value stored in customer_orders.courier_service. */
    public function name(): string;

    /** Whether credentials exist for the current mode. */
    public function isConfigured(): bool;

    /** Book one order. Throws ShipmentFailed if the courier refuses. */
    public function book(Order $order): Consignment;
}

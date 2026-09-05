<?php

namespace App\Contracts;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * One payment channel.
 *
 * Every gateway is handed an order that already exists and an amount the
 * server computed. None of them accept an amount from the caller.
 */
interface PaymentGateway
{
    /** The value stored in customer_orders.payment_channel. */
    public function channel(): string;

    /** Whether the store has this channel switched on and configured. */
    public function isAvailable(): bool;

    /**
     * Begin payment. Returns where to send the customer next — an external
     * gateway URL, or an internal route for a channel that takes no redirect.
     */
    public function start(Order $order, float $amount): PaymentHandoff;

    /**
     * Verify an inbound callback or return and say what it means.
     *
     * MUST verify the gateway's signature before trusting anything in the
     * request, and MUST NOT trust an amount or status supplied by the browser.
     */
    public function verify(Request $request): PaymentResult;
}

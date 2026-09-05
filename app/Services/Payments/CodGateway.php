<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Contracts\PaymentHandoff;
use App\Contracts\PaymentResult;
use App\Models\Order;
use App\Services\StoreSettings;
use Illuminate\Http\Request;

/**
 * Cash on delivery.
 *
 * No external gateway: the order is placed live and the money is collected by
 * the courier. The COD fee itself is computed by Basket, from cod_charges,
 * never in the browser — the source revealed the fee row with JavaScript and
 * rewrote the total client-side.
 */
class CodGateway implements PaymentGateway
{
    public function __construct(private StoreSettings $settings) {}

    public function channel(): string
    {
        return Order::CHANNEL_COD;
    }

    public function isAvailable(): bool
    {
        return $this->settings->enabled('cod_enabled');
    }

    public function start(Order $order, float $amount): PaymentHandoff
    {
        // Nothing to redirect to: the order is already live.
        return PaymentHandoff::redirect(route('shop.order.thanks', ['order' => $order->id]));
    }

    public function verify(Request $request): PaymentResult
    {
        return PaymentResult::untrusted('Cash on delivery has no callback.');
    }
}

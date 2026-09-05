<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Middleware\HandleStorefrontRequests;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Services\Payments\PaymentGateways;
use App\Services\Storefront\PlaceOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentGateways $gateways,
        private PlaceOrder $placeOrder,
    ) {}

    /**
     * Create the order and hand off to the chosen channel.
     *
     * The amount comes from PlaceOrder, which prices the basket from the
     * catalogue. Nothing about the total is read from the request.
     */
    public function start(Request $request, string $channel): RedirectResponse|Response
    {
        $gateway = $this->gateways->get($channel);

        abort_if(! $gateway || ! $gateway->isAvailable(), 404);

        $country = $request->attributes->get('storefront.country');
        abort_unless($country, 400);

        try {
            $draft = $this->placeOrder->draft(
                HandleStorefrontRequests::cartToken($request),
                $country,
                $channel,
            );
        } catch (RuntimeException $e) {
            return redirect()->route('shop.checkout')->with('error', $e->getMessage());
        }

        /** @var Order $order */
        $order = $draft['order'];

        // Cash on delivery is already a live order.
        if ($channel === Order::CHANNEL_COD) {
            $this->sendConfirmation($order);

            return redirect()
                ->route('shop.order.thanks', ['order' => $order->id])
                ->withCookie(cookie(
                    HandleStorefrontRequests::CART_COOKIE,
                    $this->placeOrder->newCartToken(),
                    60 * 24 * 30,
                ));
        }

        $handoff = $gateway->start($order, $draft['amount']);

        // A gateway that wants a POST gets an auto-submitting form; anything
        // else is a plain redirect.
        return $handoff->method === 'POST'
            ? Inertia::render('Shop/Handoff', [
                'url' => $handoff->url,
                'fields' => $handoff->fields,
                'gateway' => $channel,
            ])
            : redirect()->away($handoff->url);
    }

    /**
     * Server-to-server callback. This is the only thing that confirms an
     * order — the browser return below never does.
     */
    public function callback(Request $request, string $channel): HttpResponse
    {
        $gateway = $this->gateways->get($channel);

        if (! $gateway) {
            return response('unknown channel', 404);
        }

        $result = $gateway->verify($request);

        if (! $result->verified) {
            Log::warning('Rejected an unverified payment callback.', [
                'channel' => $channel,
                'ip' => $request->ip(),
            ]);

            return response('invalid signature', 400);
        }

        $order = Order::find((int) $result->reference);

        if (! $order) {
            return response('unknown order', 404);
        }

        if ($result->paid) {
            // confirm() is idempotent, so a repeated callback is harmless.
            if ($this->placeOrder->confirm($order, $result->paymentCode)) {
                $this->sendConfirmation($order);
            }
        } else {
            $this->placeOrder->fail($order);
        }

        return response('OK');
    }

    /**
     * Where the gateway sends the customer's browser back to.
     *
     * Deliberately does NOT confirm anything: a browser return can be forged
     * or simply never arrive. It reports what the order already says.
     */
    public function return(Request $request, string $channel): RedirectResponse
    {
        $gateway = $this->gateways->get($channel);
        abort_if(! $gateway, 404);

        $result = $gateway->verify($request);
        $order = $result->reference ? Order::find((int) $result->reference) : null;

        if (! $order) {
            return redirect()->route('shop.checkout')->with('error', 'We could not match that payment to an order.');
        }

        $paid = (int) $order->status !== Order::STATUS_AWAITING_PAYMENT;

        return redirect()
            ->route($paid ? 'shop.order.thanks' : 'shop.order.failed', ['order' => $order->id])
            ->withCookie($paid
                ? cookie(HandleStorefrontRequests::CART_COOKIE, $this->placeOrder->newCartToken(), 60 * 24 * 30)
                : cookie(HandleStorefrontRequests::CART_COOKIE, HandleStorefrontRequests::cartToken($request), 60 * 24 * 30));
    }

    public function thanks(Order $order): Response
    {
        return Inertia::render('Shop/Thanks', [
            'order' => $this->present($order),
        ]);
    }

    public function failed(Order $order): Response
    {
        return Inertia::render('Shop/PaymentFailed', [
            'order' => $this->present($order),
        ]);
    }

    /** @return array<string, mixed> */
    private function present(Order $order): array
    {
        return [
            'reference' => $order->reference(),
            'status' => $order->statusLabel(),
            'currency' => $order->currency_sign,
            'total' => number_format((float) $order->myr_value_include_postage, 2),
            'email' => $order->customer_email,
            'channel' => $order->payment_channel,
            'cod' => $order->payment_channel === Order::CHANNEL_COD,
        ];
    }

    private function sendConfirmation(Order $order): void
    {
        if (! $order->customer_email) {
            return;
        }

        // Queued: a slow mail server must never hold up a payment callback.
        Mail::to($order->customer_email)->queue(new OrderPlaced($order));
    }
}

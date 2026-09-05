<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Order confirmation.
 *
 * Replaces the source's PHPMailer call with hardcoded Brevo SMTP credentials
 * sitting in the checkout controller. Credentials live in .env now.
 */
class OrderPlaced extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Order {$this->order->reference()} confirmed");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.order-placed',
            with: [
                'reference' => $this->order->reference(),
                'name' => $this->order->customer_name,
                'currency' => $this->order->currency_sign,
                'total' => number_format((float) $this->order->myr_value_include_postage, 2),
                'postage' => number_format((float) $this->order->postage_cost, 2),
                'lines' => $this->order->lines()->with('product:id,name')->get()->map(fn ($line) => [
                    'name' => $line->product?->name ?? 'Product removed',
                    'quantity' => (int) $line->quantity,
                ])->all(),
                'trackUrl' => route('shop.track', [
                    'order' => $this->order->id,
                    'email' => $this->order->customer_email,
                ]),
            ],
        );
    }
}

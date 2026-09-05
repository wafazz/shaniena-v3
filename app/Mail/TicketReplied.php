<?php

namespace App\Mail;

use App\Models\CsTicketReply;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a customer that support has answered their ticket.
 *
 * The source's admin reply screen only wrote the row — nothing ever left the
 * building, so "Reply sent." was not true and customers were never told.
 */
class TicketReplied extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupportTicket $ticket, public CsTicketReply $reply) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Re: [{$this->ticket->ticket_no}] {$this->ticket->title}",
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.ticket-replied',
            with: [
                'name' => $this->ticket->customer_name,
                'ticketNo' => $this->ticket->ticket_no,
                'title' => $this->ticket->title,
                'message' => $this->reply->message,
                // Ticket number only — the address it was raised with never goes
                // into a URL that ends up in browser history and referrer logs.
                'supportUrl' => route('shop.support', ['ticket_no' => $this->ticket->ticket_no]),
            ],
        );
    }
}

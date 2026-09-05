<?php

use App\Mail\TicketReplied;
use App\Models\CsTicketReply;
use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function ticketFor(string $email = 'buyer@example.test'): SupportTicket
{
    return SupportTicket::create([
        'ticket_no' => 'T-'.strtoupper(substr(uniqid(), -8)),
        'customer_name' => 'Aina',
        'customer_email' => $email,
        'title' => 'Parcel has not arrived',
        'description' => 'It has been nine days.',
        'status' => SupportTicket::STATUS_NEW,
        'priority' => 'normal',
    ]);
}

it('emails the customer when support replies', function () {
    Mail::fake();

    $admin = adminWith(['support/tickets']);
    $ticket = ticketFor();

    $this->actingAs($admin, 'admin')
        ->post("/admin/support/tickets/{$ticket->id}/reply", [
            'message' => 'Sorry about that — it is out for delivery today.',
            'status' => SupportTicket::STATUS_IN_PROGRESS,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    Mail::assertQueued(TicketReplied::class, function (TicketReplied $mail) use ($ticket) {
        return $mail->hasTo($ticket->customer_email)
            && $mail->ticket->is($ticket)
            && str_contains($mail->reply->message, 'out for delivery');
    });

    expect($ticket->fresh()->status)->toBe(SupportTicket::STATUS_IN_PROGRESS)
        ->and($ticket->replies()->count())->toBe(1);
});

it('queues the mail rather than sending it inline', function () {
    Mail::fake();

    $admin = adminWith(['support/tickets']);
    $ticket = ticketFor();

    $this->actingAs($admin, 'admin')
        ->post("/admin/support/tickets/{$ticket->id}/reply", ['message' => 'Looking into it.']);

    // A slow SMTP host must never hold up the agent's screen.
    Mail::assertNothingSent();
    Mail::assertQueued(TicketReplied::class);
});

it('still saves the reply when the ticket has no email address', function () {
    Mail::fake();

    $admin = adminWith(['support/tickets']);
    $ticket = ticketFor('');

    $response = $this->actingAs($admin, 'admin')
        ->post("/admin/support/tickets/{$ticket->id}/reply", ['message' => 'Noted internally.']);

    $response->assertRedirect();

    Mail::assertNothingQueued();

    expect($ticket->replies()->count())->toBe(1)
        // And the flash must not claim a reply was sent to nobody.
        ->and(session('success'))->toContain('no email address');
});

it('renders the reply email without leaking the address into the link', function () {
    $ticket = ticketFor();
    $reply = $ticket->replies()->create([
        'user_type' => CsTicketReply::FROM_STAFF,
        'user_id' => 1,
        'message' => 'Your refund is on its way.',
    ]);

    $rendered = (new TicketReplied($ticket, $reply))->render();

    expect($rendered)->toContain('Your refund is on its way.')
        ->and($rendered)->toContain($ticket->ticket_no)
        ->and($rendered)->not->toContain($ticket->customer_email);
});

it('prefills the ticket number from an emailed link without searching', function () {
    $ticket = ticketFor();

    $this->get('/support?ticket_no='.$ticket->ticket_no)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('prefill.ticket_no', $ticket->ticket_no)
            // No email, so nothing is looked up — and no validation error.
            ->where('searched', false)
            ->where('ticket', null));
});

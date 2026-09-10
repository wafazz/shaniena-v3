<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CsCustomer;
use App\Models\CsTicketReply;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer-side support tickets.
 *
 * A ticket is looked up by its number AND the email it was raised with, so a
 * ticket number on its own does not expose someone else's conversation.
 */
class SupportController extends Controller
{
    public function show(Request $request): Response
    {
        $ticket = null;

        // Both halves, or it is not a lookup. A ticket number on its own is a
        // link from an email: prefill it and let them supply the address.
        $searched = $request->filled('ticket_no') && $request->filled('email');

        if ($searched) {
            $data = $request->validate([
                'ticket_no' => ['required', 'string', 'max:30'],
                'email' => ['required', 'email'],
            ]);

            $ticket = SupportTicket::query()
                ->where('ticket_no', $data['ticket_no'])
                ->where('customer_email', $data['email'])
                ->with(['replies' => fn ($q) => $q->orderBy('id')])
                ->first();
        }

        return Inertia::render('Shop/Support', [
            'searched' => $searched,
            // Both halves, not just the number. The reply form and the thread
            // poll each need the email that was looked up with, and neither
            // has a session to recover it from — so arriving by the link in a
            // notification left the address blank and both of them broken.
            // It is the visitor's own input echoed back, not a disclosure.
            'prefill' => [
                'ticket_no' => (string) $request->query('ticket_no', ''),
                'email' => (string) $request->query('email', ''),
            ],
            'member' => $request->user('web')?->only(['name', 'email']),
            'ticket' => $ticket ? $this->ticketPayload($ticket) : null,
        ]);
    }

    /**
     * The open ticket, for the page to poll while the customer is reading it.
     *
     * Same two-part lookup as show(), deliberately: this is an unauthenticated
     * endpoint, and a ticket number on its own must not open someone else's
     * conversation here any more than it does there.
     */
    public function thread(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ticket_no' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email'],
        ]);

        $ticket = SupportTicket::query()
            ->where('ticket_no', $data['ticket_no'])
            ->where('customer_email', $data['email'])
            ->with(['replies' => fn ($q) => $q->orderBy('id')])
            ->first();

        // 404 rather than an empty 200: a wrong pair is not a ticket with no
        // messages, and saying so plainly keeps the poller from rendering an
        // emptied thread over one the customer is reading.
        abort_if(! $ticket, 404);

        return response()->json($this->ticketPayload($ticket));
    }

    /**
     * One shape for both the rendered page and the polled update. Built in two
     * places, these drift: a field added to the render would simply stop
     * existing the moment the first poll replaced it.
     *
     * @return array<string, mixed>
     */
    private function ticketPayload(SupportTicket $ticket): array
    {
        return [
            'ticket_no' => $ticket->ticket_no,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'status' => str_replace('_', ' ', (string) $ticket->status),
            'closed' => $ticket->isClosed(),
            'opened_at' => $ticket->created_at?->format('j M Y'),
            'replies' => $ticket->replies->map(fn (CsTicketReply $r) => [
                'id' => $r->id,
                'message' => $r->message,
                'from_staff' => $r->fromStaff(),
                'at' => $r->created_at?->format('j M Y, h:iA'),
            ])->all(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'order_id' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in([
                SupportTicket::PRIORITY_LOW,
                SupportTicket::PRIORITY_MEDIUM,
                SupportTicket::PRIORITY_HIGH,
            ])],
        ]);

        $ticket = DB::transaction(function () use ($data) {
            $customer = CsCustomer::firstOrCreate(
                ['email' => $data['customer_email']],
                ['name' => $data['customer_name']],
            );

            return SupportTicket::create($data + [
                'ticket_no' => 'T-'.Str::upper(Str::random(8)),
                'customer_id' => $customer->id,
                'status' => SupportTicket::STATUS_NEW,
            ]);
        });

        return back()->with('success', "Ticket {$ticket->ticket_no} opened. Keep the number — you'll need it and your email to check back.");
    }

    public function reply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ticket_no' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = SupportTicket::query()
            ->where('ticket_no', $data['ticket_no'])
            ->where('customer_email', $data['email'])
            ->firstOrFail();

        abort_if($ticket->isClosed(), 422, 'This ticket is closed. Please open a new one.');

        $ticket->replies()->create([
            'user_type' => CsTicketReply::FROM_CUSTOMER,
            'user_id' => $ticket->customer_id,
            'message' => $data['message'],
        ]);

        // Back to the queue: the customer has said something new.
        $ticket->update(['status' => SupportTicket::STATUS_IN_PROGRESS]);

        return back()->with('success', 'Reply sent.');
    }
}

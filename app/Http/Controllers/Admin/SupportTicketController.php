<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TicketReplied;
use App\Models\CsTicketReply;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $showClosed = $request->boolean('closed');

        return Inertia::render('Admin/Support/Tickets', [
            'filters' => ['closed' => $showClosed],
            'tickets' => SupportTicket::query()
                ->unless($showClosed, fn ($q) => $q->open())
                ->byPriority()
                ->paginate(30)
                ->withQueryString()
                ->through(fn (SupportTicket $t) => [
                    'id' => $t->id,
                    'ticket_no' => $t->ticket_no,
                    'title' => $t->title,
                    'customer' => $t->customer_name,
                    'email' => $t->customer_email,
                    'order_id' => $t->order_id,
                    'status' => $t->status,
                    'priority' => $t->priority,
                    'opened_at' => $t->created_at?->format('j M Y, h:iA'),
                ]),
            'statuses' => [
                SupportTicket::STATUS_NEW, SupportTicket::STATUS_IN_PROGRESS,
                SupportTicket::STATUS_WAITING_CUSTOMER, SupportTicket::STATUS_RESOLVED,
                SupportTicket::STATUS_CLOSED,
            ],
        ]);
    }

    public function show(SupportTicket $ticket): Response
    {
        return Inertia::render('Admin/Support/Ticket', [
            'ticket' => [
                'id' => $ticket->id,
                'ticket_no' => $ticket->ticket_no,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'customer' => $ticket->customer_name,
                'email' => $ticket->customer_email,
                'order_id' => $ticket->order_id,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'opened_at' => $ticket->created_at?->format('j M Y, h:iA'),
            ],
            'replies' => $ticket->replies()->orderBy('id')->get()->map(fn (CsTicketReply $r) => [
                'id' => $r->id,
                'message' => $r->message,
                'from_staff' => $r->fromStaff(),
                'at' => $r->created_at?->format('j M Y, h:iA'),
            ])->all(),
            'attachments' => $ticket->attachments()->get(['id', 'filename', 'file_path'])->all(),
            'statuses' => [
                SupportTicket::STATUS_NEW, SupportTicket::STATUS_IN_PROGRESS,
                SupportTicket::STATUS_WAITING_CUSTOMER, SupportTicket::STATUS_RESOLVED,
                SupportTicket::STATUS_CLOSED,
            ],
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'status' => ['nullable', Rule::in([
                SupportTicket::STATUS_NEW, SupportTicket::STATUS_IN_PROGRESS,
                SupportTicket::STATUS_WAITING_CUSTOMER, SupportTicket::STATUS_RESOLVED,
                SupportTicket::STATUS_CLOSED,
            ])],
        ]);

        $admin = $request->user('admin');

        $reply = DB::transaction(function () use ($ticket, $data, $admin) {
            $reply = $ticket->replies()->create([
                'user_type' => CsTicketReply::FROM_STAFF,
                'user_id' => (int) $admin->getKey(),
                'message' => $data['message'],
            ]);

            if (! empty($data['status']) && $data['status'] !== $ticket->status) {
                $ticket->logs()->create([
                    'action' => 'status',
                    'action_by' => (int) $admin->getKey(),
                    'previous_value' => $ticket->status,
                    'new_value' => $data['status'],
                ]);

                $ticket->update(['status' => $data['status']]);
            }

            return $reply;
        });

        // Queued, and only once the reply is committed — a worker must never
        // pick up a job for a row that is still inside an open transaction.
        if (filled($ticket->customer_email)) {
            Mail::to($ticket->customer_email)->queue(new TicketReplied($ticket, $reply));
        }

        return back()->with(
            'success',
            filled($ticket->customer_email)
                ? 'Reply sent to '.$ticket->customer_email.'.'
                : 'Reply saved. This ticket has no email address to send it to.',
        );
    }
}

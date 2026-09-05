<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CsTicketAttachment;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a ticket attachment to staff who are allowed to see it.
 *
 * The screen used to hand the browser the stored `file_path` and link straight
 * at it. Two things were wrong with that: the file itself had no authorization
 * — anyone who guessed the URL could read a customer's upload — and the path
 * is imported data going into an href, so a row reading `javascript:...` would
 * have been a clickable script. The browser now only ever sees an id.
 */
class SupportAttachmentController extends Controller
{
    /** Attachments live outside the web root; imported ones may not yet. */
    private const DISKS = ['local', 'public'];

    public function __invoke(SupportTicket $ticket, CsTicketAttachment $attachment): StreamedResponse
    {
        // Belongs to this ticket, or it does not exist as far as this route is
        // concerned — otherwise any ticket id would serve any attachment.
        abort_unless((int) $attachment->ticket_id === (int) $ticket->id, 404);

        $path = ltrim((string) $attachment->file_path, '/');

        // No traversal out of the attachment root, whatever the row says.
        abort_if($path === '' || str_contains($path, '..'), 404);

        foreach (self::DISKS as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->download($path, $this->filename($attachment));
            }
        }

        abort(404);
    }

    /** A name safe to put in a Content-Disposition header. */
    private function filename(CsTicketAttachment $attachment): string
    {
        $name = trim(basename((string) $attachment->filename));

        return $name !== '' ? $name : 'attachment-'.$attachment->id;
    }
}

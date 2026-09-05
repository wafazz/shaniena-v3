<?php

use App\Models\CsTicketAttachment;
use App\Models\MemberHq;
use App\Models\RoleAccess;
use App\Models\SupportTicket;
use App\Services\PageAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function csAdmin(string ...$slugs): MemberHq
{
    $user = MemberHq::create([
        'email' => 'a'.uniqid().'@example.test',
        'password' => bcrypt('secret'),
        'sec_pin' => '1234',
        'f_name' => 'Attach',
        'l_name' => 'Operator',
        'phone' => '0100000000',
        'role' => MemberHq::ROLE_STAFF_ADMIN,
        'status' => MemberHq::STATUS_ACTIVE,
    ]);

    foreach ($slugs as $i => $slug) {
        RoleAccess::create([
            'page_url' => $slug, 'name' => $slug,
            'allowed_user' => '['.$user->id.']', 'sort' => $i,
        ]);
    }

    app(PageAccess::class)->flushFor($user->id);

    return $user;
}

function ticketWithFile(string $path = 'tickets/receipt.pdf'): array
{
    $ticket = SupportTicket::create([
        'ticket_no' => 'T-'.strtoupper(substr(uniqid(), -8)),
        'customer_name' => 'Aina',
        'customer_email' => 'aina@example.test',
        'title' => 'Wrong item',
        'description' => 'Received the wrong shade.',
        'status' => SupportTicket::STATUS_NEW,
        'priority' => 'normal',
    ]);

    $attachment = CsTicketAttachment::create([
        'ticket_id' => $ticket->id,
        'filename' => 'receipt.pdf',
        'file_path' => $path,
        'file_type' => 'application/pdf',
        'uploaded_by' => CsTicketAttachment::FROM_CUSTOMER,
    ]);

    return [$ticket, $attachment];
}

it('never sends the stored file path to the browser', function () {
    Storage::fake('local');

    $admin = csAdmin('support/tickets');
    [$ticket] = ticketWithFile();

    // The path is imported data. It went straight into an href before, so a
    // row reading `javascript:...` would have been a clickable script.
    $this->actingAs($admin, 'admin')
        ->get("/admin/support/tickets/{$ticket->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('attachments.0.filename', 'receipt.pdf')
            ->missing('attachments.0.file_path')
            ->where('attachments.0.url', fn ($url) => str_contains($url, '/attachments/')));
});

it('serves an attachment to staff who may see the ticket', function () {
    Storage::fake('local');
    Storage::disk('local')->put('tickets/receipt.pdf', '%PDF-1.4 receipt');

    $admin = csAdmin('support/tickets');
    [$ticket, $attachment] = ticketWithFile();

    $this->actingAs($admin, 'admin')
        ->get("/admin/support/tickets/{$ticket->id}/attachments/{$attachment->id}")
        ->assertOk()
        ->assertDownload('receipt.pdf');
});

it('keeps attachments away from staff without the support grant', function () {
    Storage::fake('local');
    Storage::disk('local')->put('tickets/receipt.pdf', '%PDF-1.4 receipt');

    $admin = csAdmin('stock-control');
    [$ticket, $attachment] = ticketWithFile();

    // The file itself had no authorization at all before: anyone who guessed
    // the storage URL could read a customer's upload.
    $this->actingAs($admin, 'admin')
        ->get("/admin/support/tickets/{$ticket->id}/attachments/{$attachment->id}")
        ->assertForbidden();
});

it('will not serve one ticket a different ticket attachment', function () {
    Storage::fake('local');
    Storage::disk('local')->put('tickets/receipt.pdf', '%PDF');

    $admin = csAdmin('support/tickets');
    [, $attachment] = ticketWithFile();
    [$other] = ticketWithFile('tickets/other.pdf');

    $this->actingAs($admin, 'admin')
        ->get("/admin/support/tickets/{$other->id}/attachments/{$attachment->id}")
        ->assertNotFound();
});

it('refuses a path that tries to climb out of the attachment root', function () {
    Storage::fake('local');

    $admin = csAdmin('support/tickets');
    [$ticket, $attachment] = ticketWithFile('../../../.env');

    $this->actingAs($admin, 'admin')
        ->get("/admin/support/tickets/{$ticket->id}/attachments/{$attachment->id}")
        ->assertNotFound();
});

it('404s rather than erroring when the file is gone', function () {
    Storage::fake('local');

    $admin = csAdmin('support/tickets');
    [$ticket, $attachment] = ticketWithFile();

    $this->actingAs($admin, 'admin')
        ->get("/admin/support/tickets/{$ticket->id}/attachments/{$attachment->id}")
        ->assertNotFound();
});

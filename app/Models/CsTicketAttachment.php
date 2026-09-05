<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** File attached to the ticket itself (not to a reply). */
class CsTicketAttachment extends Model
{
    protected $table = 'cs_ticket_attachments';

    public const UPDATED_AT = null;

    public const FROM_CUSTOMER = 'customer';

    public const FROM_STAFF = 'staff';

    protected $fillable = ['ticket_id', 'filename', 'file_path', 'file_type', 'uploaded_by'];

    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }
}

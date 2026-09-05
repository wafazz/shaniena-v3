<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One message on a support ticket, from either side of the conversation. */
class CsTicketReply extends Model
{
    protected $table = 'cs_ticket_replies';

    public const UPDATED_AT = null;

    public const FROM_CUSTOMER = 'customer';

    public const FROM_STAFF = 'staff';

    protected $fillable = ['ticket_id', 'user_type', 'user_id', 'message'];

    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'user_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CsReplyAttachment::class, 'reply_id');
    }

    public function fromStaff(): bool
    {
        return $this->user_type === self::FROM_STAFF;
    }
}

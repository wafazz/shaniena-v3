<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Audit trail of status / assignment changes on a ticket. */
class CsTicketLog extends Model
{
    protected $table = 'cs_ticket_logs';

    public const UPDATED_AT = null;

    protected $fillable = ['ticket_id', 'action', 'action_by', 'previous_value', 'new_value'];

    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'action_by' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(MemberHq::class, 'action_by');
    }
}

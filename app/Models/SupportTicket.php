<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer support ticket. The source split this table across two models —
 * SupportTicket (admin reads) and CsTicket (customer writes) — both over
 * cs_tickets; they are merged here.
 */
class SupportTicket extends Model
{
    protected $table = 'cs_tickets';

    public const STATUS_NEW = 'new';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_WAITING_CUSTOMER = 'waiting_customer';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_LOW = 'low';

    /** Sort weight used by the admin queue, from SupportTicket::getOpenTickets(). */
    public const PRIORITY_ORDER = [
        self::PRIORITY_URGENT,
        self::PRIORITY_HIGH,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_LOW,
    ];

    protected $fillable = [
        'customer_name', 'customer_email', 'ticket_no', 'customer_id', 'title',
        'description', 'status', 'order_id', 'assigned_to', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'assigned_to' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CsCustomer::class, 'customer_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(MemberHq::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(CsTicketReply::class, 'ticket_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CsTicketAttachment::class, 'ticket_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CsTicketLog::class, 'ticket_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', '!=', self::STATUS_CLOSED);
    }

    /** Urgent first, then newest — the admin queue order. */
    public function scopeByPriority($query)
    {
        return $query
            ->orderByRaw('FIELD(priority, ?, ?, ?, ?)', self::PRIORITY_ORDER)
            ->orderByDesc('created_at');
    }

    public function getRouteKeyName(): string
    {
        return 'ticket_no';
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function close(): bool
    {
        return $this->update(['status' => self::STATUS_CLOSED]);
    }
}

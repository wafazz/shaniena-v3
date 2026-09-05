<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** File attached to a single ticket reply. */
class CsReplyAttachment extends Model
{
    protected $table = 'cs_reply_attachments';

    public const UPDATED_AT = null;

    protected $fillable = ['reply_id', 'filename', 'file_path', 'file_type'];

    protected function casts(): array
    {
        return [
            'reply_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(CsTicketReply::class, 'reply_id');
    }
}

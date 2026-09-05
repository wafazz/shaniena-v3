<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Audit trail of AWB label prints.
 *
 * The source wrote ONE row per print run holding every order id in a single
 * text column, formatted `[12],[13],[14]` — impossible to query, and it was
 * INSERTed before the PDF was generated, so a failed print still counted.
 * Here it is one row per order, written after the label exists.
 */
class AwbPrint extends Model
{
    use SoftDeletes;

    protected $table = 'awb_printed';

    protected $fillable = ['order_id', 'printed_by'];

    protected function casts(): array
    {
        return [
            'printed_by' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(MemberHq::class, 'printed_by');
    }

    /**
     * Matches this project's plain ids and the legacy `[12],[13]` blobs, so
     * imported history still answers "has this label been printed before?".
     */
    public function scopeForOrder(Builder $query, int $orderId): Builder
    {
        return $query->where(function (Builder $q) use ($orderId) {
            $q->where('order_id', (string) $orderId)
                ->orWhere('order_id', 'like', '%['.$orderId.']%');
        });
    }
}

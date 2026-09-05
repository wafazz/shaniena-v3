<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Malaysian postcode -> area / post office / state lookup, backing the
 * checkout postcode autocomplete.
 */
class PostcodeMy extends Model
{
    protected $table = 'postcode_my';

    /**
     * The source table has no primary key — a postcode maps to many areas, and
     * no column or pair of columns is unique. It is a read-only lookup; writes
     * go through the seeder, not the model.
     */
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['postcode', 'area_name', 'post_office', 'state_code'];

    public function state(): BelongsTo
    {
        return $this->belongsTo(StateMy::class, 'state_code', 'state_code');
    }

    public function scopeForPostcode($query, string $postcode)
    {
        return $query->where('postcode', $postcode);
    }
}

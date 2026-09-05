<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A state within a selling country, carrying the shipping zone that drives
 * postage and COD lookups. Mirrors stateMalaysia() in config/function.php:89.
 */
class StateSetting extends Model
{
    use SoftDeletes;

    protected $table = 'state';

    public $timestamps = false;

    /** Peninsular Malaysia. */
    public const ZONE_WEST = 1;

    /** Sabah / Sarawak / Labuan. */
    public const ZONE_EAST = 2;

    protected $fillable = ['country_id', 'shipping_zone', 'state_code', 'name'];

    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
            'shipping_zone' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(ListCountry::class, 'country_id');
    }

    public function scopeForCountry($query, int $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    public function scopeMalaysia($query)
    {
        return $query->where('country_id', ListCountry::MALAYSIA_ID);
    }
}

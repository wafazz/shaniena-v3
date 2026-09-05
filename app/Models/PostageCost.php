<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shipping cost per country + zone. Replaces calculatePostage() in
 * config/function.php:71.
 */
class PostageCost extends Model
{
    protected $table = 'postage_cost';

    protected $fillable = ['country_id', 'shipping_zone', 'currency', 'first_kilo', 'next_kilo'];

    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
            'shipping_zone' => 'integer',
            'first_kilo' => 'decimal:2',
            'next_kilo' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(ListCountry::class, 'country_id');
    }

    public function scopeForZone($query, int $countryId, int $zone)
    {
        return $query->where('country_id', $countryId)->where('shipping_zone', $zone);
    }

    /**
     * Postage for a parcel weight. The first kilo is charged at first_kilo;
     * every additional kilo — rounded UP to the next whole kilo — at next_kilo.
     * Mirrors calculatePostage() exactly, including the ceil().
     */
    public function costForWeight(float $weightKg): float
    {
        if ($weightKg <= 1) {
            return (float) $this->first_kilo;
        }

        return (float) $this->first_kilo + (ceil($weightKg - 1) * (float) $this->next_kilo);
    }
}

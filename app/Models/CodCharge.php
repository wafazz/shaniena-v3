<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cash-on-delivery fee per country + zone, benchmark based: an order under
 * benchmark_amount pays cod_fee_below, at or above it pays cod_fee_above.
 * Mirrors view/ecom/e-checkout-keya88.php:198-206.
 *
 * Note: shipping_zone is a string here but an integer on postage_cost — that
 * inconsistency is inherited from the source schema and kept for parity.
 */
class CodCharge extends Model
{
    protected $table = 'cod_charges';

    protected $fillable = [
        'country_id', 'shipping_zone', 'benchmark_amount', 'cod_fee_below', 'cod_fee_above',
    ];

    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
            'benchmark_amount' => 'decimal:2',
            'cod_fee_below' => 'decimal:2',
            'cod_fee_above' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(ListCountry::class, 'country_id');
    }

    public function scopeForZone($query, int $countryId, string $zone)
    {
        return $query->where('country_id', $countryId)->where('shipping_zone', $zone);
    }

    /** The fee charged on a given order subtotal. */
    public function feeForSubtotal(float $subtotal): float
    {
        return $subtotal < (float) $this->benchmark_amount
            ? (float) $this->cod_fee_below
            : (float) $this->cod_fee_above;
    }
}

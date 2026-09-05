<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A country the store sells into. `rate` is the MYR conversion rate used to
 * fill customer_orders.to_myr_rate at checkout.
 */
class ListCountry extends Model
{
    protected $table = 'list_country';

    public const STATUS_INACTIVE = 0;

    public const STATUS_ACTIVE = 1;

    /** Malaysia — the only country with per-state shipping zones. */
    public const MALAYSIA_ID = 1;

    protected $fillable = ['name', 'sign', 'rate', 'phone_code', 'status'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'status' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(CountryPrice::class, 'country_id');
    }

    public function postageCosts(): HasMany
    {
        return $this->hasMany(PostageCost::class, 'country_id');
    }

    public function codCharges(): HasMany
    {
        return $this->hasMany(CodCharge::class, 'country_id');
    }

    public function states(): HasMany
    {
        return $this->hasMany(StateSetting::class, 'country_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return (int) $this->status === self::STATUS_ACTIVE;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Physical pickup location. Orders are linked to a hub through
 * customer_orders.tracking_milestone when ship_channel = 'hub_pickup' —
 * an overloaded column inherited from the source, not a real FK.
 */
class PickupHub extends Model
{
    protected $table = 'pickup_hubs';

    public const UPDATED_AT = null;

    public const SHIP_CHANNEL = 'hub_pickup';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'hub_code', 'hub_name', 'contact_person', 'phone', 'email', 'address',
        'country', 'state', 'city', 'postcode', 'latitude', 'longitude', 'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'created_at' => 'datetime',
        ];
    }

    public function staff(): HasMany
    {
        return $this->hasMany(PickupHubStaff::class, 'hub_id');
    }

    /** Orders routed to this hub. See the class note on tracking_milestone. */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'tracking_milestone')
            ->where('ship_channel', self::SHIP_CHANNEL);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function getRouteKeyName(): string
    {
        return 'hub_code';
    }
}

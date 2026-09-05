<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Assignment of an admin user (member_hq) to a pickup hub. */
class PickupHubStaff extends Model
{
    protected $table = 'pickup_hub_staff';

    public $timestamps = false;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_STAFF = 'staff';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = ['user_id', 'hub_id', 'role', 'status'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'hub_id' => 'integer',
        ];
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(PickupHub::class, 'hub_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(MemberHq::class, 'user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}

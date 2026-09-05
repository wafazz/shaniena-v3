<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Support-desk contact record. Separate from Member: a ticket can be raised by
 * someone who never registered a storefront account.
 */
class CsCustomer extends Model
{
    protected $table = 'cs_customers';

    public const UPDATED_AT = null;

    protected $fillable = ['name', 'email', 'phone'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'customer_id');
    }
}

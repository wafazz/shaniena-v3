<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value store settings. Read through App\Services\StoreSettings, which
 * caches the whole table — do not query this model directly in views.
 */
class StoreSetting extends Model
{
    protected $table = 'store_settings';

    public const CREATED_AT = null;

    protected $fillable = ['setting_key', 'setting_value'];

    protected function casts(): array
    {
        return ['updated_at' => 'datetime'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Malaysian state reference table, keyed by the 2-3 letter state code used by
 * the postcode lookup (autocomplete-postcode-city.php).
 */
class StateMy extends Model
{
    protected $table = 'state_my';

    protected $primaryKey = 'state_code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['state_code', 'state_name'];

    public function postcodes(): HasMany
    {
        return $this->hasMany(PostcodeMy::class, 'state_code', 'state_code');
    }
}

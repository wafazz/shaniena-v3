<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Reference list of every world country, used to populate the "add country"
 * picker in admin. Distinct from ListCountry, which holds only the countries
 * the store actually sells into.
 */
class AllCountry extends Model
{
    protected $table = 'all_country';

    public $timestamps = false;

    protected $fillable = ['name', 'sign', 'phone_code'];
}

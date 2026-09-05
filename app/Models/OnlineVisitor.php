<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** One row per returning visitor session. Mirrors `online_visitor_return`. */
class OnlineVisitor extends Model
{
    protected $table = 'online_visitor_return';

    protected $fillable = ['ip_address', 'session_end_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'session_end_at' => 'datetime',
        ];
    }

    /** Still on the site: their session has not run out yet. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('session_end_at', '>=', now());
    }
}

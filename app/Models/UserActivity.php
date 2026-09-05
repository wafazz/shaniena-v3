<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Second, coarser activity log keyed by app segment. Distinct from Activity,
 * which records per-table CRUD.
 */
class UserActivity extends Model
{
    use SoftDeletes;

    protected $table = 'user_activities';

    protected $fillable = ['user_id', 'user_name', 'segment', 'details'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(MemberHq::class, 'user_id');
    }
}

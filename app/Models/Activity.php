<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Admin audit trail. Mirrors activity() in config/function.php:46. */
class Activity extends Model
{
    use SoftDeletes;

    protected $table = 'activities';

    protected $fillable = ['user_id', 'description', 'table_name', 'activities'];

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

    /** Record one audit entry. Replaces the global activity() helper. */
    public static function record(int $userId, string $description, ?string $table, string $activity): self
    {
        return static::create([
            'user_id' => $userId,
            'description' => $description,
            'table_name' => $table,
            'activities' => $activity,
        ]);
    }
}

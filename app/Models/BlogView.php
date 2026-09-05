<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per unique visitor IP per blog post. */
class BlogView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['blog_id', 'visitor_ip', 'created_at'];

    protected function casts(): array
    {
        return [
            'blog_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(NewsBlog::class, 'blog_id');
    }
}

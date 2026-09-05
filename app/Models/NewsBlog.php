<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Blog / news post. `reader` is a denormalised count of blog_views rows,
 * recomputed whenever a new unique visitor IP is recorded.
 */
class NewsBlog extends Model
{
    use SoftDeletes;

    protected $table = 'news_blog';

    protected $fillable = ['post_by', 'update_by', 'title', 'contents', 'reader'];

    protected function casts(): array
    {
        return [
            'post_by' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function views(): HasMany
    {
        return $this->hasMany(BlogView::class, 'blog_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(MemberHq::class, 'post_by');
    }

    public function hasBeenViewedBy(string $ip): bool
    {
        return $this->views()->where('visitor_ip', $ip)->exists();
    }

    /**
     * Record a view from an IP that has not seen this post before, then
     * refresh the denormalised counter. Mirrors NewsBlog::addView().
     */
    public function recordView(string $ip): bool
    {
        if ($this->hasBeenViewedBy($ip)) {
            return false;
        }

        $this->views()->create(['visitor_ip' => $ip, 'created_at' => now()]);
        $this->forceFill(['reader' => (string) $this->views()->count()])->save();

        return true;
    }
}

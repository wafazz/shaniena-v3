<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Homepage slider image, ordered by sort_order (drag-reorder in admin). */
class Slider extends Model
{
    protected $fillable = ['title', 'link_url', 'image', 'sort_order', 'status'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'status' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base for the three single-row rich-text pages (about_us, policy,
 * terms_conditions). The source used one PageContent model with the table
 * name passed in per call; each page gets its own subclass here so the table
 * name can never be caller-supplied.
 */
abstract class PageContent extends Model
{
    protected $fillable = ['description'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** The single content row, created empty on first access. */
    public static function content(): static
    {
        return static::query()->firstOrCreate([], ['description' => '']);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Uploaded logo / slider images. `sorting` is used as a "is default" flag for
 * logos: setDefault() zeroes every row then sets one to 1.
 */
class ImageSetting extends Model
{
    use SoftDeletes;

    protected $table = 'image_setting';

    public const TYPE_LOGO = 'logo';

    public const TYPE_SLIDER = 'slider';

    protected $fillable = ['use_type', 'image_path', 'use_link', 'sorting'];

    protected function casts(): array
    {
        return [
            'sorting' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('use_type', $type);
    }

    public function scopeLogos($query)
    {
        return $query->where('use_type', self::TYPE_LOGO);
    }

    /** Make this the active logo, clearing the flag on every other row. */
    public function makeDefault(): void
    {
        static::query()->update(['sorting' => 0]);
        $this->forceFill(['sorting' => 1])->save();
    }

    public function isDefault(): bool
    {
        return (int) $this->sorting === 1;
    }
}

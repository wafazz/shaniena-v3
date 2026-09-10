<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

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

    /** Forgotten by LogoSettingController whenever the active logo changes. */
    public const LOGO_CACHE_KEY = 'storefront:logo';

    private const LOGO_CACHE_TTL = 600;

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

    /**
     * The active logo's URL, or null while none has been uploaded — in which
     * case the storefront themes and the console sidebar both fall back to the
     * store name as text, exactly as they rendered before.
     *
     * Cached because it is read on every page of both surfaces and changes
     * about as often as the shop is rebranded.
     */
    public static function activeUrl(): ?string
    {
        $url = Cache::remember(self::LOGO_CACHE_KEY, self::LOGO_CACHE_TTL, function () {
            $path = static::query()->logos()->where('sorting', 1)->value('image_path');

            // '' rather than null as the "no logo" marker: Cache::remember
            // reads a cached null as a miss, so a shop that has never
            // uploaded one would re-run this query on every page.
            return $path ? Storage::disk('public')->url($path) : '';
        });

        return $url ?: null;
    }
}

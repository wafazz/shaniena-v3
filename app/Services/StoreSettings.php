<?php

namespace App\Services;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Cached access to the store_settings key/value table.
 *
 * Replaces the global getStoreSettings() helper (config/function.php:2484),
 * keeping its 10-minute cache window. Resolve it from the container — it is
 * registered as a singleton — rather than newing it up, so the per-request
 * copy is shared.
 */
class StoreSettings
{
    public const CACHE_KEY = 'store_settings:all';

    /** Source TTL: cache_remember('store_settings:all', 600, ...). */
    public const CACHE_TTL = 600;

    /** @var array<string, string|null>|null */
    private ?array $loaded = null;

    /** @return array<string, string|null> */
    public function all(): array
    {
        return $this->loaded ??= Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => StoreSetting::query()->pluck('setting_value', 'setting_key')->all(),
        );
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }

    /** Truthy in the source's sense: the string '1'. */
    public function enabled(string $key): bool
    {
        return $this->get($key, '0') === '1';
    }

    public function set(string $key, ?string $value): void
    {
        StoreSetting::query()->updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value, 'updated_at' => now()],
        );

        $this->flush();
    }

    /** @param  array<string, string|null>  $values */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            StoreSetting::query()->updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value, 'updated_at' => now()],
            );
        }

        $this->flush();
    }

    public function flush(): void
    {
        $this->loaded = null;
        Cache::forget(self::CACHE_KEY);
    }
}

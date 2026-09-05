<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * J&T Express credentials — single row, id = 1. Mirrors dataSettingJNT().
 *
 * Mode is 0 = sandbox, 1 = production — the opposite of DhlSetting. The
 * misspelled `username_sanbox` column is the source's, kept for parity.
 */
class JtSetting extends Model
{
    protected $table = 'jt_setting';

    public $timestamps = false;

    public const MODE_SANDBOX = 0;

    public const MODE_PRODUCTION = 1;

    protected $fillable = [
        'production_sandbox',
        'url_sandbox', 'username_sanbox', 'password_sandbox', 'cuscode_sandbox', 'key_sandbox',
        'url_production', 'username_production', 'password_production', 'cuscode_production', 'key_production',
    ];

    protected $hidden = [
        'password_sandbox', 'key_sandbox', 'password_production', 'key_production',
    ];

    protected function casts(): array
    {
        return ['production_sandbox' => 'integer'];
    }

    public static function current(): ?self
    {
        return static::query()->find(1);
    }

    public function isProduction(): bool
    {
        return (int) $this->production_sandbox === self::MODE_PRODUCTION;
    }

    /**
     * Credentials for the active mode.
     *
     * @return array{url: ?string, username: ?string, password: ?string, cuscode: ?string, key: ?string}
     */
    public function credentials(): array
    {
        return $this->isProduction()
            ? [
                'url' => $this->url_production,
                'username' => $this->username_production,
                'password' => $this->password_production,
                'cuscode' => $this->cuscode_production,
                'key' => $this->key_production,
            ]
            : [
                'url' => $this->url_sandbox,
                'username' => $this->username_sanbox,
                'password' => $this->password_sandbox,
                'cuscode' => $this->cuscode_sandbox,
                'key' => $this->key_sandbox,
            ];
    }
}

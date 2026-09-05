<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DHL API credentials — single row, id = 1. Mirrors dhlDetails().
 *
 * Careful: DHL encodes the mode as 1 = production, 2 = sandbox, which is the
 * OPPOSITE of JtSetting (0 = sandbox, 1 = production). Both are kept as the
 * source schema defines them; use the isProduction() helpers, never the raw
 * column.
 */
class DhlSetting extends Model
{
    protected $table = 'dhl';

    public $timestamps = false;

    public const MODE_PRODUCTION = 1;

    public const MODE_SANDBOX = 2;

    protected $fillable = [
        'production_sandbox', 'clientid', 'password', 'format', 'url',
        'clientid_test', 'password_test', 'format_test', 'url_test',
    ];

    protected $hidden = ['password', 'password_test'];

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
     * @return array{clientid: ?string, password: ?string, format: ?string, url: ?string}
     */
    public function credentials(): array
    {
        return $this->isProduction()
            ? [
                'clientid' => $this->clientid,
                'password' => $this->password,
                'format' => $this->format,
                'url' => $this->url,
            ]
            : [
                'clientid' => $this->clientid_test,
                'password' => $this->password_test,
                'format' => $this->format_test,
                'url' => $this->url_test,
            ];
    }
}

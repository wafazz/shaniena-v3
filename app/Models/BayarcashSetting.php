<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bayarcash credentials (table `bayarcash_api`). Like SenangPay, one row holds
 * both sandbox and production keys with `type` selecting the live pair.
 */
class BayarcashSetting extends Model
{
    protected $table = 'bayarcash_api';

    public const MODE_SANDBOX = 'sandbox';

    public const MODE_PRODUCTION = 'production';

    protected $fillable = [
        'type', 'sandbox_api_token', 'sandbox_secret_key', 'sandbox_portal_key',
        'api_token', 'secret_key', 'portal_key',
    ];

    protected $hidden = [
        'sandbox_api_token', 'sandbox_secret_key', 'sandbox_portal_key',
        'api_token', 'secret_key', 'portal_key',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->orderByDesc('id')->first();
    }

    public function isSandbox(): bool
    {
        return $this->type === self::MODE_SANDBOX;
    }

    /**
     * Credentials for the currently selected mode.
     *
     * @return array{api_token: ?string, secret_key: ?string, portal_key: ?string, type: string}
     */
    public function credentials(): array
    {
        return $this->isSandbox()
            ? [
                'api_token' => $this->sandbox_api_token,
                'secret_key' => $this->sandbox_secret_key,
                'portal_key' => $this->sandbox_portal_key,
                'type' => self::MODE_SANDBOX,
            ]
            : [
                'api_token' => $this->api_token,
                'secret_key' => $this->secret_key,
                'portal_key' => $this->portal_key,
                'type' => self::MODE_PRODUCTION,
            ];
    }
}

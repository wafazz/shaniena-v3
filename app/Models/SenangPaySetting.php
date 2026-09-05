<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SenangPay credentials. One row holds both sandbox and production keys;
 * `type` selects which pair is live. Mirrors SenangPaySetting::getCredentials().
 */
class SenangPaySetting extends Model
{
    protected $table = 'senangpay_api';

    public $timestamps = false;

    public const MODE_SANDBOX = 'sandbox';

    public const MODE_PRODUCTION = 'production';

    protected $fillable = [
        'merchant_id', 'secret_key', 'pro_merchant_id', 'pro_secret_key',
        'status', 'type', 'sandbox_url', 'production_url',
    ];

    protected $hidden = ['secret_key', 'pro_secret_key'];

    /** The active row — the source always took the highest id. */
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
     * @return array{merchant_id: ?string, secret_key: ?string, url: ?string, type: string}
     */
    public function credentials(): array
    {
        return $this->isSandbox()
            ? [
                'merchant_id' => $this->merchant_id,
                'secret_key' => $this->secret_key,
                'url' => $this->sandbox_url,
                'type' => self::MODE_SANDBOX,
            ]
            : [
                'merchant_id' => $this->pro_merchant_id,
                'secret_key' => $this->pro_secret_key,
                'url' => $this->production_url,
                'type' => self::MODE_PRODUCTION,
            ];
    }
}

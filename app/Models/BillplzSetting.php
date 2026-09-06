<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Billplz gateway config — single row. Mirrors getBillPlzz(). */
class BillplzSetting extends Model
{
    protected $table = 'billplz';

    public $timestamps = false;

    public const MODE_SANDBOX = 0;

    public const MODE_PRODUCTION = 1;

    /** Who absorbs the payment charge. */
    public const CHARGE_TO_SELLER = 1;

    public const CHARGE_TO_CUSTOMER = 2;

    protected $fillable = [
        'sandbox_production', 'sand_box_url', 'production_url', 'api_key',
        'x_signature', 'bill_collection_id', 'payment_collection_slug',
        'bill_charge', 'payment_charge',
    ];

    protected $hidden = ['api_key', 'x_signature'];

    protected function casts(): array
    {
        return [
            'sandbox_production' => 'integer',
            'bill_charge' => 'float',
            'payment_charge' => 'float',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->first();
    }

    public function isProduction(): bool
    {
        return (int) $this->sandbox_production === self::MODE_PRODUCTION;
    }

    /**
     * Billplz has exactly two hosts, and they are not configuration.
     *
     * They used to come from `sand_box_url` / `production_url`, which the
     * settings form let an operator edit — and this URL is where the API key
     * is sent as HTTP Basic auth. Anyone who could open the payments screen
     * could point it at a host of their own and collect the live key on the
     * next checkout, without ever being able to read it from the form, which
     * masks it. The columns stay for schema parity with the source; nothing
     * reads them.
     */
    public function baseUrl(): string
    {
        return $this->isProduction()
            ? 'https://www.billplz.com/'
            : 'https://www.billplz-sandbox.com/';
    }
}

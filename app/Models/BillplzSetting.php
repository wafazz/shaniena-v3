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

    public function baseUrl(): ?string
    {
        return $this->isProduction() ? $this->production_url : $this->sand_box_url;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-country product pricing — the authoritative source of customer-facing
 * prices, replacing the source project's getPriceOnCountry() helper.
 */
class CountryPrice extends Model
{
    protected $table = 'list_country_product_price';

    protected $fillable = ['country_id', 'product_id', 'market_price', 'sale_price'];

    protected function casts(): array
    {
        return [
            'market_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(ListCountry::class, 'country_id');
    }

    /** The price actually charged. */
    public function chargedPrice(): float
    {
        return (float) $this->sale_price;
    }

    /**
     * Whether the market price should render struck through.
     * Mirrors e-product-details-keya88.php:83 — `sale < market`.
     */
    public function isDiscounted(): bool
    {
        return (float) $this->sale_price < (float) $this->market_price;
    }
}

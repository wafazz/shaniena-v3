<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id', 'variant_name', 'sku', 'price_retail', 'price_sale',
        'stock', 'image', 'max_purchase', 'status',
    ];

    protected function casts(): array
    {
        return [
            'price_retail' => 'decimal:2',
            'price_sale' => 'decimal:2',
            'stock' => 'integer',
            'max_purchase' => 'integer',
            'status' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockControl::class, 'pv_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(Cart::class, 'pv_id');
    }

    /** The storefront auto-selects the first in-stock variant. */
    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function isInStock(): bool
    {
        return (int) $this->stock > 0;
    }

    // Note: customer-facing prices come from `list_country_product_price`
    // (see CountryPrice), not from these columns. price_retail/price_sale are
    // retained for parity with the source schema only.
}

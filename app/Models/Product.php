<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_SIMPLE = 'simple';

    public const TYPE_VARIABLE = 'variable';

    protected $fillable = [
        'name', 'slug', 'description', 'type', 'category_id', 'brand_id',
        'price_capital', 'status', 'weight', 'length', 'width', 'height',
    ];

    protected function casts(): array
    {
        return [
            'price_capital' => 'decimal:2',
            'status' => 'boolean',
            'weight' => 'integer',
            'length' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function countryPrices(): HasMany
    {
        return $this->hasMany(CountryPrice::class, 'product_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockControl::class, 'p_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

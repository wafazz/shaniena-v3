<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stock ledger. Physical stock is the running sum of stock_in - stock_out,
 * matching the source stockBalanceIndividual() helper.
 */
class StockControl extends Model
{
    use SoftDeletes;

    protected $table = 'stock_control';

    protected $fillable = ['p_id', 'pv_id', 'stock_in', 'stock_out', 'comment'];

    protected function casts(): array
    {
        return [
            'stock_in' => 'integer',
            'stock_out' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'p_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'pv_id');
    }
}

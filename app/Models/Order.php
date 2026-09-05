<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One row per ordered variant. Rows are grouped into a customer-visible order
 * by `session_id`, with OrderDetail holding the shared hash_code.
 */
class Order extends Model
{
    use SoftDeletes;

    protected $table = 'customer_orders';

    /** Status codes, verified against OrderController::listOrders() calls. */
    public const STATUS_DRAFT = 0;
    public const STATUS_NEW = 1;
    public const STATUS_PROCESSING = 2;
    public const STATUS_IN_DELIVERY = 3;
    public const STATUS_COMPLETED = 4;
    public const STATUS_RETURNED = 5;
    public const STATUS_CANCELLED = 6;
    /** Awaiting payment — polled by the payment bot (Order::getPendingPayments). */
    public const STATUS_AWAITING_PAYMENT = 10;

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_NEW => 'New',
        self::STATUS_PROCESSING => 'Processing',
        self::STATUS_IN_DELIVERY => 'In Delivery',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_RETURNED => 'Returned',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_AWAITING_PAYMENT => 'Awaiting Payment',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total_qty' => 'integer',
            'total_price' => 'decimal:2',
            'postage_cost' => 'decimal:2',
            'to_myr_rate' => 'decimal:4',
            'myr_value_include_postage' => 'decimal:2',
            'myr_value_without_postage' => 'decimal:2',
            'status' => 'integer',
            'printed_awb' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_var_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(ListCountry::class, 'country_id');
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class, 'session_id', 'order_id');
    }

    public function scopeStatus(Builder $query, int $status): Builder
    {
        return $query->where('status', $status);
    }

    /** Statuses a customer is allowed to see (Order::findForCustomer in source). */
    public function scopeCustomerVisible(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_NEW,
            self::STATUS_PROCESSING,
            self::STATUS_IN_DELIVERY,
            self::STATUS_COMPLETED,
            self::STATUS_RETURNED,
            self::STATUS_CANCELLED,
        ]);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? 'Unknown';
    }

    public function customerFullName(): string
    {
        return trim("{$this->customer_name} {$this->customer_name_last}");
    }
}

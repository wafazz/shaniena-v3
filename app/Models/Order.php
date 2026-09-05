<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One row per order.
 *
 * Line items are NOT on this table — they are `cart` rows sharing the order's
 * `session_id` (see model/Order.php::listByStatus, which joins cart and groups
 * by co.id). `product_var_id` is a denormalised comma-separated list of
 * variant ids kept for the source's own convenience; it is not a foreign key,
 * so never hang a belongsTo off it.
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, SoftDeletes;

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

    /**
     * The one status vocabulary. `resources/js/Components/StatusPill.vue`
     * carries the same map for rendering, and a test holds the two in step —
     * the source had three different label sets for the same seven codes.
     */
    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_NEW => 'New Order',
        self::STATUS_PROCESSING => 'Processing',
        self::STATUS_IN_DELIVERY => 'In Delivery',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_RETURNED => 'Returned',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_AWAITING_PAYMENT => 'Awaiting Payment',
    ];

    /**
     * Stage transitions an operator may perform, keyed by the current status.
     * The source scattered these across button hrefs in the view, so nothing
     * stopped a crafted URL moving an order anywhere.
     */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_NEW => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
        self::STATUS_PROCESSING => [self::STATUS_IN_DELIVERY, self::STATUS_CANCELLED],
        self::STATUS_IN_DELIVERY => [self::STATUS_COMPLETED, self::STATUS_RETURNED, self::STATUS_CANCELLED],
    ];

    /**
     * Moving an order to these statuses also moves its cart lines, so stock
     * and basket state stay consistent (OrderController::statusOrder).
     */
    public const CART_STATUS_ON_TRANSITION = [
        self::STATUS_RETURNED => Cart::STATUS_RETURNED,
        self::STATUS_CANCELLED => Cart::STATUS_CANCELLED,
    ];

    /** Payment channels, as written to customer_orders.payment_channel. */
    public const CHANNEL_COD = 'cod';

    public const CHANNEL_SENANGPAY = 'senangpay';

    public const CHANNEL_BAYARCASH = 'bayarcash';

    public const CHANNEL_STRIPE = 'stripe';

    /**
     * An allowlist, and a deliberately incomplete one.
     *
     * `status`, the money columns and the courier fields are NOT here: those
     * are the four things an attacker would want to set, and leaving them out
     * means a stray `Order::create($request->all())` cannot touch them. Every
     * legitimate writer sets them through forceFill(), which greps cleanly.
     */
    protected $fillable = [
        'session_id', 'order_to', 'product_var_id', 'total_qty',
        'currency_sign', 'country_id', 'country', 'state', 'city', 'postcode',
        'address_1', 'address_2',
        'customer_name', 'customer_name_last', 'customer_phone', 'customer_email',
        'payment_code', 'payment_url', 'ship_channel', 'remark_comment',
    ];

    /** Set only through forceFill, never mass assignment. */
    public const PROTECTED_COLUMNS = [
        'status', 'payment_channel', 'total_price', 'postage_cost',
        'to_myr_rate', 'myr_value_include_postage', 'myr_value_without_postage',
        'courier_service', 'awb_number', 'tracking_url', 'tracking_milestone',
        'printed_awb',
    ];

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

    /** The order's line items. */
    public function lines(): HasMany
    {
        return $this->hasMany(Cart::class, 'session_id', 'session_id');
    }

    /**
     * The variant ids denormalised into product_var_id. Prefer lines(); this
     * exists only for the source code paths that still read that column.
     *
     * @return list<int>
     */
    public function variantIds(): array
    {
        return array_values(array_filter(array_map(
            'intval',
            preg_split('/\s*,\s*/', (string) $this->product_var_id, flags: PREG_SPLIT_NO_EMPTY) ?: [],
        )));
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(ListCountry::class, 'country_id');
    }

    /**
     * `order_details.order_id` holds the ORDER id, not the session id — this
     * relation was left pointing at `session_id` after that was corrected, so
     * it matched nothing. Nothing read it, which is why it went unnoticed.
     */
    public function detail(): HasOne
    {
        return $this->hasOne(OrderDetail::class, 'order_id');
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

    /** `#00000123` — the source's 8-digit padded reference, used everywhere. */
    public function reference(): string
    {
        return '#'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    public function canMoveTo(int $status): bool
    {
        return in_array($status, self::ALLOWED_TRANSITIONS[$this->status] ?? [], true);
    }
}

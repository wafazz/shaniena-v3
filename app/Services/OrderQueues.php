<?php

namespace App\Services;

use App\Models\Order;

/**
 * The seven order routes.
 *
 * They share one screen in the source, but they are two kinds of screen:
 * working queues an operator clears top to bottom, and archives that hold the
 * bulk of a 20k+ order table and are only ever searched. Archives open empty
 * and require a filter — loading page 1 of 18,000 helps nobody.
 */
class OrderQueues
{
    public const QUEUES = [
        'new-order' => [
            'status' => Order::STATUS_NEW,
            'title' => 'New Order',
            'working' => true,
            'per_page' => 100,
        ],
        'process-order' => [
            'status' => Order::STATUS_PROCESSING,
            'title' => 'Process Order',
            'working' => true,
            'per_page' => 100,
        ],
        'indelivery-order' => [
            'status' => Order::STATUS_IN_DELIVERY,
            'title' => 'Indelivery Order',
            'working' => true,
            'per_page' => 30,
        ],
        'completed-order' => [
            'status' => Order::STATUS_COMPLETED,
            'title' => 'Completed Order',
            'working' => false,
            'per_page' => 30,
        ],
        'returned-order' => [
            'status' => Order::STATUS_RETURNED,
            'title' => 'Returned Order',
            'working' => false,
            'per_page' => 30,
        ],
        'cancelled-order' => [
            'status' => Order::STATUS_CANCELLED,
            'title' => 'Cancelled Order',
            'working' => false,
            'per_page' => 30,
        ],
        // Every status, including the ones no queue shows (To Pay, Failed
        // Payment). The source called this "Order Database".
        'database-order' => [
            'status' => null,
            'title' => 'Database Order',
            'working' => false,
            'per_page' => 30,
        ],
    ];

    public static function exists(string $slug): bool
    {
        return isset(self::QUEUES[$slug]);
    }

    /** @return array<string, mixed> */
    public static function get(string $slug): array
    {
        return self::QUEUES[$slug] + ['slug' => $slug];
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::QUEUES);
    }
}

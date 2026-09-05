<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Basket expiry
    |--------------------------------------------------------------------------
    |
    | How long an unpaid basket may sit untouched before it is released. The
    | source hardcoded ten minutes inside the cron script.
    |
    */

    'cart_abandon_minutes' => (int) env('CART_ABANDON_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Delivery tracking
    |--------------------------------------------------------------------------
    |
    | J&T's public tracking endpoint, polled for orders that are out for
    | delivery. The signing key falls back to the one on the jt_setting row so
    | there is only one place to rotate it.
    |
    */

    'tracking' => [
        'jt_url' => env('JT_TRACKING_URL', 'https://ylstandard.jtexpress.my/common/track/trackings'),
        'jt_company_id' => env('JT_TRACKING_COMPANY_ID', 'ROZEYANA'),
        'jt_key' => env('JT_TRACKING_KEY'),
        // Orders checked per scheduled run.
        'batch' => (int) env('TRACKING_BATCH', 100),
    ],

];

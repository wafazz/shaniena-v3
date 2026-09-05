<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shipper details
    |--------------------------------------------------------------------------
    |
    | The pickup address every courier is given. The source repeated this in
    | three places inside config/function.php, and DHL's shipperAddress and
    | pickupAddress had drifted apart (A-G-30 versus B-G-48).
    |
    */

    'shipper' => [
        'name' => env('SHIPPER_NAME', 'ROZZ BEAUTY LEGACY'),
        'contact' => env('SHIPPER_CONTACT', 'Admin'),
        'address1' => env('SHIPPER_ADDRESS_1', 'B-G-48, SAVANNA LIFESTYLE RETAIL'),
        'address2' => env('SHIPPER_ADDRESS_2', 'Jalan Southville 2, Southville City'),
        'city' => env('SHIPPER_CITY', 'Dengkil'),
        'state' => env('SHIPPER_STATE', 'Selangor'),
        'postcode' => env('SHIPPER_POSTCODE', '43800'),
        'phone' => env('SHIPPER_PHONE', '60389123807'),
        'email' => env('SHIPPER_EMAIL', 'orders@example.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AWB labels
    |--------------------------------------------------------------------------
    |
    | 'a5' prints one label per half-sheet on an ordinary office printer.
    | Set AWB_LABEL_PAPER=thermal for 4x6in label stock.
    |
    */

    'label' => [
        'paper' => env('AWB_LABEL_PAPER', 'a5'),
        // 4in x 6in at 72dpi — the size every thermal label printer expects.
        'thermal' => [0, 0, 288, 432],
    ],

    /*
    |--------------------------------------------------------------------------
    | NinjaVan
    |--------------------------------------------------------------------------
    |
    | Credentials come from the environment; the source had no settings table
    | for NinjaVan at all, so there is nothing to migrate.
    |
    */

    'ninjavan' => [
        'enabled' => (bool) env('NINJAVAN_ENABLED', false),
        'sandbox' => (bool) env('NINJAVAN_SANDBOX', true),
        'country' => env('NINJAVAN_COUNTRY', 'MY'),
        'client_id' => env('NINJAVAN_CLIENT_ID'),
        'client_secret' => env('NINJAVAN_CLIENT_SECRET'),
    ],

];

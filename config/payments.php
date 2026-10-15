<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Both gateways default to their public sandbox environments. Set the
    | *_SANDBOX variables to false and use live credentials in production.
    |
    */

    'gateways' => [

        'esewa' => [
            'label' => 'eSewa',
            'sandbox' => env('ESEWA_SANDBOX', true),
            'product_code' => env('ESEWA_PRODUCT_CODE', 'EPAYTEST'),
            // Public UAT key from eSewa's developer docs; replace in production.
            'secret_key' => env('ESEWA_SECRET_KEY', '8gBm/:&EnhH.1/q'),
            'urls' => [
                'sandbox' => [
                    'form' => 'https://rc-epay.esewa.com.np/api/epay/main/v2/form',
                    'status' => 'https://rc.esewa.com.np/api/epay/transaction/status/',
                ],
                'production' => [
                    'form' => 'https://epay.esewa.com.np/api/epay/main/v2/form',
                    'status' => 'https://epay.esewa.com.np/api/epay/transaction/status/',
                ],
            ],
        ],

        'khalti' => [
            'label' => 'Khalti',
            'sandbox' => env('KHALTI_SANDBOX', true),
            'secret_key' => env('KHALTI_SECRET_KEY'),
            'urls' => [
                'sandbox' => 'https://dev.khalti.com/api/v2/',
                'production' => 'https://khalti.com/api/v2/',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Reconciliation
    |--------------------------------------------------------------------------
    |
    | Pending payments older than this many minutes are re-checked against the
    | gateway by `php artisan payments:reconcile` (scheduled every five minutes).
    |
    */

    'reconcile_after_minutes' => env('PAYMENTS_RECONCILE_AFTER', 15),

];

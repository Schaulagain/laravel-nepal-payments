<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    |
    | All gateways default to their test environments. Set the *_SANDBOX
    | variables to false and use live credentials in production. A gateway
    | only shows up at checkout once its credentials are set.
    |
    */

    'gateways' => [

        'esewa' => [
            'label' => 'eSewa',
            'description' => 'Pay from your eSewa wallet',
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
            'description' => 'Khalti wallet or mobile banking',
            'sandbox' => env('KHALTI_SANDBOX', true),
            'secret_key' => env('KHALTI_SECRET_KEY'),
            'urls' => [
                'sandbox' => 'https://dev.khalti.com/api/v2/',
                'production' => 'https://khalti.com/api/v2/',
            ],
        ],

        // Himalayan Bank card payments (2C2P PACO). There is no public sandbox:
        // HBL issues the office ID, API key and its two public keys when you
        // sign up as a merchant, and you send it the public halves of your own
        // two key pairs. Keys can be PEM or bare base64.
        'hbl' => [
            'label' => 'HBL card',
            'description' => 'Visa or Mastercard, through Himalayan Bank',
            'sandbox' => env('HBL_SANDBOX', true),
            'office_id' => env('HBL_OFFICE_ID'),
            'api_key' => env('HBL_API_KEY'),
            'encryption_key_id' => env('HBL_ENCRYPTION_KEY_ID'),
            'merchant_signing_private_key' => env('HBL_MERCHANT_SIGNING_PRIVATE_KEY'),
            'merchant_decryption_private_key' => env('HBL_MERCHANT_DECRYPTION_PRIVATE_KEY'),
            'paco_encryption_public_key' => env('HBL_PACO_ENCRYPTION_PUBLIC_KEY'),
            'paco_signing_public_key' => env('HBL_PACO_SIGNING_PUBLIC_KEY'),
            'currency' => env('HBL_CURRENCY', 'NPR'),
            'three_d_secure' => env('HBL_3DS', true),
            'urls' => [
                'sandbox' => 'https://core.demo-paco.2c2p.com/',
                'production' => 'https://core.paco.2c2p.com/',
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

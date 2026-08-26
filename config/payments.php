<?php

use App\Services\Payments\Gateways\ManualTransferGateway;
use App\Services\Payments\Gateways\XenditGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Provider → Gateway map
    |--------------------------------------------------------------------------
    |
    | Each App\Enums\PaymentProvider case maps to a class implementing
    | PaymentGatewayInterface. Adding a provider = add a class + a line here.
    |
    */

    'providers' => [
        'xendit' => XenditGateway::class,
        'manual' => ManualTransferGateway::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | How long a payment instruction stays valid
    |--------------------------------------------------------------------------
    |
    | Kept at/below the order's own 30-minute hold so a QR can never outlive
    | the quota reservation it belongs to.
    |
    */

    'expiry_minutes' => (int) env('PAYMENT_EXPIRY_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Payment method catalog
    |--------------------------------------------------------------------------
    |
    | The list surfaced by GET /api/payment-methods. Flip `enabled` to expose a
    | method — disabled entries stay here as ready-to-activate scaffolding.
    |
    |   code         unique key the client sends back when creating a payment
    |   provider     which gateway handles it (App\Enums\PaymentProvider)
    |   type         how the buyer pays (App\Enums\PaymentType) — drives grouping
    |   channel_code provider-specific channel identifier
    |
    */

    'methods' => [

        // ---- Live -----------------------------------------------------------
        [
            'code'         => 'qris',
            'name'         => 'QRIS',
            'description'  => 'Scan with GoPay, OVO, DANA, ShopeePay, or any mobile banking app.',
            'provider'     => 'xendit',
            'type'         => 'qris',
            'channel_code' => 'QRIS',
            'logo_url'     => '/images/payments/qris.png',
            'fee_flat'     => 0,
            'fee_percent'  => 0.7,
            'min_amount'   => 1_000,
            'max_amount'   => 10_000_000,
            'enabled'      => true,
        ],

        // ---- Scaffolded, not yet live ---------------------------------------
        // Flip `enabled` once the channel is activated on the provider side.
        [
            'code'         => 'bri_va',
            'name'         => 'Bank BRI Virtual Account',
            'description'  => 'Pay via BRI ATM, BRImo, or internet banking.',
            'provider'     => 'xendit',
            'type'         => 'virtual_account',
            'channel_code' => 'BRI',
            'logo_url'     => '/images/payments/bri.png',
            'fee_flat'     => 4_000,
            'fee_percent'  => 0,
            'min_amount'   => 10_000,
            'max_amount'   => 50_000_000,
            'enabled'      => false,
        ],
        [
            'code'         => 'mandiri_va',
            'name'         => 'Bank Mandiri Virtual Account',
            'description'  => 'Pay via Mandiri ATM, Livin\', or internet banking.',
            'provider'     => 'xendit',
            'type'         => 'virtual_account',
            'channel_code' => 'MANDIRI',
            'logo_url'     => '/images/payments/mandiri.png',
            'fee_flat'     => 4_000,
            'fee_percent'  => 0,
            'min_amount'   => 10_000,
            'max_amount'   => 50_000_000,
            'enabled'      => false,
        ],
        [
            'code'         => 'mandiri_transfer',
            'name'         => 'Bank Mandiri Transfer',
            'description'  => 'Manual transfer to our Mandiri account, confirmed by our team.',
            'provider'     => 'manual',
            'type'         => 'bank_transfer',
            'channel_code' => 'MANDIRI',
            'logo_url'     => '/images/payments/mandiri.png',
            'fee_flat'     => 0,
            'fee_percent'  => 0,
            'min_amount'   => 10_000,
            'max_amount'   => 100_000_000,
            'enabled'      => false,
            'account'      => [
                'bank_name'      => env('MANUAL_BANK_NAME', 'Bank Mandiri'),
                'account_number' => env('MANUAL_BANK_ACCOUNT_NUMBER'),
                'account_holder' => env('MANUAL_BANK_ACCOUNT_HOLDER'),
            ],
        ],
    ],
];

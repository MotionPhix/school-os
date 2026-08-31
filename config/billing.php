<?php

declare(strict_types=1);

/**
 * Platform billing capability — configuration.
 *
 * The platform charges TENANTS (not individual users) for the subscription.
 * Permission keys are pulled into the global catalog by the
 * `registered_capabilities` list in config/identity.php.
 */
return [

    'permissions' => [
        ['key' => 'billing.payments.read',   'domain' => 'billing', 'label' => 'View billing',        'description' => 'See the tenant subscription invoice and payment history.'],
        ['key' => 'billing.payments.write',  'domain' => 'billing', 'label' => 'Make payments',       'description' => 'Start a PayChangu checkout and settle the tenant subscription.'],
    ],

    /**
     * Monthly subscription per tenant, in minor units (e.g. 50000 = MWK 500).
     * Env-tunable via PLATFORM_MONTHLY_FEE_MINOR.
     */
    'monthly_fee_minor' => (int) env('PLATFORM_MONTHLY_FEE_MINOR', 50000),

    /**
     * PayChangu standard checkout (https://developer.paychangu.com/docs/standard-checkout).
     */
    'paychangu' => [
        'base_url' => env('PAYCHANGU_BASE_URL', 'https://api.paychangu.com'),
        'secret_key' => env('PAYCHANGU_SECRET_KEY', ''),
        'mode' => env('PAYCHANGU_MODE', 'test'),
        'supported_currencies' => ['MWK', 'USD'],
    ],
];

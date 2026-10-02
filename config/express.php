<?php

declare(strict_types=1);

// Express Component settings. Data only: nothing here runs at load.

/**
 * @var array{
 *     wallets: array<string, array{gatewayId: string, mollieMethod: string, paidAs: string, checkoutSetting: string, addressFrom: string}>,
 *     surfaces: array<int, string>,
 *     allowedModes: array<int, string>,
 *     sessionLifetimeSeconds: int,
 *     sessionReuseMarginSeconds: int,
 *     maxNewSessions: int,
 *     maxNewSessionsPerAddress: int,
 *     windowSeconds: int,
 *     abandonGraceSeconds: int,
 *     abandonGiveUpSeconds: int,
 * } $express
 */
$express = [
    // A wallet shows when its gateway exists, is enabled, is active at Mollie, has `checkoutSetting` on
    // and carries no surcharge.
    // addressFrom: 'form' waits for the checkout's shipping form; 'wallet' would use the wallet's own sheet.
    // mollieMethod is the id in Mollie's methods API; paidAs is the method Mollie reports on the payment.
    'wallets' => [
        'applepay' => [
            'gatewayId' => 'mollie_wc_gateway_applepay',
            'mollieMethod' => 'applepay',
            'paidAs' => 'applepay',
            'checkoutSetting' => 'mollie_apple_pay_button_enabled_express_checkout',
            'addressFrom' => 'form',
        ],
        'paypal' => [
            'gatewayId' => 'mollie_wc_gateway_paypal',
            'mollieMethod' => 'paypal',
            'paidAs' => 'paypal',
            'checkoutSetting' => 'mollie_paypal_button_enabled_checkout',
            'addressFrom' => 'form',
        ],
        'googlepay' => [
            'gatewayId' => 'mollie_wc_gateway_googlepay',
            'mollieMethod' => 'googlepay',
            'paidAs' => 'creditcard',
            'checkoutSetting' => 'enabled', // its only setting: the method exists only for express
            'addressFrom' => 'form',
        ],
    ],
    'surfaces' => ['checkout'],
    // Sessions may have no test mode.
    'allowedModes' => ['live'],
    // A floor, not Mollie's number: raise it only against a measured expiry.
    'sessionLifetimeSeconds' => 900,
    // A session with less than this left is not handed out again.
    'sessionReuseMarginSeconds' => 60,
    // New Mollie sessions per shopper per window; each price change needs one.
    'maxNewSessions' => 10,
    // Per caller address per window, whoever asks: one address can be many shoppers.
    'maxNewSessionsPerAddress' => 100,
    'windowSeconds' => 600,
    // Cleanup waits this long after a session expired, so a late payment can still arrive.
    'abandonGraceSeconds' => 3600,
    // An order Mollie could not be asked about is cancelled this long after its session expired.
    'abandonGiveUpSeconds' => 7 * 86400,
];

return $express;

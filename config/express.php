<?php

declare(strict_types=1);

// Data for the Express Component that differs by wallet, surface or mode.
//
// A table, not code: nothing here does I/O at load. Later specs add what they need (the session
// reuse margin, the anonymous work budget, the abandon grace) next to the rows that use them.

/**
 * @var array{
 *     wallets: array<string, array{gatewayId: string, mollieMethod: string, checkoutSetting: string, addressFrom: string}>,
 *     surfaces: array<int, string>,
 *     allowedModes: array<int, string>,
 *     sessionLifetimeSeconds: int,
 *     sessionReuseMarginSeconds: int,
 *     maxNewSessions: int,
 *     windowSeconds: int,
 *     abandonGraceSeconds: int,
 * } $express
 */
$express = [
    // A wallet is offered in the Express Component on the checkout when ALL of these hold: the plugin has
    // a payment method with this gateway id, the merchant enabled that method, it is active at Mollie,
    // and the method's own setting `checkoutSetting` ("show the express button on the checkout") is on.
    // Express has no switch of its own: it takes over what the merchant already turned on per method.
    //
    // addressFrom says where the shipping address comes from: 'form' is the WooCommerce checkout form, so
    // a cart that ships must wait for it; 'wallet' would be the wallet's own sheet, as today's Apple Pay
    // button does. In the Express Component every wallet waits for the form, Apple Pay included (owner,
    // 2026-09-22), so no row uses 'wallet'; switching one back is a data change only.
    //
    // googlepay has no payment method yet, so it stays hidden until one with this gateway id exists; its
    // checkoutSetting is the name the future method should expose, to be aligned then.
    'wallets' => [
        'applepay' => [
            'gatewayId' => 'mollie_wc_gateway_applepay',
            'mollieMethod' => 'applepay',
            'checkoutSetting' => 'mollie_apple_pay_button_enabled_express_checkout',
            'addressFrom' => 'form',
        ],
        'paypal' => [
            'gatewayId' => 'mollie_wc_gateway_paypal',
            'mollieMethod' => 'paypal',
            'checkoutSetting' => 'mollie_paypal_button_enabled_checkout',
            'addressFrom' => 'form',
        ],
        'googlepay' => [
            'gatewayId' => 'mollie_wc_gateway_googlepay',
            'mollieMethod' => 'googlepay',
            'checkoutSetting' => 'mollie_googlepay_button_enabled_express_checkout',
            'addressFrom' => 'form',
        ],
    ],
    'surfaces' => ['checkout'],
    // Sessions may have no test mode, so live only until it is answered.
    'allowedModes' => ['live'],
    // How long the plugin treats a Checkout Session as usable after Mollie created it.
    // This is deliberately a floor, not Mollie's number. Raise it only against a measured expiry.
    'sessionLifetimeSeconds' => 900,
    // An open session is handed out again only while it has more than this left before it expires,
    // so the shopper is not given a token that dies while they are in the wallet.
    'sessionReuseMarginSeconds' => 60,
    // The anonymous work budget of the session route: at most this many new Mollie sessions per
    // caller per window. Sized for a shopper who edits the address or the rate a few times (each
    // price change needs a new session), not for one request per keystroke.
    'maxNewSessions' => 10,
    'windowSeconds' => 600,
    // Cleanup looks at a pending express order only this long after its session expired,
    // so an order is never cancelled while its payment could still arrive
    'abandonGraceSeconds' => 3600,
];

return $express;

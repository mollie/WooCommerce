<?php

declare (strict_types=1);
namespace Mollie;

// Express Component settings. Data only: nothing here runs at load.
/**
 * @var array{
 *     wallets: array<string, array{gatewayId: string, mollieMethod: string, paidAs?: string, paidWallet?: string, checkoutSetting: string, cartSetting: string, addressFrom: string}>,
 *     surfaces: array<int, string>,
 *     allowedModes: array<int, string>,
 *     sessionLifetimeSeconds: int,
 *     sessionReuseMarginSeconds: int,
 *     maxNewSessions: int,
 *     maxNewSessionsPerAddress: int,
 *     windowSeconds: int,
 *     abandonGraceSeconds: int,
 * } $express
 */
$express = [
    // A wallet shows on a surface when its gateway exists, is enabled, is active at Mollie, has that
    // surface's switch on (`checkoutSetting`, `cartSetting`) and carries no surcharge.
    // addressFrom: 'wallet' takes the address from the wallet's own sheet and the store prices the options for it
    // (shipping callback); 'form' would wait for the checkout's shipping form.
    // mollieMethod is the id in Mollie's methods API; a payment shows as method paidAs, or as a creditcard with details.wallet paidWallet.
    'wallets' => ['applepay' => ['gatewayId' => 'mollie_wc_gateway_applepay', 'mollieMethod' => 'applepay', 'paidAs' => 'applepay', 'paidWallet' => 'applepay', 'checkoutSetting' => 'mollie_apple_pay_button_enabled_express_checkout', 'cartSetting' => 'mollie_apple_pay_button_enabled_cart', 'addressFrom' => 'wallet'], 'paypal' => ['gatewayId' => 'mollie_wc_gateway_paypal', 'mollieMethod' => 'paypal', 'paidAs' => 'paypal', 'checkoutSetting' => 'mollie_paypal_button_enabled_checkout', 'cartSetting' => 'mollie_paypal_button_enabled_cart', 'addressFrom' => 'wallet'], 'googlepay' => ['gatewayId' => 'mollie_wc_gateway_googlepay', 'mollieMethod' => 'googlepay', 'paidWallet' => 'googlepay', 'checkoutSetting' => 'mollie_googlepay_button_enabled_checkout', 'cartSetting' => 'mollie_googlepay_button_enabled_cart', 'addressFrom' => 'wallet']],
    // The block checkout and the block cart; never the product page or the classic cart.
    'surfaces' => ['checkout', 'cart'],
    // Sessions may have no test mode.
    'allowedModes' => ['live'],
    // A floor, not Mollie's number: raise it only against a measured expiry.
    'sessionLifetimeSeconds' => 900,
    // A session with less than this left is not handed out again.
    'sessionReuseMarginSeconds' => 60,
    // New Mollie sessions per shopper per window; each price change needs one.
    'maxNewSessions' => 10,
    // Per caller address per window, whoever asks: one address can be many shoppers.
    // Kept below what one cleanup run cancels, so one address cannot leave more pending orders than cleanup removes.
    'maxNewSessionsPerAddress' => 40,
    'windowSeconds' => 600,
    // Cleanup waits this long after a session expired, so a late payment can still arrive.
    'abandonGraceSeconds' => 3600,
];
return $express;

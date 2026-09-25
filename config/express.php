<?php

declare (strict_types=1);
namespace Mollie;

// Data for the Express Component that differs by wallet, surface or mode.
//
// A table, not code: nothing here does I/O at load. Later specs add what they need (the session
// reuse margin, the anonymous work budget, the abandon grace) next to the rows that use them.
/**
 * @var array{
 *     wallets: array<string, array{gatewayId: string, mollieMethod: string, needsHttps: bool, checkoutSetting: string, addressFrom: string}>,
 *     surfaces: array<int, string>,
 *     allowedModes: array<int, string>,
 * } $express
 */
$express = [
    // A wallet is offered in the Express Component on the checkout when ALL of these hold: the plugin has
    // a payment method with this gateway id, the merchant enabled that method, it is active at Mollie,
    // and the method's own setting `checkoutSetting` ("show the express button on the checkout") is on.
    // Express has no switch of its own: it takes over what the merchant already turned on per method.
    //
    // addressFrom says where the shipping address comes from: 'form' is the WooCommerce checkout form, so
    // a cart that ships must wait for it; 'wallet' is the wallet's own sheet, as today's Apple Pay button
    // does. Apple Pay is 'wallet' by the owner's decision of 2026-09-21; whether the component can do it
    // is unconfirmed and is on the real-key checklist, so flipping it here is a data change only.
    //
    // googlepay has no payment method yet, so it stays hidden until one with this gateway id exists; its
    // checkoutSetting is the name the future method should expose, to be aligned then.
    'wallets' => ['applepay' => ['gatewayId' => 'mollie_wc_gateway_applepay', 'mollieMethod' => 'applepay', 'needsHttps' => \true, 'checkoutSetting' => 'mollie_apple_pay_button_enabled_express_checkout', 'addressFrom' => 'wallet'], 'paypal' => ['gatewayId' => 'mollie_wc_gateway_paypal', 'mollieMethod' => 'paypal', 'needsHttps' => \false, 'checkoutSetting' => 'mollie_paypal_button_enabled_checkout', 'addressFrom' => 'form'], 'googlepay' => ['gatewayId' => 'mollie_wc_gateway_googlepay', 'mollieMethod' => 'googlepay', 'needsHttps' => \true, 'checkoutSetting' => 'mollie_googlepay_button_enabled_express_checkout', 'addressFrom' => 'form']],
    'surfaces' => ['checkout'],
    // Sessions may have no test mode (REQ-H4 is unanswered), so live only until it is answered.
    'allowedModes' => ['live'],
];
return $express;

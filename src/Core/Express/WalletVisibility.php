<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Express;

use Mollie\WooCommerce\Core\Types\CartFacts;
use Mollie\WooCommerce\Core\Types\ExpressSettings;
use Mollie\WooCommerce\Core\Types\ShopFacts;
/**
 * Which wallets the Express Component offers.
 *
 * Express checkout is not a gateway and has no switch of its own: it offers the wallets the plugin
 * already has as payment methods, and the merchant controls each one where they already do. A wallet
 * is shown only if its payment method exists in the plugin, the merchant enabled it, it is active at
 * Mollie, and the method's own "show the express button on the checkout" setting is on; a wallet that
 * needs HTTPS is hidden on plain HTTP. A wallet with no payment method in the plugin is hidden, and
 * appears by itself the day one is added.
 *
 * Given a cart, a wallet whose shipping address comes from the checkout form is hidden while that
 * form is incomplete; a wallet with its own address sheet is not.
 */
final class WalletVisibility
{
    /**
     * @return array<string, bool> The options.buttons map, keyed by the wallets table.
     */
    public static function buttons(ExpressSettings $settings, ShopFacts $shop, ?CartFacts $cart = null): array
    {
        $buttons = [];
        foreach ($settings->wallets() as $wallet => $row) {
            $gatewayId = $row['gatewayId'];
            $buttons[$wallet] = in_array($gatewayId, $shop->registeredGatewayIds(), \true) && in_array($gatewayId, $shop->enabledGatewayIds(), \true) && in_array($row['mollieMethod'], $shop->activeMollieMethods(), \true) && in_array($gatewayId, $shop->expressCheckoutGatewayIds(), \true) && (!$row['needsHttps'] || $shop->isHttps()) && !self::waitsForTheCheckoutForm($row['addressFrom'], $cart);
        }
        return $buttons;
    }
    private static function waitsForTheCheckoutForm(string $addressFrom, ?CartFacts $cart): bool
    {
        return $cart !== null && $addressFrom === 'form' && $cart->needsShipping() && !($cart->shippingDestinationComplete() && $cart->shippingRateChosen());
    }
}

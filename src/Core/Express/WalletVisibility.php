<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

use Mollie\WooCommerce\Core\Types\CartFacts;
use Mollie\WooCommerce\Core\Types\ExpressSettings;
use Mollie\WooCommerce\Core\Types\ShopFacts;

/**
 * Which wallets the Express Component offers.
 *
 * Express checkout is not a gateway and has no switch of its own: it offers the wallets the plugin
 * already has as payment methods, and the merchant controls each one where they already do. HTTPS is not a
 * per-wallet question: ExpressAvailability refuses a plain HTTP site before any wallet is looked at.
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
            $buttons[$wallet] = in_array($gatewayId, $shop->registeredGatewayIds(), true)
                && in_array($gatewayId, $shop->enabledGatewayIds(), true)
                && in_array($row['mollieMethod'], $shop->activeMollieMethods(), true)
                && in_array($gatewayId, $shop->expressCheckoutGatewayIds(), true)
                && !self::waitsForTheCheckoutForm($row['addressFrom'], $cart);
        }

        return $buttons;
    }

    private static function waitsForTheCheckoutForm(string $addressFrom, ?CartFacts $cart): bool
    {
        return $cart !== null
            && $addressFrom === 'form'
            && $cart->needsShipping()
            && !($cart->shippingDestinationComplete() && $cart->shippingRateChosen());
    }
}

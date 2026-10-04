<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressSettings;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShopFacts;
/**
 * HTTPS is checked by ExpressAvailability, not per wallet.
 */
final class WalletVisibility
{
    /**
     * @return array<string, bool> Keyed by wallet.
     */
    public static function buttons(ExpressSettings $settings, ShopFacts $shop, ?CartFacts $cart = null): array
    {
        $buttons = [];
        foreach ($settings->wallets() as $wallet => $row) {
            $gatewayId = $row['gatewayId'];
            $buttons[$wallet] = in_array($gatewayId, $shop->registeredGatewayIds(), \true) && in_array($gatewayId, $shop->enabledGatewayIds(), \true) && in_array($row['mollieMethod'], $shop->activeMollieMethods(), \true) && in_array($gatewayId, $shop->expressCheckoutGatewayIds(), \true) && !self::waitsForTheCheckoutForm($row['addressFrom'], $cart);
        }
        return $buttons;
    }
    private static function waitsForTheCheckoutForm(string $addressFrom, ?CartFacts $cart): bool
    {
        return $cart !== null && $addressFrom === 'form' && $cart->needsShipping() && !($cart->shippingDestinationComplete() && $cart->shippingRateChosen());
    }
}

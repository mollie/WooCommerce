<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressSettings;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShopFacts;
/**
 * HTTPS is checked by ExpressAvailability, not per wallet.
 */
final class WalletVisibility
{
    /**
     * @param string $surface The page the buttons are for: its switch decides.
     * @return array<string, bool> Keyed by wallet.
     */
    public static function buttons(ExpressSettings $settings, ShopFacts $shop, string $surface): array
    {
        $buttons = [];
        foreach ($settings->wallets() as $wallet => $row) {
            $gatewayId = $row['gatewayId'];
            $buttons[$wallet] = in_array($gatewayId, $shop->registeredGatewayIds(), \true) && in_array($gatewayId, $shop->enabledGatewayIds(), \true) && in_array($row['mollieMethod'], $shop->activeMollieMethods(), \true) && in_array($gatewayId, $shop->expressGatewayIds($surface), \true) && !in_array($gatewayId, $shop->surchargedGatewayIds(), \true);
        }
        return $buttons;
    }
}

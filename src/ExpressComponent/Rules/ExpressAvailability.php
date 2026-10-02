<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressAvailabilityResult;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressSettings;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShopFacts;

/**
 * The first failing check is the reason. Waiting for the shipping form is blocked, not unavailable.
 */
final class ExpressAvailability
{
    public static function resolve(
        ExpressSettings $settings,
        ShopFacts $shop,
        ?CartFacts $cart,
        string $surface
    ): ExpressAvailabilityResult {

        if (!in_array($surface, $settings->supportedSurfaces(), true)) {
            return ExpressAvailabilityResult::unavailable('surface_not_supported');
        }
        if (!in_array($shop->mode(), $settings->allowedModes(), true)) {
            return ExpressAvailabilityResult::unavailable('mode_not_allowed');
        }
        if (!$shop->isHttps()) {
            return ExpressAvailabilityResult::unavailable('not_https');
        }
        if (!in_array(true, WalletVisibility::buttons($settings, $shop), true)) {
            return ExpressAvailabilityResult::unavailable('no_wallet_visible');
        }
        if ($cart === null) {
            return ExpressAvailabilityResult::available();
        }

        return self::resolveCart($settings, $shop, $cart);
    }

    private static function resolveCart(ExpressSettings $settings, ShopFacts $shop, CartFacts $cart): ExpressAvailabilityResult
    {
        if ($cart->lines() === []) {
            return ExpressAvailabilityResult::unavailable('cart_empty');
        }
        foreach ($cart->lines() as $line) {
            if ($line->isSubscription()) {
                return ExpressAvailabilityResult::unavailable('subscription_in_cart');
            }
        }
        if (!in_array(true, WalletVisibility::buttons($settings, $shop, $cart), true)) {
            return ExpressAvailabilityResult::blocked('shipping_incomplete');
        }
        // After the shipping check: until then the total is not final.
        $total = $cart->total();
        if ($total !== null && $total->minorUnits() <= 0) {
            return ExpressAvailabilityResult::unavailable('nothing_to_pay');
        }

        return ExpressAvailabilityResult::available();
    }
}

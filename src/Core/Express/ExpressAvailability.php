<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

use Mollie\WooCommerce\Core\Types\CartFacts;
use Mollie\WooCommerce\Core\Types\ExpressAvailabilityResult;
use Mollie\WooCommerce\Core\Types\ExpressSettings;
use Mollie\WooCommerce\Core\Types\ShopFacts;

/**
 * May the Express Component run here, for this cart, in this mode.
 *
 * The checks run in a fixed order and the first that fails is the reason. Express has no switch of
 * its own: it may run on a surface when at least one wallet is visible, that is, when the merchant
 * turned on a wallet's express button for it. A cart that ships whose every visible wallet still
 * waits for the checkout form is blocked, not unavailable: the feature is there and becomes
 * available the moment the form is complete. Never throws.
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

        return ExpressAvailabilityResult::available();
    }
}

<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressAvailabilityResult;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressSettings;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShopFacts;

/**
 * The first failing check is the reason.
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
        if (!in_array(true, WalletVisibility::buttons($settings, $shop, $surface), true)) {
            return ExpressAvailabilityResult::unavailable('no_wallet_visible');
        }
        if ($cart === null) {
            return ExpressAvailabilityResult::available();
        }

        return self::resolveCart($cart);
    }

    private static function resolveCart(CartFacts $cart): ExpressAvailabilityResult
    {
        if ($cart->lines() === []) {
            return ExpressAvailabilityResult::unavailable('cart_empty');
        }
        foreach ($cart->lines() as $line) {
            if ($line->isSubscription()) {
                return ExpressAvailabilityResult::unavailable('subscription_in_cart');
            }
        }
        // Mollie cannot change a session's amount: the price must not depend on an address the wallet gives later.
        if (
            TaxCompatibility::decide(
                $cart->taxEnabled(),
                $cart->taxBasedOn(),
                $cart->taxCountries(),
                $cart->ratesVaryWithinCountry()
            ) === TaxCompatibility::INCOMPATIBLE
        ) {
            return ExpressAvailabilityResult::unavailable('tax_depends_on_address');
        }
        $total = $cart->total();
        if ($total !== null && $total->minorUnits() <= 0) {
            return ExpressAvailabilityResult::unavailable('nothing_to_pay');
        }

        return ExpressAvailabilityResult::available();
    }
}

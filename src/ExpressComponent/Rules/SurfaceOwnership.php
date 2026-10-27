<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressAvailabilityResult;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressSettings;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShopFacts;
/**
 * Where Express owns a surface, the legacy express buttons step aside.
 * It owns it only when the cart is ready as the page is rendered: a price that waits for an address keeps the legacy buttons.
 */
final class SurfaceOwnership
{
    public static function owns(ExpressSettings $settings, ShopFacts $shop, CartFacts $cart, string $surface): bool
    {
        return \Mollie\WooCommerce\ExpressComponent\Rules\ExpressAvailability::resolve($settings, $shop, $cart, $surface)->status() === ExpressAvailabilityResult::AVAILABLE;
    }
}

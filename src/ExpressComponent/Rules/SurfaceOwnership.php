<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressAvailabilityResult;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressSettings;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShopFacts;

/**
 * Where Express owns a surface, the legacy express buttons step aside.
 */
final class SurfaceOwnership
{
    public static function owns(ExpressSettings $settings, ShopFacts $shop, string $surface): bool
    {
        return ExpressAvailability::resolve($settings, $shop, null, $surface)->status()
            === ExpressAvailabilityResult::AVAILABLE;
    }
}

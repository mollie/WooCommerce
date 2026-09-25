<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

use Mollie\WooCommerce\Core\Types\ExpressAvailabilityResult;
use Mollie\WooCommerce\Core\Types\ExpressSettings;
use Mollie\WooCommerce\Core\Types\ShopFacts;

/**
 * The one mutual-exclusion rule: does the Express Component own this surface?
 *
 * It is availability without a cart. When Express owns a surface the legacy buttons step aside
 * there; when it is off, or on but unable to run, they keep what the merchant configured.
 */
final class SurfaceOwnership
{
    public static function owns(ExpressSettings $settings, ShopFacts $shop, string $surface): bool
    {
        return ExpressAvailability::resolve($settings, $shop, null, $surface)->status()
            === ExpressAvailabilityResult::AVAILABLE;
    }
}

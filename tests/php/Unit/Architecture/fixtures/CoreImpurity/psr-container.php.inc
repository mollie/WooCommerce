<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

use Psr\Container\ContainerInterface;

/**
 * Breaks one rule: a service locator in the core. Pass what the decision needs as an argument.
 */
final class ResolvesFromTheContainer
{
    public static function decide(ContainerInterface $container): bool
    {
        return (bool) $container->get('settings.IsTestModeEnabled');
    }
}

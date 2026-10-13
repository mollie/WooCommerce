<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

/**
 * Breaks one rule: state that survives between two calls, and between two orders.
 */
final class CachesBetweenCalls
{
    /**
     * @var array<string, bool>
     */
    private static $seen = [];

    public static function decide(string $reference): bool
    {
        return isset(self::$seen[$reference]);
    }
}

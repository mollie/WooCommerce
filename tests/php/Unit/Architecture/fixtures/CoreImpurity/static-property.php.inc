<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

/**
 * Breaks one rule: state that survives between two calls, and between two orders (FF-09).
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

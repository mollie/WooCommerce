<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Psr\Log\LoggerInterface;

/**
 * Breaks one rule: a rule cannot log at all. Return a verdict instead.
 */
final class LogsFromARule
{
    public static function decide(LoggerInterface $logger, string $reason): bool
    {
        $logger->warning($reason);

        return false;
    }
}

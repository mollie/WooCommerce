<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

use Psr\Log\LoggerInterface;

/**
 * Breaks one rule: a rule cannot log at all (ADR-014). Return a verdict instead.
 */
final class LogsFromTheCore
{
    public static function decide(LoggerInterface $logger, string $reason): bool
    {
        $logger->warning($reason);

        return false;
    }
}

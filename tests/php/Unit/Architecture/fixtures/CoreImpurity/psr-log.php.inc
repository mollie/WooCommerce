<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

use Psr\Log\LoggerInterface;

/**
 * Breaks one rule: the core cannot log at all (ADR-014). Return an Effect instead.
 */
final class LogsFromTheCore
{
    public static function decide(LoggerInterface $logger, string $reason): bool
    {
        $logger->warning($reason);

        return false;
    }
}

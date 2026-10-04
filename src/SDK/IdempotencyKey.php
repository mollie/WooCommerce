<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\SDK;

use InvalidArgumentException;

/**
 * Parts must be ids and amounts, never personal data.
 */
final class IdempotencyKey
{
    private const PREFIX = 'mwc';

    /**
     * @param string $intent Versioned, e.g. 'express.session.v1'.
     * @param array<string, scalar|null> $parts
     * @throws InvalidArgumentException
     */
    public static function for(string $intent, array $parts): string
    {
        $pairs = [];
        foreach ($parts as $name => $value) {
            $pairs[] = [(string) $name, self::normalise((string) $name, $value)];
        }
        usort($pairs, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        $canonical = json_encode([$intent, $pairs], JSON_THROW_ON_ERROR);

        return self::PREFIX . '-' . hash('sha256', $canonical);
    }

    /**
     * @param mixed $value
     */
    private static function normalise(string $name, $value): string
    {
        if (is_bool($value)) {
            return $value ? 'bool:1' : 'bool:0';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_int($value) || is_string($value)) {
            return 'v:' . $value;
        }

        throw new InvalidArgumentException(
            sprintf('Idempotency key part "%s" must be an int, string, bool or null.', $name)
        );
    }
}

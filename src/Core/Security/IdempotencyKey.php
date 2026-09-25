<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Security;

use InvalidArgumentException;

/**
 * The deterministic key every mutating Mollie call carries (blueprint ADR-012).
 *
 * The same intent and parts always give the same key, so Mollie can refuse a repeated intent. Any
 * change to the versioned intent name, or to a part's name or value, gives a different key, so a
 * legitimate second attempt is not mistaken for a retry. The key is a hash: it never contains a
 * part value, and parts must be ids and amounts, never personal data.
 */
final class IdempotencyKey
{
    private const PREFIX = 'mwc';

    /**
     * @param string $intent Versioned intent name, for example 'express.session.v1'.
     * @param array<string, scalar|null> $parts Ids, attempt numbers and amounts, by name.
     *
     * @throws InvalidArgumentException When a part is not a scalar.
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
     * Integers and their string forms are the same part; booleans and null are kept distinct.
     *
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

<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

/**
 * Mollie cannot change a session's amount, so the price must not depend on an address the wallet gives later.
 */
final class TaxCompatibility
{
    public const COMPATIBLE = 'compatible';
    public const REGIONAL = 'regional';
    public const INCOMPATIBLE = 'incompatible';

    /**
     * An empty country list is a fact that could not be read: it counts as incompatible.
     *
     * @param list<string> $taxCountries
     */
    public static function decide(
        bool $taxEnabled,
        string $taxBasedOn,
        array $taxCountries,
        bool $ratesVaryWithinCountry
    ): string {

        if (!$taxEnabled || $taxBasedOn === 'base') {
            return self::COMPATIBLE;
        }
        if (count($taxCountries) !== 1) {
            return self::INCOMPATIBLE;
        }

        return $ratesVaryWithinCountry ? self::REGIONAL : self::COMPATIBLE;
    }
}

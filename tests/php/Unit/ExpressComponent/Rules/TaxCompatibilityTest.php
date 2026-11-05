<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\TaxCompatibility;
use Mollie\WooCommerceTests\TestCase;

/**
 * Whether the price of a cart, taxes included, is known before the wallet gives an address.
 *
 * @covers \Mollie\WooCommerce\ExpressComponent\Rules\TaxCompatibility
 */
class TaxCompatibilityTest extends TestCase
{
    /**
     * Scenario: express checkout needs the amount with taxes before the wallet gives an address
     *   Given the shop's tax settings: taxes on or off, the address they are based on, the countries
     *         whose address the taxes can follow, and whether rates differ by state or postcode inside one country
     *   When the rule decides
     *   Then it is incompatible when taxes are on, they follow the customer's address and that
     *        address can be in more than one country
     *   And it is regional when there is one such country, taxes follow the customer's address
     *        and the rates differ inside that country
     *   And it is compatible in every other case
     *
     * @dataProvider taxSettings
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\TaxCompatibility::decide
     * @param array<int, string> $taxCountries
     * @group REQ-NEW-EXPRESS-TAX
     */
    public function testDecidesWhetherTheTaxesAreKnownBeforeTheWalletGivesAnAddress(
        bool $taxEnabled,
        string $taxBasedOn,
        array $taxCountries,
        bool $ratesVaryWithinCountry,
        string $expected
    ): void {

        self::assertSame(
            $expected,
            TaxCompatibility::decide($taxEnabled, $taxBasedOn, $taxCountries, $ratesVaryWithinCountry)
        );
    }

    /**
     * @return array<string, array{0: bool, 1: string, 2: array<int, string>, 3: bool, 4: string}>
     */
    public function taxSettings(): array
    {
        return [
            'taxes off, many countries' => [false, 'billing', ['DE', 'IT'], false, 'compatible'],
            'taxes off, rates would differ' => [false, 'billing', ['DE'], true, 'compatible'],
            'taxes on the shop address, many countries' => [true, 'base', ['DE', 'IT'], false, 'compatible'],
            'taxes on the shop address, rates differ' => [true, 'base', ['DE'], true, 'compatible'],
            'billing address, one country, one rate' => [true, 'billing', ['DE'], false, 'compatible'],
            'shipping address, one country, one rate' => [true, 'shipping', ['DE'], false, 'compatible'],
            'billing address, two countries' => [true, 'billing', ['DE', 'IT'], false, 'incompatible'],
            'shipping address, two countries' => [true, 'shipping', ['DE', 'IT'], false, 'incompatible'],
            'billing address, two countries and rates differ' => [true, 'billing', ['DE', 'IT'], true, 'incompatible'],
            'billing address, one country, rates differ' => [true, 'billing', ['DE'], true, 'regional'],
            'shipping address, one country, rates differ' => [true, 'shipping', ['DE'], true, 'regional'],
        ];
    }
}

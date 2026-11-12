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
     * Scenario: only taxes that follow the customer's address can change the price after the session is priced
     *   Given taxes on or off and the address they are based on
     *   When the rule asks whether they follow the customer's address
     *   Then it is yes only with taxes on and a basis other than the shop address
     *
     * @dataProvider addressBases
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\TaxCompatibility::followsCustomerAddress
     * @group REQ-NEW-EXPRESS-TAX
     */
    public function testKnowsWhenTheTaxesFollowTheCustomerAddress(bool $taxEnabled, string $taxBasedOn, bool $expected): void
    {

        self::assertSame($expected, TaxCompatibility::followsCustomerAddress($taxEnabled, $taxBasedOn));
    }

    /**
     * Scenario: taxes that follow one country's address are known beforehand only for a cart priced for that country
     *   Given the shop's tax settings and the country the cart's taxes were calculated for
     *   When the rule asks whether the cart is priced for the country its taxes follow
     *   Then it is no when taxes follow the customer's address in one country and the cart was priced
     *        for another country or for none
     *   And it is yes in every other case: the rule on many countries answers for those
     *
     * @dataProvider taxableCountries
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\TaxCompatibility::cartPricedForTaxCountry
     * @param array<int, string> $taxCountries
     * @group REQ-NEW-EXPRESS-TAX
     */
    public function testKnowsWhenTheCartIsPricedForTheCountryItsTaxesFollow(
        bool $taxEnabled,
        string $taxBasedOn,
        array $taxCountries,
        string $taxableCountry,
        bool $expected
    ): void {

        self::assertSame(
            $expected,
            TaxCompatibility::cartPricedForTaxCountry($taxEnabled, $taxBasedOn, $taxCountries, $taxableCountry)
        );
    }

    /**
     * @return array<string, array{0: bool, 1: string, 2: array<int, string>, 3: string, 4: bool}>
     */
    public function taxableCountries(): array
    {
        return [
            'billing address, priced for the only country' => [true, 'billing', ['AT'], 'AT', true],
            'shipping address, priced for the only country' => [true, 'shipping', ['AT'], 'AT', true],
            'billing address, priced for no country' => [true, 'billing', ['AT'], '', false],
            'shipping address, priced for no country' => [true, 'shipping', ['AT'], '', false],
            'billing address, priced for another country' => [true, 'billing', ['AT'], 'DE', false],
            'taxes off, priced for no country' => [false, 'billing', ['AT'], '', true],
            'taxes on the shop address, priced for no country' => [true, 'base', ['AT'], '', true],
            'many countries, priced for none' => [true, 'billing', ['AT', 'DE'], '', true],
            'countries could not be read' => [true, 'billing', [], '', true],
        ];
    }

    /**
     * @return array<string, array{0: bool, 1: string, 2: bool}>
     */
    public function addressBases(): array
    {
        return [
            'taxes off, billing address' => [false, 'billing', false],
            'taxes off, shop address' => [false, 'base', false],
            'taxes on the shop address' => [true, 'base', false],
            'taxes on the billing address' => [true, 'billing', true],
            'taxes on the shipping address' => [true, 'shipping', true],
        ];
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
            'billing address, countries could not be read' => [true, 'billing', [], false, 'incompatible'],
        ];
    }
}

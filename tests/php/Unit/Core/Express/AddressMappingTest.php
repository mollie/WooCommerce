<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Core\Express;

use Mollie\WooCommerce\Core\Express\AddressMapping;
use Mollie\WooCommerceTests\TestCase;

/**
 * An address a wallet collected, from Mollie's field names to WooCommerce's (REQ-D5; AC-24).
 *
 * Mollie's region becomes WooCommerce's state, as given. A field Mollie did not supply is left out,
 * never written empty, so the order keeps its own value.
 *
 * @covers \Mollie\WooCommerce\Core\Express\AddressMapping
 */
class AddressMappingTest extends TestCase
{
    /**
     * Scenario: an address the wallet collected is mapped back to WooCommerce, and what it lacks is left out
     *   Given an address in Mollie's field names, as it is on the paid payment
     *   When it is mapped to WooCommerce's field names
     *   Then region is state, streetAdditional is address_2, and phone and email are kept
     *   And no key is present for a field Mollie did not supply, so the order's own value is never overwritten with null or ''
     *
     * @dataProvider mollieAddresses
     * @covers \Mollie\WooCommerce\Core\Express\AddressMapping::toWooCommerce
     * @param array<string, mixed> $mollie
     * @param array<string, string> $expected
     */
    public function testMapsAMollieAddressToWooCommerceFields(array $mollie, array $expected): void
    {
        $mapped = AddressMapping::toWooCommerce($mollie);

        ksort($mapped);
        ksort($expected);
        self::assertSame($expected, $mapped);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: array<string, string>}>
     */
    public function mollieAddresses(): array
    {
        return [
            'a full wallet address' => [
                [
                    'givenName' => 'Piet',
                    'familyName' => 'Mondriaan',
                    'organizationName' => 'De Stijl',
                    'email' => 'piet@example.org',
                    'phone' => '+31208202070',
                    'streetAndNumber' => 'Keizersgracht 12',
                    'streetAdditional' => 'Unit 3',
                    'postalCode' => '1015 CS',
                    'city' => 'Amsterdam',
                    'region' => 'Noord-Holland',
                    'country' => 'NL',
                ],
                [
                    'first_name' => 'Piet',
                    'last_name' => 'Mondriaan',
                    'company' => 'De Stijl',
                    'email' => 'piet@example.org',
                    'phone' => '+31208202070',
                    'address_1' => 'Keizersgracht 12',
                    'address_2' => 'Unit 3',
                    'postcode' => '1015 CS',
                    'city' => 'Amsterdam',
                    'state' => 'Noord-Holland',
                    'country' => 'NL',
                ],
            ],
            'PayPal without phone, second line or region' => [
                [
                    'givenName' => 'Piet',
                    'familyName' => 'Mondriaan',
                    'email' => 'piet@example.org',
                    'streetAndNumber' => 'Keizersgracht 12',
                    'postalCode' => '1015 CS',
                    'city' => 'Amsterdam',
                    'country' => 'NL',
                ],
                [
                    'first_name' => 'Piet',
                    'last_name' => 'Mondriaan',
                    'email' => 'piet@example.org',
                    'address_1' => 'Keizersgracht 12',
                    'postcode' => '1015 CS',
                    'city' => 'Amsterdam',
                    'country' => 'NL',
                ],
            ],
            'null and empty values are left out' => [
                ['givenName' => 'Piet', 'phone' => null, 'streetAdditional' => '', 'region' => '  '],
                ['first_name' => 'Piet'],
            ],
            'fields WooCommerce has no place for are dropped' => [
                ['city' => 'Town', 'unknownField' => 'x'],
                ['city' => 'Town'],
            ],
        ];
    }
}

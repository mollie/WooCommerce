<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Core\Express;

use Mollie\WooCommerce\Core\Express\AddressMapping;
use Mollie\WooCommerceTests\TestCase;

/**
 * The address the store holds, in the shape Mollie's checkout.on('submit') resolve() takes
 * (REQ-C3; AC-16).
 *
 * The input is an address in WooCommerce field names without the billing_/shipping_ prefix, as the
 * customer or order returns it. WooCommerce's state becomes Mollie's region, as held (a state code
 * stays a code: the core cannot ask WooCommerce for the name). What the store does not hold is left
 * out, never sent empty, so the wallet can still collect it.
 *
 * @covers \Mollie\WooCommerce\Core\Express\AddressMapping
 */
class AddressMappingTest extends TestCase
{
    /**
     * Scenario: the store's address is mapped field by field, and what it lacks is left out
     *   Given an address in WooCommerce field names
     *   When it is mapped to Mollie's shape
     *   Then every held field has its Mollie name, state is region
     *   And no key is present for a field the store does not hold
     *
     * @dataProvider addresses
     * @covers \Mollie\WooCommerce\Core\Express\AddressMapping::toMollie
     * @param array<string, string> $wooCommerce
     * @param array<string, string> $expected
     */
    public function testMapsTheAddressTheStoreHoldsToMolliesShape(array $wooCommerce, array $expected): void
    {
        $mapped = AddressMapping::toMollie($wooCommerce);

        ksort($mapped);
        ksort($expected);
        self::assertSame($expected, $mapped);
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: array<string, string>}>
     */
    public function addresses(): array
    {
        return [
            'a full billing address' => [
                [
                    'first_name' => 'Piet',
                    'last_name' => 'Mondriaan',
                    'email' => 'piet@example.org',
                    'phone' => '+31208202070',
                    'address_1' => 'Keizersgracht 12',
                    'address_2' => 'Unit 3',
                    'postcode' => '1015 CS',
                    'city' => 'Amsterdam',
                    'state' => 'NH',
                    'country' => 'NL',
                ],
                [
                    'givenName' => 'Piet',
                    'familyName' => 'Mondriaan',
                    'email' => 'piet@example.org',
                    'phone' => '+31208202070',
                    'streetAndNumber' => 'Keizersgracht 12',
                    'streetAdditional' => 'Unit 3',
                    'postalCode' => '1015 CS',
                    'city' => 'Amsterdam',
                    'region' => 'NH',
                    'country' => 'NL',
                ],
            ],
            'a shipping address: no email, no second line, no state' => [
                [
                    'first_name' => 'Piet',
                    'last_name' => 'Mondriaan',
                    'address_1' => 'Rue 1',
                    'address_2' => '',
                    'postcode' => '1234',
                    'city' => 'Town',
                    'state' => '',
                    'country' => 'LU',
                ],
                [
                    'givenName' => 'Piet',
                    'familyName' => 'Mondriaan',
                    'streetAndNumber' => 'Rue 1',
                    'postalCode' => '1234',
                    'city' => 'Town',
                    'country' => 'LU',
                ],
            ],
            'only an email is held' => [
                ['email' => 'piet@example.org', 'first_name' => '', 'country' => ''],
                ['email' => 'piet@example.org'],
            ],
            'nothing is held' => [
                ['first_name' => '', 'last_name' => '', 'email' => '', 'country' => ''],
                [],
            ],
            'fields Mollie has no place for are dropped' => [
                ['city' => 'Town', 'unknown_field' => 'x'],
                ['city' => 'Town'],
            ],
        ];
    }
}

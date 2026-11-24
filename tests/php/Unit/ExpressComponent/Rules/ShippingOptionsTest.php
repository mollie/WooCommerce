<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\ShippingOptions;
use Mollie\WooCommerce\Shared\Values\Money;
use Mollie\WooCommerceTests\TestCase;

/**
 * What the store answers when Mollie asks for the shipping options of an address.
 *
 * @covers \Mollie\WooCommerce\ExpressComponent\Rules\ShippingOptions
 */
class ShippingOptionsTest extends TestCase
{
    private const MOLLIE_REFUSALS = ['unsupported-country', 'unsupported-postal-code', 'unsupported-address'];

    /**
     * Scenario: an address nobody ships to is refused with one of Mollie's types, never offered an empty list
     *   Given the rates the store found for an address, or the fact that the lookup failed
     *   When the options are decided
     *   Then no option is offered
     *   And the refusal is one of unsupported-country, unsupported-postal-code or unsupported-address
     *   And a lookup that failed is always unsupported-address: the shopper is never offered a price the store did not compute
     *
     * @dataProvider addressesWithoutOptions
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\ShippingOptions::decide
     * @group REQ-NEW-EXPRESS-SHIPPING-CALLBACK
     * @param list<array{id: string, label: string, amount: Money}> $rates
     */
    public function testRefusesWithAMollieTypeWhenNoRateApplies(array $rates, bool $lookupFailed, ?string $expectedType): void
    {
        $verdict = ShippingOptions::decide($rates, $lookupFailed);

        self::assertSame([], $verdict->options());
        self::assertContains($verdict->refusalType(), self::MOLLIE_REFUSALS);
        if ($expectedType !== null) {
            self::assertSame($expectedType, $verdict->refusalType());
        }
    }

    /**
     * @return array<string, array{0: list<array{id: string, label: string, amount: Money}>, 1: bool, 2: ?string}>
     */
    public function addressesWithoutOptions(): array
    {
        $rate = [['id' => 'flat_rate:1', 'label' => 'Standard', 'amount' => Money::fromDecimal('6.05', 'EUR')]];

        return [
            'no rate applies' => [[], false, null],
            'the lookup failed' => [[], true, 'unsupported-address'],
            // A lookup that failed is refused even when it left a rate behind: that price was not computed.
            'the lookup failed and left a rate' => [$rate, true, 'unsupported-address'],
        ];
    }
}

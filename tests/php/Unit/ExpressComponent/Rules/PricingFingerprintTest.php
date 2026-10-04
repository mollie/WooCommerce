<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\PricingFingerprint;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartLine;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartShipping;
use Mollie\WooCommerce\Shared\Values\Money;
use Mollie\WooCommerceTests\TestCase;

/**
 * What makes two session requests "the same checkout": a hash of cart, total, currency,
 * shipping rate and destination, never the destination itself.
 *
 * @covers \Mollie\WooCommerce\ExpressComponent\Rules\PricingFingerprint
 */
class PricingFingerprintTest extends TestCase
{
    /**
     * Scenario: the same checkout gives the same fingerprint
     *   Given two cart facts built separately from the same cart, total, rate and destination
     *   When their fingerprints are taken
     *   Then they are equal
     *
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\PricingFingerprint::of
     */
    public function testSameCheckoutGivesTheSameFingerprint(): void
    {
        self::assertSame(PricingFingerprint::of($this->cart()), PricingFingerprint::of($this->cart()));
    }

    /**
     * Scenario: each pricing input on its own makes a different checkout
     *   Given a cart that differs from the reference in one pricing input
     *   When its fingerprint is taken
     *   Then it differs from the reference's
     *
     * @dataProvider changedInputs
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\PricingFingerprint::of
     * @param array<string, mixed> $change
     */
    public function testEachPricingInputChangesTheFingerprint(array $change): void
    {
        self::assertNotSame(PricingFingerprint::of($this->cart()), PricingFingerprint::of($this->cart($change)));
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public function changedInputs(): array
    {
        return [
            'the cart contents' => [['cartHash' => 'another-cart-hash']],
            'the total' => [['total' => '26.06']],
            'the currency' => [['currency' => 'USD']],
            'the shipping rate' => [['rateIds' => ['flat_rate:2']]],
            'the shipping destination' => [['destination' => 'AT|1010|Wien']],
        ];
    }

    /**
     * Scenario: the fingerprint does not carry the address
     *   Given a cart shipping to a postcode and a city
     *   When its fingerprint is taken
     *   Then it is a SHA-256 hex digest
     *   And neither the postcode nor the city can be read from it
     *
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\PricingFingerprint::of
     */
    public function testTheFingerprintDoesNotContainTheDestination(): void
    {
        $fingerprint = PricingFingerprint::of($this->cart(['destination' => 'LU|L-1234|Luxembourg']));

        self::assertSame(1, preg_match('/^[0-9a-f]{64}$/', $fingerprint), 'A SHA-256 hex digest.');
        self::assertStringNotContainsString('1234', $fingerprint);
        self::assertStringNotContainsString('Luxembourg', $fingerprint);
    }

    /**
     * Scenario: a cart with nothing to ship is not repriced by its address
     *   Given two carts with nothing to ship that differ only in their destination
     *   When their fingerprints are taken
     *   Then the two are the same
     *   And a cart that ships is still repriced by the same change
     *
     * The Store API copies the billing address onto such a cart's shipping fields, unseen by the page.
     *
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\PricingFingerprint::of
     */
    public function testACartWithNothingToShipIgnoresItsDestination(): void
    {
        $here = PricingFingerprint::of($this->cart(['needsShipping' => false, 'destination' => 'LU|L-1234|Luxembourg']));
        $there = PricingFingerprint::of($this->cart(['needsShipping' => false, 'destination' => 'ES|48001|Bilbao']));

        self::assertSame($here, $there, 'An address nothing is shipped to cannot reprice a session.');
        self::assertNotSame(
            PricingFingerprint::of($this->cart(['destination' => 'LU|L-1234|Luxembourg'])),
            PricingFingerprint::of($this->cart(['destination' => 'ES|48001|Bilbao'])),
            'A cart that ships is still repriced by its destination.'
        );
    }

    /**
     * @param array<string, mixed> $change
     */
    private function cart(array $change = []): CartFacts
    {
        $currency = (string) ($change['currency'] ?? 'EUR');
        $money = static fn (string $value): Money => Money::fromDecimal($value, $currency);

        return new CartFacts(
            lines: [new CartLine(
                productId: 7,
                quantity: 2,
                isSubscription: false,
                name: 'Test Simple Product',
                subtotal: $money('20.00'),
                vatRate: '21.00'
            )],
            needsShipping: (bool) ($change['needsShipping'] ?? true),
            shippingDestinationComplete: true,
            shippingRateChosen: true,
            total: $money((string) ($change['total'] ?? '26.05')),
            fees: [],
            coupons: [],
            shipping: new CartShipping($change['rateIds'] ?? ['flat_rate:1'], 'Flat rate', $money('6.05'), '21.00'),
            cartHash: (string) ($change['cartHash'] ?? 'cart-hash'),
            destination: (string) ($change['destination'] ?? 'LU|L-1234|Luxembourg')
        );
    }
}

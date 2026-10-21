<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\SessionLines;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartCoupon;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFee;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartLine;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartShipping;
use Mollie\WooCommerce\Shared\Values\Money;
use Mollie\WooCommerceTests\Integration\Common\FakeMollie\SessionRules;
use Mollie\WooCommerceTests\TestCase;

/**
 * The cart as Checkout Sessions lines, priced to the cent and judged by FakeMollie\SessionRules.
 * All amounts include VAT.
 *
 * @covers \Mollie\WooCommerce\ExpressComponent\Rules\SessionLines
 */
class SessionLinesTest extends TestCase
{
    /**
     * Scenario: Mollie's documented example is priced exactly
     *   Given a cart with one line of quantity 2 at 10.00 including 21% VAT
     *   When the session lines are built
     *   Then there is one physical line
     *   And its unitPrice is 10.00, its totalAmount 20.00 and its vatAmount 3.47
     *   And its vatRate is the string '21.00'
     *
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\SessionLines::fromCart
     */
    public function testPricesTwoAtTenIncluding21PercentVat(): void
    {
        $cart = $this->cart([$this->line('Test Simple Product', 2, '20.00', '21.00')], '20.00');

        $lines = SessionLines::fromCart($cart);

        self::assertCount(1, $lines);
        self::assertSame('physical', $lines[0]['type']);
        self::assertSame(2, $lines[0]['quantity']);
        self::assertSame(['currency' => 'EUR', 'value' => '10.00'], $lines[0]['unitPrice']);
        self::assertSame(['currency' => 'EUR', 'value' => '20.00'], $lines[0]['totalAmount']);
        self::assertSame(['currency' => 'EUR', 'value' => '3.47'], $lines[0]['vatAmount']);
        self::assertSame('21.00', $lines[0]['vatRate']);
    }

    /**
     * Scenario: every cart shape the checkout can produce passes the documented rules
     *   Given a cart with coupons, fees, a taxed shipping rate, mixed VAT rates, a line subtotal the
     *         quantity does not divide, or a cent of rounding between the lines and the cart total
     *   When the session lines are built
     *   Then a payload made of them and the cart total breaks none of the Sessions rules
     *   And every line satisfies both formulas and the lines sum to the cart total to the cent
     *   And a coupon is a discount line with a negative unitPrice
     *   And a fee is a surcharge line
     *   And the chosen shipping rate is exactly one shipping_fee line
     *
     * @dataProvider cartShapes
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\SessionLines::fromCart
     */
    public function testBuildsLinesThatPassTheSessionRules(CartFacts $cart): void
    {
        $lines = SessionLines::fromCart($cart);

        $payload = [
            'amount' => ['currency' => 'EUR', 'value' => $cart->total()->toDecimal()],
            'description' => 'Express checkout',
            'lines' => $lines,
            'redirectUrl' => 'https://shop.example/express-return',
        ];
        self::assertSame([], SessionRules::violations($payload), 'The lines break the documented Sessions rules.');

        $types = array_column($lines, 'type');
        self::assertSame(
            $cart->shipping() === null ? 0 : 1,
            count(array_keys($types, 'shipping_fee', true)),
            'The chosen shipping rate must be exactly one shipping_fee line.'
        );
        if ($cart->coupons() !== []) {
            self::assertContains('discount', $types, 'A coupon must be a discount line.');
        }
        if ($cart->fees() !== []) {
            self::assertContains('surcharge', $types, 'A fee must be a surcharge line.');
        }
        foreach ($lines as $line) {
            if (($line['type'] ?? '') === 'discount') {
                self::assertStringStartsWith('-', $line['unitPrice']['value'], 'A discount line needs a negative unitPrice.');
            }
            if (isset($line['vatRate'])) {
                self::assertIsString($line['vatRate']);
                self::assertSame(1, preg_match('/^\d+\.\d{2}$/', $line['vatRate']), 'vatRate must have two decimals.');
            }
        }
    }

    /**
     * @return array<string, array{0: CartFacts}>
     */
    public function cartShapes(): array
    {
        $twoAtTen = $this->line('Test Simple Product', 2, '20.00', '21.00');
        $threeAtFiveFortyFive = $this->line('Book', 3, '16.35', '9.00');
        $flatRate = new CartShipping(['flat_rate:1'], 'Flat rate', $this->money('6.05'), '21.00');
        $handling = new CartFee('Handling', $this->money('1.21'), '21.00');

        return [
            'a percentage coupon' => [$this->cart([$twoAtTen], '18.00', coupons: [new CartCoupon('ten-percent', $this->money('2.00'))])],
            'a fixed coupon' => [$this->cart([$twoAtTen], '15.00', coupons: [new CartCoupon('five-off', $this->money('5.00'))])],
            'a fee' => [$this->cart([$twoAtTen], '21.21', fees: [$handling])],
            'a taxed shipping rate' => [$this->cart([$twoAtTen], '26.05', shipping: $flatRate)],
            'mixed VAT rates' => [$this->cart([$twoAtTen, $threeAtFiveFortyFive], '36.35')],
            'everything at once' => [$this->cart(
                [$twoAtTen, $threeAtFiveFortyFive],
                '38.61',
                fees: [$handling],
                coupons: [new CartCoupon('five-off', $this->money('5.00'))],
                shipping: $flatRate
            )],
            'a line subtotal the quantity does not divide' => [$this->cart([$this->line('Pen', 3, '10.00', '21.00')], '10.00')],
            'the cart total is a cent above the lines' => [$this->cart(
                [$this->line('Pen', 3, '9.99', '21.00'), $this->line('Cup', 1, '5.00', '21.00')],
                '15.00'
            )],
            'the cart total is a cent below the lines' => [$this->cart(
                [$this->line('Pen', 3, '9.99', '21.00'), $this->line('Cup', 1, '5.00', '21.00')],
                '14.98'
            )],
            'the cart total is far below the lines' => [$this->cart(
                [$this->line('Pen', 3, '9.99', '21.00'), $this->line('Cup', 1, '5.00', '21.00')],
                '4.99'
            )],
            'the cart total is far above the lines' => [$this->cart(
                [$this->line('Pen', 3, '9.99', '21.00'), $this->line('Cup', 1, '5.00', '21.00')],
                '24.99'
            )],
            'a product line below zero' => [$this->cart(
                [$this->line('Gift', 1, '-0.02', '21.00'), $this->line('Cup', 1, '1.00', '21.00')],
                '0.98'
            )],
        ];
    }

    /**
     * Scenario: a product line below zero is sent as a discount line
     *   Given a cart where another plugin priced a product line below zero
     *   When the session lines are built
     *   Then that line is a discount of its whole subtotal, quantity 1, named after the product, without VAT
     *   And the other lines keep their type and total
     *
     * @dataProvider productLinesBelowZero
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\SessionLines::fromCart
     */
    public function testSendsAProductLineBelowZeroAsADiscount(int $quantity, string $subtotal, string $total): void
    {
        $cart = $this->cart(
            [$this->line('Gift', $quantity, $subtotal, '21.00'), $this->line('Cup', 1, '1.00', '21.00')],
            $total
        );

        $lines = SessionLines::fromCart($cart);

        self::assertSame([
            'type' => 'discount',
            'description' => 'Gift',
            'quantity' => 1,
            'unitPrice' => ['currency' => 'EUR', 'value' => $subtotal],
            'totalAmount' => ['currency' => 'EUR', 'value' => $subtotal],
        ], $lines[0]);
        self::assertSame(['physical', '1.00'], [$lines[1]['type'], $lines[1]['totalAmount']['value']]);
    }

    /**
     * @return array<string, array{0: int, 1: string, 2: string}>
     */
    public function productLinesBelowZero(): array
    {
        return [
            'one unit' => [1, '-0.02', '0.98'],
            'a quantity that does not divide the subtotal' => [3, '-0.05', '0.95'],
        ];
    }

    /**
     * Scenario: a cent of rounding lands on the largest line only
     *   Given a cart whose total is one cent above the sum of its line subtotals
     *   When the session lines are built
     *   Then the largest line absorbs the cent
     *   And every other line keeps its own total
     *
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\SessionLines::fromCart
     */
    public function testPutsAResidualCentOnTheLargestLine(): void
    {
        $cart = $this->cart(
            [$this->line('Pen', 3, '9.99', '21.00'), $this->line('Cup', 1, '5.00', '21.00')],
            '15.00'
        );

        $lines = SessionLines::fromCart($cart);

        self::assertSame('10.00', $lines[0]['totalAmount']['value']);
        self::assertSame('5.00', $lines[1]['totalAmount']['value']);
    }

    /**
     * Scenario: a difference that is more than rounding gets a line of its own
     *   Given a cart of two lines whose total differs from the sum of their subtotals
     *   When the session lines are built
     *   Then up to a cent per line is rounding, and the largest line absorbs it
     *   And anything more leaves every line as it was and is added as one discount or surcharge line
     *
     * @dataProvider differencesWithTheTotal
     * @covers \Mollie\WooCommerce\ExpressComponent\Rules\SessionLines::fromCart
     *
     * @param list<array{0: string, 1: string}> $expected Type and totalAmount of every line.
     */
    public function testGivesADifferenceBeyondRoundingALineOfItsOwn(string $total, array $expected): void
    {
        $cart = $this->cart(
            [$this->line('Pen', 3, '9.99', '21.00'), $this->line('Cup', 1, '5.00', '21.00')],
            $total
        );

        $lines = SessionLines::fromCart($cart);

        self::assertSame($expected, array_map(
            static fn (array $line): array => [$line['type'], $line['totalAmount']['value']],
            $lines
        ));
    }

    /**
     * @return array<string, array{0: string, 1: list<array{0: string, 1: string}>}>
     */
    public function differencesWithTheTotal(): array
    {
        return [
            'a cent per line above' => ['15.01', [['physical', '10.01'], ['physical', '5.00']]],
            'a cent per line below' => ['14.97', [['physical', '9.97'], ['physical', '5.00']]],
            'one cent more than that, above' => ['15.02', [['physical', '9.99'], ['physical', '5.00'], ['surcharge', '0.03']]],
            'one cent more than that, below' => ['14.96', [['physical', '9.99'], ['physical', '5.00'], ['discount', '-0.03']]],
            'far below, as a balance paid outside the lines' => ['4.99', [['physical', '9.99'], ['physical', '5.00'], ['discount', '-10.00']]],
            'below by more than the largest line' => ['2.99', [['physical', '9.99'], ['physical', '5.00'], ['discount', '-12.00']]],
        ];
    }

    private function money(string $value): Money
    {
        return Money::fromDecimal($value, 'EUR');
    }

    private function line(string $name, int $quantity, string $subtotal, string $vatRate): CartLine
    {
        return new CartLine(
            productId: crc32($name),
            quantity: $quantity,
            isSubscription: false,
            name: $name,
            subtotal: $this->money($subtotal),
            vatRate: $vatRate
        );
    }

    /**
     * @param list<CartLine> $lines
     * @param list<CartFee> $fees
     * @param list<CartCoupon> $coupons
     */
    private function cart(
        array $lines,
        string $total,
        array $fees = [],
        array $coupons = [],
        ?CartShipping $shipping = null
    ): CartFacts {

        return new CartFacts(
            lines: $lines,
            needsShipping: $shipping !== null,
            shippingDestinationComplete: $shipping !== null,
            shippingRateChosen: $shipping !== null,
            total: $this->money($total),
            fees: $fees,
            coupons: $coupons,
            shipping: $shipping,
            cartHash: 'cart-hash',
            destination: $shipping !== null ? 'LU|1234|Town' : ''
        );
    }
}

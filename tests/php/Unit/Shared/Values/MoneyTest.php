<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Shared\Values;

use InvalidArgumentException;
use Mollie\WooCommerce\Shared\Values\Money;
use Mollie\WooCommerceTests\TestCase;

/**
 * Money as currency plus integer minor units: the round trip with Mollie's decimal strings
 * and the guard against mixing currencies.
 *
 * @covers \Mollie\WooCommerce\Shared\Values\Money
 */
class MoneyTest extends TestCase
{
    /**
     * Scenario: a Mollie decimal string survives the round trip through minor units
     *   Given a decimal amount as Mollie writes it, and its currency
     *   When it is parsed into Money and formatted back
     *   Then the minor units are exact
     *   And the formatted string is identical to the one Mollie sent
     *
     * @dataProvider mollieAmounts
     * @covers \Mollie\WooCommerce\Shared\Values\Money::fromDecimal
     * @covers \Mollie\WooCommerce\Shared\Values\Money::minorUnits
     * @covers \Mollie\WooCommerce\Shared\Values\Money::toDecimal
     */
    public function testParsesAndFormatsMollieDecimalStrings(
        string $decimal,
        string $currency,
        int $minorUnits
    ): void {

        $money = Money::fromDecimal($decimal, $currency);

        self::assertSame($minorUnits, $money->minorUnits(), 'Parsing must land on the exact minor unit.');
        self::assertSame($currency, $money->currency());
        self::assertSame($decimal, $money->toDecimal(), 'Formatting must return what Mollie sent, byte for byte.');
        self::assertSame(
            $decimal,
            Money::fromMinorUnits($minorUnits, $currency)->toDecimal(),
            'The same amount built from minor units must format identically.'
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public function mollieAmounts(): array
    {
        return [
            'the documented example' => ['20.00', 'EUR', 2000],
            'a single cent' => ['0.01', 'EUR', 1],
            'zero' => ['0.00', 'EUR', 0],
            'a refund, negative' => ['-5.00', 'EUR', -500],
            'a value whose cents are not round' => ['19.99', 'EUR', 1999],
            'a four figure amount' => ['1234.56', 'EUR', 123456],
            'JPY has no decimals' => ['1200', 'JPY', 1200],
            'a single yen' => ['1', 'JPY', 1],
            'ISK has no decimals either' => ['2500', 'ISK', 2500],
        ];
    }

    /**
     * Scenario: two currencies are never silently mixed
     *   Given twenty euro and twelve hundred yen
     *   When they are added, or compared
     *   Then Money refuses instead of returning a meaningless number
     *
     * @dataProvider crossCurrencyOperations
     * @covers \Mollie\WooCommerce\Shared\Values\Money::add
     * @covers \Mollie\WooCommerce\Shared\Values\Money::compareTo
     * @covers \Mollie\WooCommerce\Shared\Values\Money::equals
     */
    public function testRefusesToAddOrCompareAmountsOfDifferentCurrencies(string $operation): void
    {
        $euro = Money::fromDecimal('20.00', 'EUR');
        $yen = Money::fromDecimal('1200', 'JPY');

        $this->expectException(InvalidArgumentException::class);

        $euro->{$operation}($yen);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function crossCurrencyOperations(): array
    {
        return [
            'adding' => ['add'],
            'comparing' => ['compareTo'],
            'testing for equality' => ['equals'],
        ];
    }

    /**
     * Scenario: the same currency adds and compares as arithmetic
     *   Given two euro amounts
     *   Then adding them is exact, and comparing them orders them
     *
     * @covers \Mollie\WooCommerce\Shared\Values\Money::add
     * @covers \Mollie\WooCommerce\Shared\Values\Money::compareTo
     * @covers \Mollie\WooCommerce\Shared\Values\Money::equals
     */
    public function testAddsAndComparesAmountsOfTheSameCurrency(): void
    {
        $twenty = Money::fromDecimal('20.00', 'EUR');
        $shipping = Money::fromDecimal('4.95', 'EUR');

        self::assertSame('24.95', $twenty->add($shipping)->toDecimal());
        self::assertSame(1, $twenty->compareTo($shipping));
        self::assertSame(-1, $shipping->compareTo($twenty));
        self::assertSame(0, $twenty->compareTo(Money::fromDecimal('20.00', 'EUR')));
        self::assertTrue($twenty->equals(Money::fromDecimal('20.00', 'EUR')));
        self::assertFalse($twenty->equals($shipping));
    }

    /**
     * Scenario: the same amount is the same amount only in the same currency
     *   Given two amounts
     *   When they are compared with isSameAs()
     *   Then equal minor units in the same currency are the same
     *   And a different amount or a different currency is not, without throwing
     */
    public function testIsTheSameAmountOnlyInTheSameCurrency(): void
    {
        $twenty = Money::fromDecimal('20.00', 'EUR');

        self::assertTrue($twenty->isSameAs(Money::fromMinorUnits(2000, 'EUR')));
        self::assertFalse($twenty->isSameAs(Money::fromDecimal('20.01', 'EUR')));
        self::assertFalse($twenty->isSameAs(Money::fromDecimal('20.00', 'USD')));
    }
}

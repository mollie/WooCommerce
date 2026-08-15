<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Functional\Payment;

use Mockery;
use Mollie\WooCommerce\Payment\LineItems\PaymentLines;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerceTests\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * Fast unit guard for the shared price/VAT accessor (LineItemPriceCalculationTrait::getMolliePrice).
 *
 * getMolliePrice() treats its input as an already-gross (VAT-inclusive) amount and derives vatAmount
 * from it, so vatAmount == grossPrice * vatRate/(100+vatRate) holds by construction. It must NOT
 * re-apply tax: its callers get_item_price()/get_item_total_amount() are always gross regardless of the
 * shop's tax-entry setting, so re-grossing double-taxed tax-exclusive shops (PIWOO-931 regression).
 * End-to-end coverage against real taxed WC orders lives in
 * tests/Integration/spec/Payment/PaymentLinesTest.php.
 *
 * @covers \Mollie\WooCommerce\Payment\LineItems\PaymentLines::getMolliePrice
 * @covers \Mollie\WooCommerce\Payment\LineItems\LineItemPriceCalculationTrait::getMolliePrice
 */
class PaymentLinesTest extends TestCase
{
    /** @var Data&\Mockery\MockInterface */
    private $dataHelper;
    private PaymentLines $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataHelper = Mockery::mock(Data::class);
        $this->sut = new PaymentLines($this->dataHelper, 'mollie-test');
        // The gross input must pass through unchanged even in a tax-exclusive shop.
        when('wc_prices_include_tax')->justReturn(false);
    }

    /**
     *   Given a line's gross (VAT-inclusive) price and a single configured VAT rate
     *   When PaymentLines derives the Mollie gross price and VAT amount
     *   Then grossPrice is returned unchanged (no re-grossing) and vatAmount == grossPrice * rate/(100+rate)
     *
     * @dataProvider provide_gross_price_and_rate
     * @covers \Mollie\WooCommerce\Payment\LineItems\PaymentLines::getMolliePrice
     */
    public function test_get_mollie_price_passes_gross_through_and_derives_vat(
        float $grossPrice,
        float $vatRate,
        float $expectedGross,
        float $expectedVat
    ): void {
        // Arrange / When
        $result = $this->callPrivate($this->sut, 'getMolliePrice', $grossPrice, $vatRate);

        // Then — gross is passed through unchanged
        self::assertEqualsWithDelta($expectedGross, $result['grossPrice'], 1e-9);
        self::assertEqualsWithDelta($expectedVat, $result['vatAmount'], 1e-9);
        // The invariant Mollie enforces server-side.
        self::assertEqualsWithDelta(
            $result['grossPrice'] * ($vatRate / (100 + $vatRate)),
            $result['vatAmount'],
            1e-9,
            'vatAmount must equal grossPrice * vatRate/(100+vatRate).'
        );
    }

    public function provide_gross_price_and_rate(): array
    {
        return [
            // gross in, rate, expected gross (unchanged), expected vat
            'gross 121 @21%'        => [121.0, 21.0, 121.0, 21.0],
            'gross 99 @25%'         => [99.0, 25.0, 99.0, 19.8],
        ];
    }

    /**
     *   Given a 0% VAT line
     *   When PaymentLines derives the Mollie price
     *   Then the price is returned unchanged and vatAmount is 0, so no VAT mismatch is reintroduced
     *
     * @covers \Mollie\WooCommerce\Payment\LineItems\PaymentLines::getMolliePrice
     */
    public function test_get_mollie_price_zero_rate_yields_zero_vat_amount(): void
    {
        // Arrange / When
        $result = $this->callPrivate($this->sut, 'getMolliePrice', 80.0, 0.0);

        // Then
        self::assertEqualsWithDelta(80.0, $result['grossPrice'], 1e-9);
        self::assertSame(0.0, (float) $result['vatAmount']);
    }

    private function callPrivate(object $obj, string $method, ...$args)
    {
        $reflection = new \ReflectionMethod($obj, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke($obj, ...$args);
    }
}

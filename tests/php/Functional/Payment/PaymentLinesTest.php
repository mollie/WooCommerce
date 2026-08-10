<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Functional\Payment;

use Mockery;
use Mollie\WooCommerce\Payment\LineItems\PaymentLines;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerceTests\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * Fast unit guard for the price/VAT accessor that PIWOO-931 ports from OrderLines into PaymentLines.
 *
 * getMolliePrice() derives grossPrice and vatAmount together from a single vatRate, so the two values
 * always satisfy Mollie's cross-field rule vatAmount == grossPrice * vatRate/(100+vatRate) by construction
 * — the property the old self-division approach (round(line_tax/line_total,4)*100) could not guarantee.
 * End-to-end coverage against a real taxed WC order lives in
 * tests/Integration/spec/Payment/PaymentLinesTest.php.
 *
 * @covers \Mollie\WooCommerce\Payment\LineItems\PaymentLines::getMolliePrice
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
        // getMolliePrice() treats wcPrice as the net amount when prices exclude tax.
        when('wc_prices_include_tax')->justReturn(false);
    }

    /**
     * PIWOO-931
     *   Given a line's net price and a single configured VAT rate
     *   When PaymentLines derives the Mollie gross price and VAT amount
     *   Then vatAmount equals grossPrice * rate/(100+rate), the equation Mollie validates
     *
     * @dataProvider provide_net_price_and_rate
     * @covers \Mollie\WooCommerce\Payment\LineItems\PaymentLines::getMolliePrice
     */
    public function test_get_mollie_price_satisfies_mollie_validation_formula(
        float $netPrice,
        float $vatRate,
        float $expectedGross,
        float $expectedVat
    ): void {
        // Arrange / When
        $result = $this->callPrivate($this->sut, 'getMolliePrice', $netPrice, $vatRate);

        // Then
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

    public function provide_net_price_and_rate(): array
    {
        return [
            // net, rate, expected gross, expected vat
            'default 21% clean'        => [100.0, 21.0, 121.0, 21.0],
            'DKK 25% non-clean net'    => [79.2, 25.0, 99.0, 19.8],
        ];
    }

    /**
     * PIWOO-931 (guards the PIWOO-516 Germanized/Billie B2B scenario)
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
        return (new \ReflectionMethod($obj, $method))->invoke($obj, ...$args);
    }
}
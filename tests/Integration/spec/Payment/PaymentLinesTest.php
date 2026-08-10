<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Payment;

use Mockery;
use Mollie\WooCommerce\Payment\LineItems\PaymentLines;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product_Simple;
use WC_Tax;

/**
 * End-to-end coverage for the Payments API line items produced by PaymentLines, against a real
 * taxed WooCommerce order.
 *
 * Reproduces PIWOO-931: with wc_get_price_decimals()=0 (DKK/SEK-style shops) WooCommerce rounds the
 * per-line tax, so the old self-divided vatRate (round(line_tax/line_total,4)*100) drifts away from the
 * configured WC_Tax rate. The emitted vatRate / totalAmount / vatAmount then stop satisfying Mollie's
 * cross-field validation (vatAmount == totalAmount * vatRate/(100+vatRate)) and the API returns 422.
 * The fix reads the rate from the configured WooCommerce tax rate and derives gross/VAT together.
 *
 * The expected rate is read from the store's configured standard tax rate rather than hardcoded, so the
 * test is agnostic to the ambient tax configuration of the integration environment (e.g. a 19% DE rate).
 *
 * @group integration
 * @group payment-lines
 * @covers \Mollie\WooCommerce\Payment\LineItems\PaymentLines
 */
class PaymentLinesTest extends IntegrationMockedTestCase
{
    private const TOLERANCE = 0.01; // Mollie validates to the currency's minor unit.

    private int $originalDecimals = 2;
    /** @var array<string, mixed> */
    private array $originalTaxOptions = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->originalDecimals = (int) get_option('woocommerce_price_num_decimals', 2);
        $this->originalTaxOptions = [
            'woocommerce_calc_taxes' => get_option('woocommerce_calc_taxes'),
            'woocommerce_prices_include_tax' => get_option('woocommerce_prices_include_tax'),
            'woocommerce_tax_round_at_subtotal' => get_option('woocommerce_tax_round_at_subtotal'),
        ];

        // Deterministic arithmetic: taxes on, product prices entered net (excluding tax), and tax rounded
        // per line (WooCommerce's default) so a low price-decimals setting actually rounds line_tax — the
        // condition under which the old self-divided vatRate drifts from the configured rate.
        update_option('woocommerce_calc_taxes', 'yes');
        update_option('woocommerce_prices_include_tax', 'no');
        update_option('woocommerce_tax_round_at_subtotal', 'no');
    }

    public function tearDown(): void
    {
        update_option('woocommerce_price_num_decimals', $this->originalDecimals);
        foreach ($this->originalTaxOptions as $key => $value) {
            update_option($key, $value);
        }

        parent::tearDown();
    }

    /**
     * PIWOO-931
     *   Given a taxed product in a 0-decimal shop where WooCommerce rounds the per-line tax
     *   When PaymentLines builds the Payments API line items
     *   Then vatRate is the configured tax rate (not the drifted round(line_tax/line_total,4)*100)
     *        and every line satisfies Mollie's vatAmount == totalAmount * rate/(100+rate)
     *
     * @test
     * @group payment-lines
     * @covers \Mollie\WooCommerce\Payment\LineItems\PaymentLines::order_lines
     */
    public function test_it_emits_mollie_valid_lines_for_taxed_product_at_zero_price_decimals(): void
    {
        // Arrange
        update_option('woocommerce_price_num_decimals', 0);
        $expectedRate = $this->configuredStandardVatRate();
        self::assertGreaterThan(
            0.0,
            $expectedRate,
            'Precondition: the integration store must have a positive standard tax rate configured.'
        );
        $order = $this->createTaxedOrder('999', 'DKK', 'taxable');

        // When
        $lines = $this->makeSut()->order_lines($order);

        // Then — configured rate, not the drifted self-division (round(line_tax/line_total,4)*100).
        self::assertEqualsWithDelta(
            $expectedRate,
            (float) $lines[0]['vatRate'],
            1e-9,
            'vatRate must equal the configured WC_Tax rate, not round(line_tax/line_total,4)*100.'
        );
        $this->assertEveryTaxedLineSatisfiesMollieFormula($lines);
    }

    /**
     * PIWOO-931 (no regression on the previously-working case)
     *   Given a taxed product in a default 2-decimal shop with a cleanly dividing price
     *   When PaymentLines builds the Payments API line items
     *   Then vatRate is the configured rate and every line still satisfies Mollie's validation formula
     *
     * @test
     * @group payment-lines
     * @covers \Mollie\WooCommerce\Payment\LineItems\PaymentLines::order_lines
     */
    public function test_it_emits_mollie_valid_lines_for_taxed_product_at_default_price_decimals(): void
    {
        // Arrange
        update_option('woocommerce_price_num_decimals', 2);
        $expectedRate = $this->configuredStandardVatRate();
        self::assertGreaterThan(0.0, $expectedRate);
        $order = $this->createTaxedOrder('100.00', 'EUR', 'taxable');

        // When
        $lines = $this->makeSut()->order_lines($order);

        // Then
        self::assertEqualsWithDelta($expectedRate, (float) $lines[0]['vatRate'], 1e-9);
        $this->assertEveryTaxedLineSatisfiesMollieFormula($lines);
    }

    /**
     * PIWOO-931 (guards the PIWOO-516 Germanized/Billie B2B scenario)
     *   Given a non-taxable (0% VAT) product
     *   When PaymentLines builds the Payments API line items
     *   Then the line's vatRate is 0 and vatAmount is 0, without reintroducing the original VAT mismatch
     *
     * @test
     * @group payment-lines
     * @covers \Mollie\WooCommerce\Payment\LineItems\PaymentLines::order_lines
     */
    public function test_it_emits_zero_vat_line_for_zero_rate_b2b_product(): void
    {
        // Arrange
        update_option('woocommerce_price_num_decimals', 2);
        $order = $this->createTaxedOrder('100.00', 'EUR', 'none');

        // When
        $lines = $this->makeSut()->order_lines($order);

        // Then
        self::assertEqualsWithDelta(0.0, (float) $lines[0]['vatRate'], 1e-9);
        self::assertEqualsWithDelta(0.0, (float) $lines[0]['vatAmount']['value'], 1e-9);
    }

    /**
     * Every line carrying a positive VAT rate must satisfy the exact equation Mollie validates
     * server-side: vatAmount == totalAmount * vatRate/(100+vatRate).
     *
     * @param array<int, array<string, mixed>> $lines
     */
    private function assertEveryTaxedLineSatisfiesMollieFormula(array $lines): void
    {
        foreach ($lines as $index => $line) {
            $vatRate = (float) $line['vatRate'];
            if ($vatRate <= 0) {
                continue;
            }
            $total = (float) $line['totalAmount']['value'];
            $vat = (float) $line['vatAmount']['value'];
            $expected = round($total * $vatRate / (100 + $vatRate), 2);

            self::assertEqualsWithDelta(
                $expected,
                $vat,
                self::TOLERANCE,
                sprintf(
                    'Line %d violates Mollie validation: vatAmount %.4f != totalAmount %.4f * %.4f/(100+%.4f).',
                    (int) $index,
                    $vat,
                    $total,
                    $vatRate,
                    $vatRate
                )
            );
        }
    }

    /**
     * The store's configured standard-class VAT rate, summed the same way the SUT reads it, so the
     * assertion tracks the ambient tax configuration instead of a hardcoded value.
     */
    private function configuredStandardVatRate(): float
    {
        $rate = 0.0;
        foreach ((new WC_Tax())->get_rates('') as $configured) {
            if (isset($configured['rate'])) {
                $rate += (float) $configured['rate'];
            }
        }

        return round($rate, 2);
    }

    private function createTaxedOrder(string $netPrice, string $currency, string $taxStatus): WC_Order
    {
        $product = new WC_Product_Simple();
        $product->set_name('PIWOO-931 Product');
        $product->set_sku('PIWOO_931_' . uniqid());
        $product->set_regular_price($netPrice);
        $product->set_tax_status($taxStatus); // 'taxable' → standard class; 'none' → no VAT
        $product->set_tax_class('');
        $product->set_status('publish');
        $product->save();

        $order = wc_create_order(['customer_id' => $this->customer_id]);
        $order->set_currency($currency);
        $order->set_payment_method('mollie_wc_gateway_creditcard');

        $item = new WC_Order_Item_Product();
        $item->set_product($product);
        $item->set_quantity(1);
        // WooCommerce derives line_tax from these net line totals during calculate_totals().
        $item->set_subtotal((float) $netPrice);
        $item->set_total((float) $netPrice);
        $order->add_item($item);

        $order->calculate_totals(true);
        $order->save();

        return $order;
    }

    private function makeSut(): PaymentLines
    {
        $data = Mockery::mock(Data::class);
        $data->shouldReceive('getOrderCurrency')->andReturnUsing(static function ($order) {
            return $order->get_currency();
        });
        $data->shouldReceive('formatCurrencyValue')->andReturnUsing(static function ($value, $currency) {
            return mollieWooCommerceFormatCurrencyValue($value, $currency);
        });

        return new PaymentLines($data, 'mollie-payments-for-woocommerce');
    }
}
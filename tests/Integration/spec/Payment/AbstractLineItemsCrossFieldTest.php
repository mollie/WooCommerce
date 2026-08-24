<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Payment;

use Mockery;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product_Simple;
use WC_Tax;

/**
 * Shared end-to-end coverage for the Mollie line-item builders (PaymentLines = Payments API,
 * OrderLines = Orders API), which share LineItemPriceCalculationTrait. Each concrete subclass wires
 * buildLines() to one builder, so every scenario below runs against BOTH — a bug in the shared trait
 * is a bug in both APIs (PIWOO-931).
 *
 * Every emitted line must satisfy the two cross-field rules Mollie validates server-side (no documented
 * tolerance — "any deviations will result in an error"):
 *   Rule 1: vatAmount   == round(totalAmount * vatRate/(100+vatRate), prec)
 *   Rule 2: totalAmount == round(unitPrice * quantity - discountAmount, prec)
 * plus the order-level identity sum(line totalAmount) == round(order->get_total(), prec) that
 * process_mismatch() reconciles. prec = 0 for JPY/ISK, else 2.
 *
 * @group integration
 * @group payment-lines
 */
abstract class AbstractLineItemsCrossFieldTest extends IntegrationMockedTestCase
{
    private int $originalDecimals = 2;
    /** @var array<string, mixed> */
    private array $originalTaxOptions = [];

    /**
     * Build the Mollie line items for the given order through the builder under test.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract protected function buildLines(WC_Order $order): array;

    public function setUp(): void
    {
        parent::setUp();

        $this->originalDecimals = (int) get_option('woocommerce_price_num_decimals', 2);
        $this->originalTaxOptions = [
            'woocommerce_calc_taxes' => get_option('woocommerce_calc_taxes'),
            'woocommerce_prices_include_tax' => get_option('woocommerce_prices_include_tax'),
            'woocommerce_tax_round_at_subtotal' => get_option('woocommerce_tax_round_at_subtotal'),
        ];

        // Deterministic arithmetic: taxes on, prices entered net, per-line tax rounding (WC default).
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
     * Scenario: a tax-exclusive shop must tax each line exactly once.
     *   Given prices are entered excluding tax and a net-100 product at the configured standard rate
     *   When the builder produces the Mollie line items
     *   Then the unitPrice is the single-taxed gross (e.g. 119.00 at 19%), never the double-taxed 141.61,
     *        and both cross-field rules and the order total hold.
     *
     * @test
     */
    public function test_tax_exclusive_line_is_taxed_once(): void
    {
        update_option('woocommerce_price_num_decimals', 2);
        update_option('woocommerce_prices_include_tax', 'no');
        $rate = $this->configuredStandardVatRate();
        self::assertGreaterThan(0.0, $rate, 'Precondition: store must have a positive standard tax rate.');

        $order = $this->createTaxedOrder('100.00', 1, 'EUR', 'taxable');
        $lines = $this->buildLines($order);

        $expectedUnit = round(100.0 * (1 + $rate / 100), 2);
        self::assertEqualsWithDelta(
            $expectedUnit,
            (float) $lines[0]['unitPrice']['value'],
            1e-9,
            'unitPrice must be single-taxed (net*(1+rate)), not double-taxed.'
        );
        self::assertEqualsWithDelta($expectedUnit, (float) $lines[0]['totalAmount']['value'], 1e-9);
        $this->assertMollieCrossFieldRules($lines, 'EUR');
        $this->assertOrderReconciles($lines, $order, 'EUR');
        $this->assertNoLargeMismatchLine($lines, $order);
    }

    /**
     * Scenario: unitPrice * quantity must equal totalAmount even at 4 price-decimals with quantity > 1.
     *   Given woocommerce_price_num_decimals = 4, a non-cleanly-dividing net unit and quantity 3
     *   When the builder produces the Mollie line items
     *   Then totalAmount == round(unitPrice * quantity - discountAmount, 2) exactly.
     *
     * @test
     */
    public function test_unit_times_qty_equals_total_at_four_decimals(): void
    {
        update_option('woocommerce_price_num_decimals', 4);
        $order = $this->createTaxedOrder('33.3333', 3, 'EUR', 'taxable');
        $lines = $this->buildLines($order);

        $this->assertMollieCrossFieldRules($lines, 'EUR');
        $this->assertOrderReconciles($lines, $order, 'EUR');
    }

    /**
     * Scenario: a tax-inclusive shop is unchanged by the fix.
     *   Given prices are entered including tax
     *   When the builder produces the Mollie line items
     *   Then unitPrice is the single-taxed gross and both rules hold (no new drift).
     *
     * @test
     */
    public function test_tax_inclusive_line_unchanged(): void
    {
        update_option('woocommerce_price_num_decimals', 2);
        update_option('woocommerce_prices_include_tax', 'yes');
        $rate = $this->configuredStandardVatRate();

        // WC_Order_Item::set_subtotal() always stores the net line amount, regardless of the store's
        // tax-entry setting; the 'yes' option only flips wc_prices_include_tax(). So a net-100 product
        // still yields a single-taxed gross of net*(1+rate) — the value the fix must preserve here.
        $order = $this->createTaxedOrder('100.00', 1, 'EUR', 'taxable');
        $lines = $this->buildLines($order);

        self::assertEqualsWithDelta(
            round(100.0 * (1 + $rate / 100), 2),
            (float) $lines[0]['unitPrice']['value'],
            1e-9,
            'Tax-inclusive unitPrice must remain the single-taxed gross.'
        );
        $this->assertMollieCrossFieldRules($lines, 'EUR');
        $this->assertOrderReconciles($lines, $order, 'EUR');
    }

    /**
     * Scenario: at 0 price-decimals the vatRate is the configured WC_Tax rate, not the drifted self-division.
     *
     * @test
     */
    public function test_zero_price_decimals_uses_configured_rate(): void
    {
        update_option('woocommerce_price_num_decimals', 0);
        $rate = $this->configuredStandardVatRate();
        self::assertGreaterThan(0.0, $rate);

        $order = $this->createTaxedOrder('999', 1, 'DKK', 'taxable');
        $lines = $this->buildLines($order);

        self::assertEqualsWithDelta(
            $rate,
            (float) $lines[0]['vatRate'],
            1e-9,
            'vatRate must equal the configured WC_Tax rate, not round(line_tax/line_total,4)*100.'
        );
        $this->assertMollieCrossFieldRules($lines, 'DKK');
        $this->assertOrderReconciles($lines, $order, 'DKK');
    }

    /**
     * Scenario: the previously-working default 2-decimal case is unchanged.
     *
     * @test
     */
    public function test_default_two_decimals_no_regression(): void
    {
        update_option('woocommerce_price_num_decimals', 2);
        $rate = $this->configuredStandardVatRate();

        $order = $this->createTaxedOrder('100.00', 1, 'EUR', 'taxable');
        $lines = $this->buildLines($order);

        self::assertEqualsWithDelta($rate, (float) $lines[0]['vatRate'], 1e-9);
        $this->assertMollieCrossFieldRules($lines, 'EUR');
        $this->assertOrderReconciles($lines, $order, 'EUR');
    }

    /**
     * Scenario: rounding drift with quantity 3 still reconciles at the order level.
     *
     * @test
     */
    public function test_rounding_drift_qty3_reconciles(): void
    {
        update_option('woocommerce_price_num_decimals', 2);
        $order = $this->createTaxedOrder('9.9999', 3, 'EUR', 'taxable');
        $lines = $this->buildLines($order);

        $this->assertMollieCrossFieldRules($lines, 'EUR');
        $this->assertOrderReconciles($lines, $order, 'EUR');
    }

    /**
     * Scenario: a zero-rate B2B product (PIWOO-516) emits vatRate 0 / vatAmount 0, single-taxed.
     *
     * @test
     */
    public function test_zero_rate_b2b_line_single_taxed(): void
    {
        update_option('woocommerce_price_num_decimals', 2);
        $order = $this->createTaxedOrder('100.00', 1, 'EUR', 'none');
        $lines = $this->buildLines($order);

        self::assertEqualsWithDelta(0.0, (float) $lines[0]['vatRate'], 1e-9);
        self::assertEqualsWithDelta(0.0, (float) $lines[0]['vatAmount']['value'], 1e-9);
        self::assertEqualsWithDelta(100.0, (float) $lines[0]['unitPrice']['value'], 1e-9);
        $this->assertMollieCrossFieldRules($lines, 'EUR');
        $this->assertOrderReconciles($lines, $order, 'EUR');
    }

    /**
     * Scenario: a zero-decimal currency (JPY) emits whole-number amounts satisfying both rules at prec 0.
     *
     * @test
     */
    public function test_zero_decimal_currency_jpy(): void
    {
        update_option('woocommerce_price_num_decimals', 2);
        $order = $this->createTaxedOrder('100.00', 1, 'JPY', 'taxable');
        $lines = $this->buildLines($order);

        foreach (['unitPrice', 'totalAmount', 'vatAmount'] as $field) {
            self::assertStringNotContainsString(
                '.',
                (string) $lines[0][$field]['value'],
                "JPY {$field} must be a whole number (0 decimals)."
            );
        }
        $this->assertMollieCrossFieldRules($lines, 'JPY');
        $this->assertOrderReconciles($lines, $order, 'JPY');
    }

    /**
     * Scenario: a discounted line (line_subtotal > line_total) has rule 2 account for discountAmount.
     *
     * @test
     */
    public function test_discounted_line_accounts_for_discount_in_rule2(): void
    {
        update_option('woocommerce_price_num_decimals', 2);
        // net subtotal 100 * 3 = 300, discounted total 270 (30 net discount) → line_subtotal > line_total.
        $order = $this->createTaxedOrder('100.00', 3, 'EUR', 'taxable', 30.0);
        $lines = $this->buildLines($order);

        self::assertGreaterThan(
            0.0,
            (float) $lines[0]['discountAmount']['value'],
            'Precondition: the line must carry a positive discountAmount.'
        );
        $this->assertMollieCrossFieldRules($lines, 'EUR');
        $this->assertOrderReconciles($lines, $order, 'EUR');
    }

    // --- helpers -----------------------------------------------------------------------------------

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    protected function assertMollieCrossFieldRules(array $lines, string $currency): void
    {
        $prec = in_array($currency, ['JPY', 'ISK'], true) ? 0 : 2;

        foreach ($lines as $index => $line) {
            $type = (string) ($line['type'] ?? '');
            $rate = (float) $line['vatRate'];
            $unit = (float) $line['unitPrice']['value'];
            $total = (float) $line['totalAmount']['value'];
            $vat = (float) $line['vatAmount']['value'];
            $qty = (float) ($line['quantity'] ?? 1);
            $discount = isset($line['discountAmount']) ? (float) $line['discountAmount']['value'] : 0.0;

            // Rule 1 — skip pure-discount lines, which intentionally force vatAmount to 0.
            if ($rate > 0 && $type !== 'discount') {
                self::assertEqualsWithDelta(
                    round($total * $rate / (100 + $rate), $prec),
                    $vat,
                    1e-9,
                    sprintf('Line %d violates Mollie rule 1 (vatAmount == totalAmount*rate/(100+rate)).', (int) $index)
                );
            }

            // Rule 2 — totalAmount == unitPrice*quantity - discountAmount.
            self::assertEqualsWithDelta(
                round($unit * $qty - $discount, $prec),
                $total,
                1e-9,
                sprintf('Line %d violates Mollie rule 2 (totalAmount == unitPrice*quantity - discountAmount).', (int) $index)
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    protected function assertOrderReconciles(array $lines, WC_Order $order, string $currency): void
    {
        $prec = in_array($currency, ['JPY', 'ISK'], true) ? 0 : 2;
        $sum = 0.0;
        foreach ($lines as $line) {
            $sum += (float) $line['totalAmount']['value'];
        }
        self::assertEqualsWithDelta(
            round((float) $order->get_total(), $prec),
            round($sum, $prec),
            1e-9,
            'sum(line totalAmount) must equal the order total after process_mismatch().'
        );
    }

    /**
     * After the double-tax fix the reconciliation line, if any, must be a small rounding residual —
     * never a large VAT-sized correction masking a doubled line.
     *
     * @param array<int, array<string, mixed>> $lines
     */
    protected function assertNoLargeMismatchLine(array $lines, WC_Order $order): void
    {
        foreach ($lines as $line) {
            if (($line['description'] ?? $line['name'] ?? '') === __('Rounding difference', 'mollie-payments-for-woocommerce')) {
                self::assertLessThanOrEqual(
                    0.05,
                    abs((float) $line['totalAmount']['value']),
                    'Reconciliation line must be a small rounding residual, not a VAT-sized masking discount.'
                );
            }
        }
    }

    protected function configuredStandardVatRate(): float
    {
        $rate = 0.0;
        foreach ((new WC_Tax())->get_rates('') as $configured) {
            if (isset($configured['rate'])) {
                $rate += (float) $configured['rate'];
            }
        }

        return round($rate, 2);
    }

    protected function createTaxedOrder(
        string $netPrice,
        int $qty,
        string $currency,
        string $taxStatus,
        float $netDiscount = 0.0
    ): WC_Order {
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
        $item->set_quantity($qty);
        // subtotal = full net line; total = discounted net line (subtotal > total ⇒ a discount).
        $item->set_subtotal((float) $netPrice * $qty);
        $item->set_total((float) $netPrice * $qty - $netDiscount);
        $order->add_item($item);

        $order->calculate_totals(true);
        $order->save();

        return $order;
    }

    protected function makeDataMock(): Data
    {
        $data = Mockery::mock(Data::class);
        $data->shouldReceive('getOrderCurrency')->andReturnUsing(static function ($order) {
            return $order->get_currency();
        });
        $data->shouldReceive('formatCurrencyValue')->andReturnUsing(static function ($value, $currency) {
            return mollieWooCommerceFormatCurrencyValue($value, $currency);
        });

        return $data;
    }
}

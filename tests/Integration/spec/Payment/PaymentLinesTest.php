<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Payment;

use Mollie\WooCommerce\Payment\LineItems\PaymentLines;
use WC_Order;

/**
 * End-to-end coverage for the Payments API line items produced by PaymentLines, against real taxed
 * WooCommerce orders. All scenarios live in AbstractLineItemsCrossFieldTest and run against both
 * builders; this subclass binds them to PaymentLines (PIWOO-931).
 *
 * @group integration
 * @group payment-lines
 * @covers \Mollie\WooCommerce\Payment\LineItems\PaymentLines
 * @covers \Mollie\WooCommerce\Payment\LineItems\LineItemPriceCalculationTrait
 */
class PaymentLinesTest extends AbstractLineItemsCrossFieldTest
{
    protected function buildLines(WC_Order $order): array
    {
        return (new PaymentLines($this->makeDataMock(), 'mollie-payments-for-woocommerce'))->order_lines($order);
    }
}
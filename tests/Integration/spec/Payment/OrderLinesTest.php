<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Payment;

use Mollie\WooCommerce\Payment\LineItems\OrderLines;
use WC_Order;

/**
 * End-to-end coverage for the Orders API line items produced by OrderLines, against real taxed
 * WooCommerce orders. All scenarios live in AbstractLineItemsCrossFieldTest and run against both
 * builders; this subclass binds them to OrderLines, which had no integration coverage before and
 * shares the same LineItemPriceCalculationTrait arithmetic (PIWOO-931).
 *
 * @group integration
 * @group payment-lines
 * @covers \Mollie\WooCommerce\Payment\LineItems\OrderLines
 * @covers \Mollie\WooCommerce\Payment\LineItems\LineItemPriceCalculationTrait
 */
class OrderLinesTest extends AbstractLineItemsCrossFieldTest
{
    protected function buildLines(WC_Order $order): array
    {
        return (new OrderLines($this->makeDataMock(), 'mollie-payments-for-woocommerce'))->order_lines($order);
    }
}
<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Decorator;

use Mollie\WooCommerce\Payment\OrderLines;
use Mollie\WooCommerce\Payment\Request\Decorators\RequestDecoratorInterface;
use WC_Order;

class OrderLinesDecorator implements RequestDecoratorInterface
{
    private OrderLines $orderLines;
    private string $voucherDefaultCategory;

    public function __construct($orderLines, $voucherDefaultCategory)
    {
        $this->orderLines = $orderLines;
        $this->voucherDefaultCategory = $voucherDefaultCategory;
    }

    public function decorate(array $requestData, WC_Order $order): array
    {
        $orderLines = $this->orderLines->order_lines($order, $this->voucherDefaultCategory);
        $requestData['lines'] = $orderLines['lines'];
        return $requestData;
    }
}

<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use Mollie\WooCommerce\Payment\OrderLines;
use WC_Order;

class OrderLinesMiddleware implements RequestMiddlewareInterface
{
    private OrderLines $orderLines;
    private string $voucherDefaultCategory;

    public function __construct($orderLines, $voucherDefaultCategory)
    {
        $this->orderLines = $orderLines;
        $this->voucherDefaultCategory = $voucherDefaultCategory;
    }

    public function __invoke(array $requestData, WC_Order $order, $context = null, $next): array
    {
        $orderLines = $this->orderLines->order_lines($order, $this->voucherDefaultCategory);
        $requestData['lines'] = $orderLines['lines'];
        return $next($requestData, $order, $context);
    }
}

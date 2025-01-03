<?php

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use WC_Order;

interface RequestMiddlewareInterface
{
    public function __invoke(array $requestData, WC_Order $order, string $context = null, callable $next): array;
}

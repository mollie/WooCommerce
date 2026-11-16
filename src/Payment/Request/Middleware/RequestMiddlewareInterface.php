<?php

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use WC_Order;
interface RequestMiddlewareInterface
{
    /**
     * Invoke the middleware.
     *
     * @param array $requestData The request data to be modified.
     * @param WC_Order $order The WooCommerce order object.
     * @param string $context Additional context for the middleware.
     * @param callable $next The next middleware to be called.
     * @return array The modified request data.
     */
    public function __invoke(array $requestData, WC_Order $order, string $context, callable $next): array;
}

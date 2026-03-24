<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use Mollie\WooCommerce\Payment\LineItems\LineItemProvider;
use WC_Order;

/**
 * Class OrderLinesMiddleware
 *
 * Middleware to handle order lines in the request.
 *
 * @package Mollie\WooCommerce\Payment\Request\Middleware
 */
class OrderLinesMiddleware implements RequestMiddlewareInterface
{
    /**
     * @var LineItemProvider The order lines handler (Orders API context).
     */
    private LineItemProvider $orderLines;

    /**
     * @var LineItemProvider The payment lines handler (Payments API context).
     */
    private LineItemProvider $paymentLines;

    public function __construct(LineItemProvider $orderLines, LineItemProvider $paymentLines)
    {
        $this->orderLines = $orderLines;
        $this->paymentLines = $paymentLines;
    }

    /**
     * Invoke the middleware.
     *
     * @param array $requestData The request data.
     * @param WC_Order $order The WooCommerce order object.
     * @param string $context The context of the request.
     * @param callable $next The next middleware to call.
     * @return array The modified request data.
     */
    public function __invoke(array $requestData, WC_Order $order, $context, $next): array
    {
        $methodId = $requestData['method'] ?? '';
        $optionName = 'mollie_wc_gateway_' . $methodId . '_settings';
        $hideOrderLines = get_option($optionName, false)['hide_order_lines'] === 'yes';

        if (!$hideOrderLines) {
            if ($context === 'payment') {
                $requestData['lines'] = $this->paymentLines->order_lines($order);
            } else {
                $requestData['lines'] = $this->orderLines->order_lines($order);
            }
        }
        return $next($requestData, $order, $context);
    }
}

<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Request\Middleware;

use Mollie\WooCommerce\Payment\OrderLines;
use Mollie\WooCommerce\Payment\PaymentLines;
use WC_Order;
/**
 * Class OrderLinesMiddleware
 *
 * Middleware to handle order lines in the request.
 *
 * @package Mollie\WooCommerce\Payment\Request\Middleware
 */
class OrderLinesMiddleware implements \Mollie\WooCommerce\Payment\Request\Middleware\RequestMiddlewareInterface
{
    /**
     * @var OrderLines The order lines handler.
     */
    private OrderLines $orderLines;
    /**
     * @var PaymentLines The payment lines handler.
     */
    private PaymentLines $paymentLines;
    /**
     * OrderLinesMiddleware constructor.
     *
     * @param OrderLines $orderLines The order lines handler.
     */
    public function __construct(OrderLines $orderLines, PaymentLines $paymentLines)
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
        if ($context === 'payment') {
            $orderLines = $this->paymentLines->order_lines($order);
        } else {
            $orderLines = $this->orderLines->order_lines($order);
        }
        $requestData['lines'] = $orderLines['lines'];
        return $next($requestData, $order, $context);
    }
}

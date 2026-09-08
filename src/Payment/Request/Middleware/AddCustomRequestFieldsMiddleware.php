<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Request\Middleware;

use Mollie\Psr\Container\ContainerInterface;
use WC_Order;
/**
 * Class AddCustomRequestFieldsMiddleware
 *
 * This middleware adds custom request fields to the payment request data.
 *
 * @package Mollie\WooCommerce\Payment\Request\Middleware
 */
class AddCustomRequestFieldsMiddleware implements \Mollie\WooCommerce\Payment\Request\Middleware\RequestMiddlewareInterface
{
    private array $paymentMethods;
    private ContainerInterface $container;
    /**
     * AddCustomRequestFieldsMiddleware constructor.
     *
     * @param array $paymentMethods An array of available payment methods.
     * @param ContainerInterface $container A container for dependency injection.
     */
    public function __construct($paymentMethods, $container)
    {
        $this->paymentMethods = $paymentMethods;
        $this->container = $container;
    }
    /**
     * Invoke the middleware.
     *
     * @param array $requestData The request data to be modified.
     * @param WC_Order $order The WooCommerce order object.
     * @param mixed $context Additional context for the middleware.
     * @param callable $next The next middleware to be called.
     * @return array The modified request data.
     */
    public function __invoke(array $requestData, WC_Order $order, $context, $next): array
    {
        $gateway = wc_get_payment_gateway_by_order($order);
        $methodId = substr($gateway->id, strrpos($gateway->id, '_') + 1);
        $paymentMethod = $this->paymentMethods[$methodId];
        if (property_exists($paymentMethod, 'paymentAPIfields')) {
            $paymentAPIfields = $paymentMethod->paymentAPIfields;
            foreach ($paymentAPIfields as $field) {
                $middlewareClass = 'Mollie\WooCommerce\Payment\Request\Middleware' . $field;
                if (class_exists($middlewareClass)) {
                    $middleware = $this->container->get($middlewareClass);
                    if ($middleware instanceof \Mollie\WooCommerce\Payment\Request\Middleware\RequestMiddlewareInterface) {
                        $requestData = $middleware->__invoke($requestData, $order, $context, $next);
                    }
                }
            }
        }
        return $next($requestData, $order, $context);
    }
}

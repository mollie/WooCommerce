<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use Psr\Container\ContainerInterface;
use WC_Order;

class AddCustomRequestFieldsMiddleware implements RequestMiddlewareInterface
{
    private array $paymentMethods;
    private ContainerInterface $container;

    public function __construct($paymentMethods, $container)
    {
        $this->paymentMethods = $paymentMethods;
        $this->container = $container;
    }

    public function __invoke(array $requestData, WC_Order $order, $context = null, $next): array
    {
        $gateway = wc_get_payment_gateway_by_order($order);
        $methodId = substr($gateway->id, strrpos($gateway->id, '_') + 1);
        $paymentMethod = $this->paymentMethods[$methodId];
        if (property_exists($paymentMethod, 'paymentAPIfields')) {
            $paymentAPIfields = $paymentMethod->paymentAPIfields;
            foreach ($paymentAPIfields as $field) {
                $middlewareClass = 'Mollie\\WooCommerce\\Payment\\Request\\Middleware' . $field;
                if (class_exists($middlewareClass)) {
                    $middleware = $this->container->get($middlewareClass);
                    if ($middleware instanceof RequestMiddlewareInterface) {
                        $requestData = $middleware->__invoke($requestData, $order);
                    }
                }
            }
        }
        return $next($requestData, $order, $context);
    }
}

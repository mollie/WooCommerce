<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Decorators;

use Psr\Container\ContainerInterface;
use WC_Order;

class AddCustomRequestFieldsDecorator implements RequestDecoratorInterface
{
    private array $paymentMethods;
    private ContainerInterface $container;

    public function __construct($paymentMethods, $container)
    {
        $this->paymentMethods = $paymentMethods;
        $this->container = $container;
    }

    public function decorate(array $requestData, WC_Order $order, $context = null): array
    {
        $gateway = wc_get_payment_gateway_by_order($order);
        $methodId = substr($gateway->id, strrpos($gateway->id, '_') + 1);
        $paymentMethod = $this->paymentMethods[$methodId];
        if (property_exists($paymentMethod, 'paymentAPIfields')) {
            $paymentAPIfields = $paymentMethod->paymentAPIfields;
            foreach ($paymentAPIfields as $field) {
                $decoratorClass = 'Mollie\\WooCommerce\\Payment\\Decorator\\' . $field;
                if (class_exists($decoratorClass)) {
                    $decorator = $this->container->get($decoratorClass);
                    if ($decorator instanceof RequestDecoratorInterface) {
                        $requestData = $decorator->decorate($requestData, $order);
                    }
                }
            }
        }
        return $requestData;
    }
}

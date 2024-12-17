<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Decorator;

use Mollie\WooCommerce\Payment\Request\Decorators\RequestDecoratorInterface;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Psr\Container\ContainerInterface;
use WC_Order;

class AddCustomRequestFieldsDecorator implements RequestDecoratorInterface
{
    private PaymentMethodI $paymentMethod;
    private ContainerInterface $container;

    public function __construct($paymentMethod, $container)
    {
        $this->paymentMethod = $paymentMethod;
        $this->container = $container;
    }

    public function decorate(array $requestData, WC_Order $order): array
    {
        if (property_exists($this->paymentMethod, 'paymentAPIfields')) {
            $paymentAPIfields = $this->paymentMethod->paymentAPIfields;
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

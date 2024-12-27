<?php

namespace Mollie\WooCommerce\Payment\Request\Decorators;

use WC_Order;

interface RequestDecoratorInterface
{
    public function decorate(array $requestData, WC_Order $order, string $context = null): array;
}

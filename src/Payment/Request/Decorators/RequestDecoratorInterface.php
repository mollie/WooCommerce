<?php

namespace Mollie\WooCommerce\Payment\Request\Decorators;

use WC_Order;

interface RequestDecoratorInterface
{
    public function decorate(array $requestData, WC_Order $order, String $context = null): array;
}

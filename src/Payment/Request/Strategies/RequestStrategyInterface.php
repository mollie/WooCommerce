<?php

namespace Mollie\WooCommerce\Payment\Request\Strategies;

use WC_Order;

interface RequestStrategyInterface
{
    public function createRequest(WC_Order $order, string $customerId): array;
}

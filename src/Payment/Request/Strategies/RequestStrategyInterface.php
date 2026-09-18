<?php

namespace Mollie\WooCommerce\Payment\Request\Strategies;

use WC_Order;
/**
 * Interface RequestStrategyInterface
 *
 * Defines the contract for creating payment requests.
 *
 * @package Mollie\WooCommerce\Payment\Request\Strategies
 */
interface RequestStrategyInterface
{
    /**
     * Creates a payment request.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @param string $customerId The customer ID.
     * @return array The payment request data.
     */
    public function createRequest(WC_Order $order, string $customerId): array;
}

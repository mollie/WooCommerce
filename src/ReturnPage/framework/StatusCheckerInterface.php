<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\framework;

/**
 * Interface for checking payment status
 */
interface StatusCheckerInterface
{
    /**
     * Check the current status of an order's payment
     *
     * @param \WC_Order $order
     * @return ReturnPageStatus
     */
    public function checkStatus(\WC_Order $order): ReturnPageStatus;
}

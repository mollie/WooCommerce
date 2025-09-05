<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\framework;

/**
 * Configuration interface for adaptive behavior
 */
interface AdaptiveConfigInterface
{
    /**
     * Determine if monitoring should be enabled for this order
     *
     * @param \WC_Order $order
     * @return bool
     */
    public function shouldMonitor(\WC_Order $order): bool;

    /**
     * Get retry count based on current conditions
     *
     * @param \WC_Order $order
     * @return int
     */
    public function getRetryCount(\WC_Order $order): int;

    /**
     * Get interval based on current conditions
     *
     * @param \WC_Order $order
     * @return int Interval in milliseconds
     */
    public function getInterval(\WC_Order $order): int;
}

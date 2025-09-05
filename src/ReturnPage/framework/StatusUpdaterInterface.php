<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\framework;

/**
 * Interface for triggering manual status updates
 */
interface StatusUpdaterInterface
{
    /**
     * Manually update payment status (called on final timeout)
     *
     * @param \WC_Order $order
     * @return bool True if update was successful
     */
    public function updateStatus(\WC_Order $order): bool;
}

<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\framework;

/**
 * Interface for status-specific actions
 */
interface StatusActionInterface
{
    /**
     * Execute action before rendering return page with specific status
     *
     * @param \WC_Order $order
     * @param ReturnPageStatus $status
     * @return void
     */
    public function execute(\WC_Order $order, ReturnPageStatus $status): void;
}

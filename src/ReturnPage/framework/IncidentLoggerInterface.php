<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\framework;

/**
 * Interface for incident logging (our addition to the framework)
 */
interface IncidentLoggerInterface
{
    /**
     * Log when a timeout occurs (potential race condition)
     *
     * @param \WC_Order $order
     * @param array $context Additional context data
     * @return void
     */
    public function logTimeout(\WC_Order $order, array $context = []): void;

    /**
     * Get statistics about incidents
     *
     * @return array
     */
    public function getStats(): array;
}

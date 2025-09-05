<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\mollie;

use Mollie\WooCommerce\ReturnPage\framework\AdaptiveConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Smart Adaptive Configuration - adjusts based on learned patterns
 */
class MollieAdaptiveConfig implements AdaptiveConfigInterface
{
    private const FORCE_ENABLE_OPTION = 'mollie_force_enable_return_page_monitor';
    private MollieSmartIncidentLogger $incidentLogger;
    private LoggerInterface $logger;

    public function __construct(
        MollieSmartIncidentLogger $incidentLogger,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->incidentLogger = $incidentLogger;
    }

    public function shouldMonitor( $order): bool
    {
// Always monitor if force-enabled by admin
        if (get_option(self::FORCE_ENABLE_OPTION, false)) {
            return true;
        }

// Only monitor Mollie payments
        if (strpos($order->get_payment_method(), 'mollie_wc_gateway_') === false) {
            return false;
        }

// Only monitor pending orders
        if (!$order->has_status(['pending'])) {
            return false;
        }

// Smart monitoring: enable if incident rate is increasing
        if ($this->incidentLogger->isIncidentRateIncreasing()) {
            $this->logger->info("Auto-enabling Mollie return page monitoring due to increased incident rate");
            return true;
        }

// Default: don't monitor to save resources (webhooks should work)
        return false;
    }

    public function getRetryCount(\WC_Order $order): int
    {
        $stats = $this->incidentLogger->getStats();

// Increase retry count if we're seeing lots of issues
        if ($stats['last_hour'] > 5) {
            return 20; // More retries when problems are frequent
        } elseif ($stats['last_24h'] > 10) {
            return 15;
        }

        return 12; // Default: 30 seconds of polling
    }

    public function getInterval(\WC_Order $order): int
    {
        $stats = $this->incidentLogger->getStats();

// Poll more frequently during problem periods
        if ($stats['last_hour'] > 3) {
            return 2000; // Every 2 seconds during high-incident periods
        }

        return 2500; // Default: every 2.5 seconds
    }
}

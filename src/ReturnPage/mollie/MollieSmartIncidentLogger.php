<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\mollie;

use Mollie\WooCommerce\ReturnPage\framework\IncidentLoggerInterface;
use Psr\Log\LoggerInterface;

/**
 * Smart Mollie Incident Logger - learns from patterns
 */
class MollieSmartIncidentLogger implements IncidentLoggerInterface
{
    private const INCIDENTS_OPTION = 'mollie_return_page_incidents';
    private const MAX_INCIDENTS = 200;
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public function logTimeout(\WC_Order $order, array $context = []): void
    {
        $incident = [
            'timestamp' => time(),
            'order_id' => $order->get_id(),
            'payment_method' => $order->get_payment_method(),
            'order_total' => (float)$order->get_total(),
            'customer_id' => $order->get_customer_id(),
            'billing_country' => $order->get_billing_country(),
            'context' => $context
        ];

// Store in database
        $incidents = get_option(self::INCIDENTS_OPTION, []);
        $incidents[] = $incident;

// Keep only recent incidents
        if (count($incidents) > self::MAX_INCIDENTS) {
            $incidents = array_slice($incidents, -self::MAX_INCIDENTS);
        }

        update_option(self::INCIDENTS_OPTION, $incidents);

// Also log to WordPress/plugin logger
        $this->logger->warning("Mollie payment timeout detected for order {order_id}", [
            'order_id' => $order->get_id(),
            'payment_method' => $order->get_payment_method(),
            'total' => $order->get_total(),
            'context' => $context
        ]);

// Trigger action for other systems to hook into
        do_action('mollie_return_page_timeout_logged', $order, $incident);
    }

    public function getStats(): array
    {
        $incidents = get_option(self::INCIDENTS_OPTION, []);

        if (empty($incidents)) {
            return [
                'total' => 0,
                'last_24h' => 0,
                'last_hour' => 0,
                'avg_per_day' => 0,
                'countries' => [],
                'payment_methods' => []
            ];
        }

        $now = time();
        $last24h = array_filter($incidents, fn($i) => $i['timestamp'] > ($now - 86400));
        $lastHour = array_filter($incidents, fn($i) => $i['timestamp'] > ($now - 3600));

// Get country distribution
        $countries = [];
        foreach ($last24h as $incident) {
            $country = $incident['billing_country'] ?? 'unknown';
            $countries[$country] = ($countries[$country] ?? 0) + 1;
        }
        arsort($countries);

// Get payment method distribution
        $methods = [];
        foreach ($last24h as $incident) {
            $method = str_replace('mollie_wc_gateway_', '', $incident['payment_method']);
            $methods[$method] = ($methods[$method] ?? 0) + 1;
        }
        arsort($methods);

        return [
            'total' => count($incidents),
            'last_24h' => count($last24h),
            'last_hour' => count($lastHour),
            'avg_per_day' => count($incidents) > 0 ? count($incidents) / max(
                    1,
                    (time() - min(
                            array_column(
                                $incidents,
                                'timestamp'
                            )
                        )) / 86400
                ) : 0,
            'countries' => array_slice($countries, 0, 5),
            'payment_methods' => $methods
        ];
    }

    /**
     * Check if incidents are increasing (for adaptive configuration)
     */
    public function isIncidentRateIncreasing(): bool
    {
        $stats = $this->getStats();

// Consider rate increasing if we have 3+ incidents in last hour
// OR if we have more than 10 in last 24h
        return $stats['last_hour'] >= 2 || $stats['last_24h'] > 10;
    }
}

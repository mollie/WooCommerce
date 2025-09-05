<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\mollie;

/**
 * Admin Integration for Mollie-specific features
 */
class MollieReturnPageAdmin
{
    private MollieSmartIncidentLogger $incidentLogger;

    public function __construct(
        MollieSmartIncidentLogger $incidentLogger
    ) {
        $this->incidentLogger = $incidentLogger;
    }

    public function init(): void
    {
        add_action('wp_dashboard_setup', [$this, 'addDashboardWidget']);
        add_action('admin_notices', [$this, 'maybeShowIncidentNotice']);
        add_filter('mollie_wc_gateway_settings', [$this, 'addSettings']);
    }

    public function addDashboardWidget(): void
    {
        wp_add_dashboard_widget(
            'mollie-return-page-monitor',
            __('Mollie Payment Monitoring', 'mollie-payments-for-woocommerce'),
            [$this, 'renderDashboardWidget']
        );
    }

    public function renderDashboardWidget(): void
    {
        $stats = $this->incidentLogger->getStats();

        echo '<div class="mollie-dashboard-stats">';
        echo '<h4>' . __('Recent Activity (24h)', 'mollie-payments-for-woocommerce') . '</h4>';
        echo '<p><strong>' . sprintf(
                __('%d payment timeouts detected', 'mollie-payments-for-woocommerce'),
                $stats['last_24h']
            ) . '</strong></p>';

        if (!empty($stats['payment_methods'])) {
            echo '<p>' . __('Most affected:', 'mollie-payments-for-woocommerce') . ' ';
            $methods = array_slice($stats['payment_methods'], 0, 3, true);
            foreach ($methods as $method => $count) {
                echo sprintf('%s (%d) ', ucfirst($method), $count);
            }
            echo '</p>';
        }

        if ($stats['last_hour'] > 3) {
            echo '<div class="notice notice-warning inline">';
            echo '<p>' . __(
                    '⚠️ High timeout rate detected. Monitoring is auto-enabled.',
                    'mollie-payments-for-woocommerce'
                ) . '</p>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function maybeShowIncidentNotice(): void
    {
        $stats = $this->incidentLogger->getStats();

        if ($stats['last_24h'] > 15) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>' . __('Mollie Payment Alert:', 'mollie-payments-for-woocommerce') . '</strong> ';
            echo sprintf(
                __(
                    'High number of payment timeouts (%d in last 24h). Consider reviewing your webhook setup or contacting Mollie support.',
                    'mollie-payments-for-woocommerce'
                ),
                $stats['last_24h']
            );
            echo '</p></div>';
        }
    }

    public function addSettings(array $settings): array
    {
        $monitoring_settings = [
            'monitoring_section_title' => [
                'title' => __('Return Page Monitoring', 'mollie-payments-for-woocommerce'),
                'type' => 'title',
                'description' => __(
                    'Smart monitoring for payment processing issues',
                    'mollie-payments-for-woocommerce'
                ),
            ],
            'force_enable_monitoring' => [
                'title' => __('Force Enable Monitoring', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'description' => __(
                    'Always monitor return pages (normally auto-enabled only when issues detected)',
                    'mollie-payments-for-woocommerce'
                ),
                'default' => 'no',
            ],
            'monitoring_stats' => [
                'title' => __('Monitoring Statistics', 'mollie-payments-for-woocommerce'),
                'type' => 'title',
                'description' => $this->getStatsDescription(),
            ],
        ];

// Insert after webhook settings
        $webhook_pos = array_search('webhook', array_keys($settings));
        if ($webhook_pos !== false) {
            $pos = $webhook_pos + 1;
            return array_slice($settings, 0, $pos, true) +
                $monitoring_settings +
                array_slice($settings, $pos, null, true);
        }

        return $settings + $monitoring_settings;
    }

    private function getStatsDescription(): string
    {
        $stats = $this->incidentLogger->getStats();

        if ($stats['total'] === 0) {
            return __('No payment monitoring incidents recorded.', 'mollie-payments-for-woocommerce');
        }

        return sprintf(
            __('Total: %d | Last 24h: %d | Avg/day: %.1f | Status: %s', 'mollie-payments-for-woocommerce'),
            $stats['total'],
            $stats['last_24h'],
            $stats['avg_per_day'],
            $stats['last_hour'] > 3 ? __('Monitoring Active', 'mollie-payments-for-woocommerce') : __(
                'Normal',
                'mollie-payments-for-woocommerce'
            )
        );
    }
}

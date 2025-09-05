<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage;

/**
 * Admin interface enhancements for monitoring race conditions
 */
class FailoverAdminInterface
{
    /**
     * Add admin dashboard widget for race condition monitoring
     */
    public function addDashboardWidget(): void
    {
        wp_add_dashboard_widget(
            'mollie_race_condition_monitor',
            __('Mollie Payment Race Conditions', 'mollie-payments-for-woocommerce'),
            [$this, 'renderDashboardWidget']
        );
    }

    /**
     * Render the dashboard widget content
     */
    public function renderDashboardWidget(): void
    {
        $detector = new WebhookRaceConditionDetector();
        $stats = $detector->getWebhookTimingStats();

        echo '<div class="mollie-race-condition-stats">';
        echo '<p><strong>' . __('Webhook Statistics (Last 24h):', 'mollie-payments-for-woocommerce') . '</strong></p>';
        echo '<ul>';
        echo '<li>' . sprintf(
                __('Orders with webhooks: %d', 'mollie-payments-for-woocommerce'),
                $stats['total_orders']
            ) . '</li>';
        echo '<li>' . sprintf(
                __('Multiple webhooks: %d', 'mollie-payments-for-woocommerce'),
                $stats['multiple_webhooks']
            ) . '</li>';
        echo '<li>' . sprintf(
                __('Potential race conditions: %d', 'mollie-payments-for-woocommerce'),
                $stats['potential_races']
            ) . '</li>';
        echo '</ul>';

        if ($stats['potential_races'] > 0) {
            echo '<div class="notice notice-warning inline">';
            echo '<p>' . __(
                    'Race conditions detected. Consider enabling force payment checks.',
                    'mollie-payments-for-woocommerce'
                ) . '</p>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Add admin notice if race conditions are frequent
     */
    public function maybeShowRaceConditionNotice(): void
    {
        $detector = new WebhookRaceConditionDetector();
        $stats = $detector->getWebhookTimingStats();

        if ($stats['potential_races'] > 5) { // More than 5 potential races in 24h
            add_action('admin_notices', function () use ($stats) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>' . __('Mollie Payment Alert:', 'mollie-payments-for-woocommerce') . '</strong> ';
                echo sprintf(
                    __(
                        'High number of payment race conditions detected (%d in last 24h). Consider reviewing your webhook setup.',
                        'mollie-payments-for-woocommerce'
                    ),
                    $stats['potential_races']
                );
                echo '</p>';
                echo '</div>';
            });
        }
    }
}

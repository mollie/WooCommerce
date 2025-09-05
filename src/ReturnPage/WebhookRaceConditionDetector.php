<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage;

/**
 * Additional helper service for webhook race condition detection
 */
class WebhookRaceConditionDetector
{
    private const WEBHOOK_TIMING_OPTION = 'mollie_webhook_timing_data';
    private const SUSPICIOUS_TIMING_THRESHOLD = 1000; // 1 second in milliseconds

    /**
     * Track webhook timing to detect potential race conditions
     */
    public function trackWebhookTiming(int $order_id, string $payment_id): void
    {
        $timing_data = get_option(self::WEBHOOK_TIMING_OPTION, []);
        $current_time = microtime(true) * 1000; // Convert to milliseconds

        $key = "order_{$order_id}_payment_{$payment_id}";

        if (isset($timing_data[$key])) {
            $time_diff = $current_time - $timing_data[$key]['first_webhook'];

            if ($time_diff < self::SUSPICIOUS_TIMING_THRESHOLD) {
// Potential race condition detected
                $this->logSuspiciousWebhookTiming($order_id, $payment_id, $time_diff);
            }

            $timing_data[$key]['webhook_count']++;
            $timing_data[$key]['last_webhook'] = $current_time;
        } else {
            $timing_data[$key] = [
                'first_webhook' => $current_time,
                'last_webhook' => $current_time,
                'webhook_count' => 1,
                'order_id' => $order_id,
                'payment_id' => $payment_id
            ];
        }

// Clean up old entries (keep only last 24 hours)
        $cutoff_time = $current_time - (24 * 60 * 60 * 1000); // 24 hours ago
        $timing_data = array_filter($timing_data, function ($data) use ($cutoff_time) {
            return $data['last_webhook'] > $cutoff_time;
        });

        update_option(self::WEBHOOK_TIMING_OPTION, $timing_data);
    }

    /**
     * Log suspicious webhook timing that might indicate race conditions
     */
    private function logSuspiciousWebhookTiming(int $order_id, string $payment_id, float $time_diff): void
    {
// This could be integrated with the existing logger
        error_log(
            sprintf(
                'Mollie: Suspicious webhook timing detected for Order %d, Payment %s. Time difference: %.2fms',
                $order_id,
                $payment_id,
                $time_diff
            )
        );

// Optionally trigger the race condition incident logging
        do_action('mollie_potential_race_condition_detected', $order_id, $payment_id, $time_diff);
    }

    /**
     * Get webhook timing statistics for debugging
     */
    public function getWebhookTimingStats(): array
    {
        $timing_data = get_option(self::WEBHOOK_TIMING_OPTION, []);

        $stats = [
            'total_orders' => count($timing_data),
            'multiple_webhooks' => 0,
            'potential_races' => 0,
            'average_time_diff' => 0
        ];

        $time_diffs = [];

        foreach ($timing_data as $data) {
            if ($data['webhook_count'] > 1) {
                $stats['multiple_webhooks']++;

                $time_diff = $data['last_webhook'] - $data['first_webhook'];
                if ($time_diff < self::SUSPICIOUS_TIMING_THRESHOLD) {
                    $stats['potential_races']++;
                }

                $time_diffs[] = $time_diff;
            }
        }

        if (!empty($time_diffs)) {
            $stats['average_time_diff'] = array_sum($time_diffs) / count($time_diffs);
        }

        return $stats;
    }
}


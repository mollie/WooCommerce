<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\framework;

/**
 * Simple file-based incident logger
 */
class FileIncidentLogger implements IncidentLoggerInterface
{
    private string $logFile;

    public function __construct(string $logFile = null)
    {
        $this->logFile = $logFile ?? WP_CONTENT_DIR . '/uploads/wc-return-page-incidents.log';
    }

    public function logTimeout(\WC_Order $order, array $context = []): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'order_id' => $order->get_id(),
            'payment_method' => $order->get_payment_method(),
            'context' => $context
        ];

        error_log(json_encode($logEntry) . "\n", 3, $this->logFile);
    }

    public function getStats(): array
    {
        if (!file_exists($this->logFile)) {
            return ['total' => 0, 'last_24h' => 0];
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total = count($lines);
        $last24h = 0;
        $cutoff = time() - 86400;

        foreach (array_reverse($lines) as $line) {
            $entry = json_decode($line, true);
            if ($entry && strtotime($entry['timestamp']) > $cutoff) {
                $last24h++;
            } else {
                break; // Lines are chronological, so we can stop here
            }
        }

        return ['total' => $total, 'last_24h' => $last24h];
    }
}

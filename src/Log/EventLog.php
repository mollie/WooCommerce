<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Unknown fields are dropped, never masked; only the field name is reported.
 * Warnings and errors go to $problems so they are written even when debug logging is off.
 * Steps are written only in trace mode or when the request ends badly.
 */
final class EventLog
{
    private const TRACE_FILTER = 'mollie-payments-for-woocommerce_trace';

    private const ALLOWED_FIELDS = [
        'cid', 'order', 'session', 'mollie_id', 'wallet', 'surface', 'mode', 'reason', 'kind', 'status',
        'amount', 'currency', 'ms',
        'entry', 'result', 'method', 'path', 'key', 'wait_ms', 'held_ms', 'rule', 'verdict', 'inputs',
        'status_before', 'status_after', 'meta_keys', 'note_keys', 'status_asked', 'status_found',
        'mollie_calls', 'hooks',
    ];

    private string $correlationId;

    /**
     * @var array<int, array{0: string, 1: array<string, scalar>}>
     */
    private array $buffer = [];

    private bool $problemSeen = false;

    public function __construct(
        private LoggerInterface $logger,
        private ?LoggerInterface $problems = null
    ) {

        $this->correlationId = bin2hex(random_bytes(8));
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function info(string $event, array $fields = []): void
    {
        $this->infoLogger()->info($event, $this->context($event, $fields));
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function warning(string $event, array $fields = []): void
    {
        $this->problemSeen = true;
        ($this->problems ?? $this->logger)->warning($event, $this->context($event, $fields));
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function error(string $event, array $fields = []): void
    {
        $this->problemSeen = true;
        ($this->problems ?? $this->logger)->error($event, $this->context($event, $fields));
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function step(string $event, array $fields = []): void
    {
        $context = $this->context($event, $fields);
        if ($this->traceOn()) {
            ($this->problems ?? $this->logger)->info($event, $context);

            return;
        }
        $this->buffer[] = [$event, $context];
    }

    /**
     * False with debug and trace off.
     */
    public function writesInfo(): bool
    {
        return !$this->infoLogger() instanceof NullLogger;
    }

    public function flush(bool $endedBadly = false): void
    {
        $buffer = $this->buffer;
        $this->buffer = [];
        if (!$endedBadly && !$this->problemSeen && !$this->traceOn()) {
            return;
        }

        $logger = $this->problems ?? $this->logger;
        foreach ($buffer as [$event, $context]) {
            $logger->info($event, $context);
        }
    }

    private function traceOn(): bool
    {
        return (bool) apply_filters(self::TRACE_FILTER, false);
    }

    /**
     * Trace is written even with debug off.
     */
    private function infoLogger(): LoggerInterface
    {
        return $this->traceOn() ? ($this->problems ?? $this->logger) : $this->logger;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, scalar>
     */
    private function context(string $event, array $fields): array
    {
        $context = ['cid' => $this->correlationId];

        foreach ($fields as $name => $value) {
            $name = (string) $name;
            if ($name === 'cid') {
                continue;
            }
            if (in_array($name, self::ALLOWED_FIELDS, true) && is_scalar($value)) {
                $context[$name] = $value;
                continue;
            }
            $this->reportDropped($event, $name);
        }

        return $context;
    }

    private function reportDropped(string $event, string $field): void
    {
        do_action('mollie_wc_event_log_field_dropped', $event, $field);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- developer notice, WP_DEBUG only, field name never value.
            error_log(sprintf(
                'Mollie EventLog dropped the field "%s" of event "%s": it is not an allowed field.',
                preg_replace('/[^A-Za-z0-9_.\-]/', '', $field),
                preg_replace('/[^A-Za-z0-9_.\-]/', '', $event)
            ));
        }
    }
}

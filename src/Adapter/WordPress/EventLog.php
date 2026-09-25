<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Adapter\WordPress;

use Psr\Log\LoggerInterface;

/**
 * The only way new code logs (blueprint chokepoint 4, ADR-014). Catalogue: docs/architecture/events.md.
 *
 * Events have stable names. Fields are an allowlist: anything else is dropped, never masked, and
 * only its name is reported to developers. Every line carries the correlation id of its request,
 * generated when the log is built, which is once per request.
 *
 * Warnings and errors go to $problems when one is given: they are what someone investigates, so they
 * are written even while the merchant's debug switch keeps info events out of the log.
 */
final class EventLog
{
    /**
     * The "Allowed fields" table of docs/architecture/events.md. Keep the two in step.
     */
    private const ALLOWED_FIELDS = [
        'cid', 'order', 'session', 'mollie_id', 'wallet', 'surface', 'mode', 'reason', 'kind', 'status',
        'amount', 'currency', 'ms',
    ];

    private string $correlationId;

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
        $this->logger->info($event, $this->context($event, $fields));
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function warning(string $event, array $fields = []): void
    {
        ($this->problems ?? $this->logger)->warning($event, $this->context($event, $fields));
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function error(string $event, array $fields = []): void
    {
        ($this->problems ?? $this->logger)->error($event, $this->context($event, $fields));
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
                continue; // The correlation id is the log's own.
            }
            if (in_array($name, self::ALLOWED_FIELDS, true) && is_scalar($value)) {
                $context[$name] = $value;
                continue;
            }
            $this->reportDropped($event, $name);
        }

        return $context;
    }

    /**
     * Names the field, never its value.
     */
    private function reportDropped(string $event, string $field): void
    {
        do_action('mollie_wc_event_log_field_dropped', $event, $field);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- developer notice, WP_DEBUG only, field name never value.
            error_log(sprintf(
                'Mollie EventLog dropped the field "%s" of event "%s": not in docs/architecture/events.md.',
                preg_replace('/[^A-Za-z0-9_.\-]/', '', $field),
                preg_replace('/[^A-Za-z0-9_.\-]/', '', $event)
            ));
        }
    }
}

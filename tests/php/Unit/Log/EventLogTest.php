<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Log;

use Brain\Monkey\Filters;
use Mollie\WooCommerce\Log\EventLog;
use Mollie\WooCommerceTests\TestCase;
use Psr\Log\AbstractLogger;

/**
 * Step buffering and trace mode.
 *
 * @covers \Mollie\WooCommerce\Log\EventLog
 */
class EventLogTest extends TestCase
{
    private const TRACE_FILTER = 'mollie-payments-for-woocommerce_trace';

    /**
     * @var AbstractLogger&object{records: array<int, array{level: string, message: string, context: array<mixed>}>}
     */
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new class extends AbstractLogger {
            /** @var array<int, array{level: string, message: string, context: array<mixed>}> */
            public array $records = [];

            public function log($level, $message, array $context = [])
            {
                $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
            }
        };
    }

    /**
     * Scenario: a clean request with trace off writes no step
     *   Given trace is off
     *   When steps and an info event are logged and flushed
     *   Then only the info event is written
     */
    public function test_it_drops_every_buffered_step_when_the_request_ends_cleanly_with_trace_off(): void
    {
        $log = new EventLog($this->logger);

        $log->step('express.payment.resolve.started', ['order' => 4711, 'mollie_id' => 'tr_abc', 'entry' => 'webhook']);
        $log->step('lock.taken', ['order' => 4711, 'wait_ms' => 2]);
        $log->info('rule.decided', ['order' => 4711, 'rule' => 'ExpressOrderMatch', 'verdict' => 'admit']);
        $log->step('lock.released', ['order' => 4711, 'held_ms' => 9]);
        $log->flush();

        self::assertSame(['rule.decided'], $this->messages(), 'A good day costs nothing: buffered steps are dropped.');
    }

    /**
     * Scenario: a warning or an error writes the buffered steps
     *   Given trace is off and two buffered steps
     *   When a problem is logged and the log is flushed
     *   Then both steps are written with the problem's cid
     *
     * @dataProvider problemLevels
     */
    public function test_it_writes_every_buffered_step_of_the_cid_when_a_problem_was_logged(string $level): void
    {
        $log = new EventLog($this->logger);

        $log->step('express.payment.resolve.started', ['order' => 4711, 'mollie_id' => 'tr_abc', 'entry' => 'webhook']);
        $log->step('mollie.called', ['method' => 'GET', 'path' => 'payments/tr_abc', 'result' => 'ok', 'ms' => 40]);
        $log->{$level}('express.webhook.unmatched', ['mollie_id' => 'tr_abc', 'reason' => 'unknown_ref']);
        $log->flush();

        $messages = $this->messages();
        self::assertContains('express.payment.resolve.started', $messages, 'A failure must come with its trace.');
        self::assertContains('mollie.called', $messages);
        self::assertContains('express.webhook.unmatched', $messages);
        self::assertCount(1, $this->correlationIds(), 'The trace and the problem must read as one story.');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function problemLevels(): array
    {
        return ['warning' => ['warning'], 'error' => ['error']];
    }

    /**
     * Scenario: a failure at the boundary writes the buffered steps
     *   Given trace is off and two buffered steps
     *   When the log is flushed as ended badly
     *   Then both steps are written in order
     */
    public function test_it_writes_every_buffered_step_when_the_boundary_reports_a_failure(): void
    {
        $log = new EventLog($this->logger);

        $log->step('express.payment.resolve.started', ['order' => 4711, 'mollie_id' => 'tr_abc', 'entry' => 'webhook']);
        $log->step('mollie.called', ['method' => 'GET', 'path' => 'payments/tr_abc', 'result' => 'ok', 'ms' => 40]);
        $log->flush(true);

        self::assertSame(['express.payment.resolve.started', 'mollie.called'], $this->messages());
    }

    /**
     * Scenario: a step is written once
     *   Given a step already flushed
     *   When the log is flushed again
     *   Then the step is not written twice
     */
    public function test_it_writes_a_flushed_step_only_once(): void
    {
        $log = new EventLog($this->logger);

        $log->step('lock.taken', ['order' => 4711, 'wait_ms' => 2]);
        $log->flush(true);
        $log->flush(true);

        self::assertSame(['lock.taken'], $this->messages());
    }

    /**
     * Scenario: trace on writes every step at once, in order
     *   Given the trace filter returns true
     *   When steps and info events are logged
     *   Then they are written in call order, with one cid
     */
    public function test_it_writes_steps_immediately_and_in_order_when_the_trace_filter_is_on(): void
    {
        Filters\expectApplied(self::TRACE_FILTER)->andReturn(true);
        $log = new EventLog($this->logger);

        $log->step('express.payment.resolve.started', ['order' => 4711, 'mollie_id' => 'tr_abc', 'entry' => 'webhook']);
        $log->step('mollie.called', ['method' => 'GET', 'path' => 'payments/tr_abc', 'result' => 'ok', 'ms' => 40]);
        $log->info('rule.decided', ['order' => 4711, 'rule' => 'ExpressOrderMatch', 'verdict' => 'admit']);
        $log->step('lock.taken', ['order' => 4711, 'wait_ms' => 2]);

        self::assertSame(
            ['express.payment.resolve.started', 'mollie.called', 'rule.decided', 'lock.taken'],
            $this->messages(),
            'In trace mode the log reads top to bottom as the request ran.'
        );
        self::assertSame('info', $this->logger->records[0]['level']);
        self::assertCount(1, $this->correlationIds());
    }

    /**
     * Scenario: info events are written at once with trace off
     *   Given trace is off
     *   When an info event is logged
     *   Then it is written without a flush
     */
    public function test_it_keeps_writing_info_events_immediately_with_trace_off(): void
    {
        $log = new EventLog($this->logger);

        $log->info('order.written', ['order' => 4711, 'status' => 'processing', 'ms' => 3]);

        self::assertSame(['order.written'], $this->messages());
    }

    /**
     * Scenario: a step keeps allowlisted scalars only
     *   Given trace is on
     *   When rule.decided is logged with an email, a URL and an array
     *   Then only cid, order, rule, verdict and inputs remain
     */
    public function test_it_drops_unknown_and_non_scalar_fields_from_a_step(): void
    {
        Filters\expectApplied(self::TRACE_FILTER)->andReturn(true);
        $log = new EventLog($this->logger);

        $log->step('rule.decided', [
            'order' => 4711,
            'rule' => 'FirstSight',
            'verdict' => 'record',
            'inputs' => 'status=paid tracks=0',
            'email' => 'shopper@example.com',
            'checkout_url' => 'https://shop.example/checkout?key=secret',
            'meta_keys' => ['_mollie_payment_id'],
        ]);

        $context = $this->logger->records[0]['context'];
        $keys = array_keys($context);
        sort($keys);
        self::assertSame(['cid', 'inputs', 'order', 'rule', 'verdict'], $keys, 'Unknown fields are dropped, not masked.');
        self::assertSame('status=paid tracks=0', $context['inputs']);
    }

    /**
     * @return array<int, string>
     */
    private function messages(): array
    {
        return array_map(static fn (array $record): string => $record['message'], $this->logger->records);
    }

    /**
     * @return array<int, string>
     */
    private function correlationIds(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $record): string => (string) ($record['context']['cid'] ?? ''),
            $this->logger->records
        )));
    }
}

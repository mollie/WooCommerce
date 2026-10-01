<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Mollie\WooCommerce\Payment\OrderLock;
use Mollie\WooCommerce\SDK\MollieApi;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Psr\Container\ContainerInterface;
use Throwable;
use WC_Order;
use wpdb;

/**
 * Step trace: trace mode, the flight recorder and what OrderLock reports.
 *
 * @covers \Mollie\WooCommerce\Log\EventLog
 * @covers \Mollie\WooCommerce\Payment\OrderLock
 * @covers \Mollie\WooCommerce\SDK\SdkMollieApi
 * @covers \Mollie\WooCommerce\ExpressComponent\Flow\ResolveExpressPayment
 *
 * @group integration
 * @group ExpressComponent
 * @group StepTrace
 */
class StepTraceTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    private const TRACE_FILTER = 'mollie-payments-for-woocommerce_trace';

    private const FLOW = 'express.payment.resolve';

    private const STEP_EVENTS = [
        self::FLOW . '.started',
        self::FLOW . '.finished',
        'mollie.called',
        'lock.taken',
        'lock.released',
        'rule.decided',
        'order.written',
        'listeners.observed',
        'hooks.fired',
    ];

    private const BUFFERED_STEPS = [
        self::FLOW . '.started',
        self::FLOW . '.finished',
        'mollie.called',
        'lock.taken',
        'lock.released',
        'hooks.fired',
    ];

    private ?ContainerInterface $container = null;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->setOptionForTest('mollie-payments-for-woocommerce_debug', 'yes');
    }

    public function tearDown(): void
    {
        $this->container = null;
        $this->tearDownExpressCheckout();

        parent::tearDown();
    }

    /**
     * Scenario: a traced paid webhook writes its steps in order
     *   Given trace is on and a paid express payment
     *   When Mollie calls the webhook
     *   Then the steps are written in the order the flow ran, with one cid
     *
     * @test
     */
    public function it_writes_the_steps_of_a_paid_express_webhook_in_order_with_one_cid_when_trace_is_on(): void
    {
        $this->traceOn();
        [$order, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal']);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $this->assertTrue($this->fresh($order)->is_paid(), 'The traced webhook must still pay the order.');
        $steps = $this->steps();
        $this->assertSame(
            [
                self::FLOW . '.started',
                'mollie.called',
                'rule.decided',
                'rule.decided',
                'lock.taken',
                'order.written',
                'lock.released',
                self::FLOW . '.finished',
            ],
            array_column($steps, 'message'),
            "The trace must read top to bottom as the webhook ran:\n" . $this->logger()->dump()
        );
        $this->assertCount(
            1,
            array_unique(array_map(static fn (array $step): string => (string) ($step['context']['cid'] ?? ''), $steps)),
            'One request, one cid.'
        );
        $this->assertNotSame('', (string) ($steps[0]['context']['cid'] ?? ''));
        $this->assertSame($payment['id'], $steps[0]['context']['mollie_id'] ?? null);
        $this->assertSame($order->get_id(), (int) ($steps[7]['context']['order'] ?? 0));
        $this->assertSame(['ExpressOrderMatch', 'FirstSight'], [$steps[2]['context']['rule'] ?? null, $steps[3]['context']['rule'] ?? null]);
    }

    /**
     * Scenario: a clean webhook with trace off writes no step
     *   Given trace is off and debug is on
     *   When a paid webhook is answered 200
     *   Then rule.decided and order.written are written, and no other step
     *
     * @test
     */
    public function it_writes_no_step_line_for_a_paid_express_webhook_that_ends_well_with_trace_off(): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal']);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $this->assertTrue($this->fresh($order)->is_paid());
        $this->assertSame([], $this->logger()->records('warning'), 'This request must have ended well.');
        $this->assertNotSame([], $this->loggedEvents('rule.decided'), 'rule.decided is written in normal mode with debug on.');
        $this->assertNotSame([], $this->orderWrittenSteps(), 'order.written is written in normal mode with debug on.');
        $this->assertSame(
            [],
            array_values(array_filter(
                array_column($this->logger()->records(), 'message'),
                static fn (string $message): bool => in_array($message, self::BUFFERED_STEPS, true)
            )),
            "A good day must cost nothing:\n" . $this->logger()->dump()
        );
    }

    /**
     * Scenario: a lock timeout writes the whole trace
     *   Given trace is off and the order lock is held elsewhere
     *   When a paid webhook is answered 503
     *   Then the steps before the timeout are written with its cid
     *
     * @test
     */
    public function it_writes_the_whole_trace_when_the_order_lock_times_out(): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal']);

        $holder = $this->holdTheLock((string) $order->get_id());
        try {
            $status = $this->deliverWebhook($payment['id']);
        } finally {
            $this->releaseTheLock($holder, (string) $order->get_id());
        }

        $this->assertSame(503, $status);
        $timeouts = $this->loggedEvents('order.lock_timeout');
        $this->assertCount(1, $timeouts);
        $cid = (string) $timeouts[0]['context']['cid'];
        $messages = array_column($this->recordsOf($cid), 'message');
        foreach ([self::FLOW . '.started', 'mollie.called', 'rule.decided', self::FLOW . '.finished'] as $step) {
            $this->assertContains($step, $messages, "A failure must come with its whole trace:\n" . $this->logger()->dump());
        }
        $this->assertNotContains('lock.taken', $messages, 'The lock was never taken.');
    }

    /**
     * Scenario: a listener that changes the status is reported
     *   Given a listener that moves on-hold orders to processing
     *   When the work puts the order on hold
     *   Then listeners.observed warns with on-hold asked and processing found
     *
     * @test
     */
    public function it_warns_listeners_observed_when_a_listener_changes_the_status_after_the_save(): void
    {
        $lock = $this->lock();
        $orderId = $this->pendingOrder('mollie_wc_gateway_ideal')->get_id();
        $this->addTestFilter('woocommerce_order_status_on-hold', static function ($id): void {
            $other = wc_get_order((int) $id);
            if ($other instanceof WC_Order) {
                $other->set_status('processing');
                $other->save();
            }
        });

        $lock->withFreshOrder($orderId, static function (WC_Order $fresh): void {
            $fresh->set_status('on-hold');
            $fresh->save();
        });

        $observed = $this->loggedEvents('listeners.observed');
        $this->assertCount(1, $observed, "A status changed behind the flow's back must be reported:\n" . $this->logger()->dump());
        $this->assertSame('warning', $observed[0]['level']);
        $this->assertSame($orderId, (int) ($observed[0]['context']['order'] ?? 0));
        $this->assertSame('on-hold', $observed[0]['context']['status_asked'] ?? null);
        $this->assertSame('processing', $observed[0]['context']['status_found'] ?? null);
    }

    /**
     * Scenario: a Mollie call inside the lock is reported
     *   Given a pending order
     *   When the work asks Mollie for a payment, even one Mollie refuses
     *   Then listeners.observed warns with one Mollie call
     *
     * @test
     */
    public function it_warns_listeners_observed_when_mollie_is_called_inside_the_lock(): void
    {
        $lock = $this->lock();
        $mollie = $this->boot()->get(MollieApi::class);
        $this->assertInstanceOf(MollieApi::class, $mollie);
        $orderId = $this->pendingOrder('mollie_wc_gateway_ideal')->get_id();

        $lock->withFreshOrder($orderId, static function (WC_Order $fresh) use ($mollie): void {
            try {
                $mollie->payment('tr_unknownToMollie');
            } catch (Throwable $refused) {
                // The call counts, not its answer.
            }
        });

        $observed = $this->loggedEvents('listeners.observed');
        $this->assertCount(1, $observed, "A Mollie call while holding the order lock must be reported:\n" . $this->logger()->dump());
        $this->assertSame('warning', $observed[0]['level']);
        $this->assertSame(1, (int) ($observed[0]['context']['mollie_calls'] ?? 0));
    }

    /**
     * Scenario: a plain save reports nothing
     *   Given a pending order and no listener
     *   When the work puts the order on hold
     *   Then no listeners.observed is written
     *
     * @test
     */
    public function it_does_not_warn_listeners_observed_when_the_status_holds_and_mollie_is_not_called(): void
    {
        $lock = $this->lock();
        $orderId = $this->pendingOrder('mollie_wc_gateway_ideal')->get_id();

        $lock->withFreshOrder($orderId, static function (WC_Order $fresh): void {
            $fresh->set_status('on-hold');
            $fresh->save();
        });

        $this->assertSame('on-hold', wc_get_order($orderId)->get_status());
        $this->assertSame([], $this->loggedEvents('listeners.observed'));
    }

    /**
     * Scenario: order.written names statuses and meta keys
     *   Given a pending order
     *   When the work writes a meta key and puts the order on hold
     *   Then order.written has pending, on-hold and the key, never its value
     *
     * @test
     */
    public function it_writes_order_written_with_the_status_before_and_after_and_the_meta_keys_written(): void
    {
        $lock = $this->lock();
        $orderId = $this->pendingOrder('mollie_wc_gateway_ideal')->get_id();

        $lock->withFreshOrder($orderId, static function (WC_Order $fresh): void {
            $fresh->update_meta_data('_mollie_payment_id', 'tr_writtenUnderLock');
            $fresh->set_status('on-hold');
            $fresh->save();
        });

        $written = $this->orderWrittenSteps();
        $this->assertCount(1, $written, "OrderLock must write one order.written:\n" . $this->logger()->dump());
        $context = $written[0]['context'];
        $this->assertSame($orderId, (int) ($context['order'] ?? 0));
        $this->assertSame('pending', $context['status_before'] ?? null);
        $this->assertSame('on-hold', $context['status_after'] ?? null);
        $this->assertIsString($context['meta_keys'] ?? null, 'Every field of a step is a scalar.');
        $this->assertStringContainsString('_mollie_payment_id', (string) $context['meta_keys']);
        $this->assertStringNotContainsString('tr_writtenUnderLock', $this->logger()->dump(), 'Keys are written, values are not.');
    }

    /**
     * Scenario: a traced webhook logs scalars only and leaks nothing
     *   Given trace is on and canary addresses
     *   When Mollie calls the webhook
     *   Then rules carry name, verdict and key=value inputs, the path carries only the id, and nothing leaked
     *
     * @test
     */
    public function it_logs_rule_decided_with_name_verdict_and_scalar_inputs_and_leaks_nothing_with_trace_on(): void
    {
        $this->traceOn();
        [$order, , $sessionId] = $this->expressOrder([]);
        $payment = $this->fakeMollie()->completeSession($sessionId, [
            'status' => 'paid',
            'method' => 'applepay',
            'billingAddress' => CanaryData::mollieAddress(),
            'shippingAddress' => CanaryData::mollieAddress(['city' => 'Rotterdam']),
        ]);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $this->assertTrue($this->fresh($order)->is_paid());
        $rules = $this->loggedEvents('rule.decided');
        $this->assertNotSame([], $rules, 'The traced webhook must have asked its rules.');
        foreach ($rules as $rule) {
            $context = $rule['context'];
            $this->assertNotSame('', (string) ($context['rule'] ?? ''), 'rule.decided must name its rule.');
            $this->assertNotSame('', (string) ($context['verdict'] ?? ''), 'rule.decided must name its verdict.');
            $this->assertIsString($context['inputs'] ?? null);
            $this->assertRegExp(
                '/^(?:[A-Za-z][A-Za-z0-9_]*=[A-Za-z0-9_.:\-]*(?: [A-Za-z][A-Za-z0-9_]*=[A-Za-z0-9_.:\-]*)*)?$/',
                (string) $context['inputs'],
                'The inputs of a rule are key=value pairs of booleans, statuses and ids.'
            );
        }
        foreach ($this->steps() as $step) {
            foreach ($step['context'] as $field => $value) {
                $this->assertIsScalar($value, "The field {$field} of {$step['message']} is not a scalar.");
            }
        }
        $calls = $this->loggedEvents('mollie.called');
        $this->assertNotSame([], $calls);
        $this->assertSame('GET', $calls[0]['context']['method'] ?? null);
        $this->assertSame('payments/' . $payment['id'], $calls[0]['context']['path'] ?? null, 'A path carries the resource id and nothing else.');
        $this->assertNothingLeakedToLog();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function traceOn(): void
    {
        $this->addTestFilter(self::TRACE_FILTER, '__return_true');
    }

    private function boot(): ContainerInterface
    {
        if ($this->container === null) {
            $this->container = $this->bootExpress();
        }

        return $this->container;
    }

    private function lock(): OrderLock
    {
        $lock = $this->boot()->get(OrderLock::class);
        $this->assertInstanceOf(OrderLock::class, $lock);
        $this->logger()->reset();

        return $lock;
    }

    /**
     * @param array<string, string>|null $billing [] for a guest who filled none.
     * @return array{0: WC_Order, 1: string, 2: string} The order, its express_ref, its session id.
     */
    private function expressOrder(?array $billing = null): array
    {
        $this->boot();
        $this->actAsGuest();
        $this->cartWith(['simple'], 2);
        $this->fillCheckoutForm($billing ?? $this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');

        $session = $this->startedSession();
        $this->assertAnsweredOk($this->startOrder());
        $this->logger()->reset();

        return [$this->onlyOrderFor($session['ref']), $session['ref'], $session['id']];
    }

    /**
     * @return array<int, array{level: string, message: string, context: array<mixed>}>
     */
    private function steps(): array
    {
        return array_values(array_filter($this->logger()->records(), static function (array $record): bool {
            if (!in_array($record['message'], self::STEP_EVENTS, true)) {
                return false;
            }

            return $record['message'] !== 'order.written' || array_key_exists('status_before', $record['context']);
        }));
    }

    /**
     * @return array<int, array{level: string, message: string, context: array<mixed>}>
     */
    private function orderWrittenSteps(): array
    {
        return array_values(array_filter($this->loggedEvents('order.written'), static function (array $record): bool {
            return array_key_exists('status_before', $record['context']);
        }));
    }

    /**
     * @return array<int, array{level: string, message: string, context: array<mixed>}>
     */
    private function recordsOf(string $cid): array
    {
        return array_values(array_filter($this->logger()->records(), static function (array $record) use ($cid): bool {
            return (string) ($record['context']['cid'] ?? '') === $cid;
        }));
    }

    private function fresh(WC_Order $order): WC_Order
    {
        clean_post_cache($order->get_id());
        wp_cache_delete(WC_Order::generate_meta_cache_key($order->get_id(), 'orders'), 'orders');
        $fresh = wc_get_order($order->get_id());
        $this->assertInstanceOf(WC_Order::class, $fresh);

        return $fresh;
    }

    private function holdTheLock(string $orderKey): wpdb
    {
        $holder = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $taken = $holder->get_var($holder->prepare('SELECT GET_LOCK(%s, 0)', OrderLock::lockName($orderKey)));
        $this->assertSame('1', (string) $taken, 'The test could not take the lock it needs to hold.');

        return $holder;
    }

    private function releaseTheLock(wpdb $holder, string $orderKey): void
    {
        $holder->get_var($holder->prepare('SELECT RELEASE_LOCK(%s)', OrderLock::lockName($orderKey)));
        $holder->close();
    }
}

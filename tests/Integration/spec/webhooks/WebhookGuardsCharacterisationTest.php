<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\webhooks;

use Mockery;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;
use WC_Order;

/**
 * @group integration
 * @group Webhooks
 * @group WebhookGuards
 */
class WebhookGuardsCharacterisationTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    private const NEEDS_PAYMENT_LINE = 'Mollie\\WooCommerce\\Payment\\MollieOrderService::orderNeedsPayment %s: Order %d orderNeedsPayment check: %s';

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->recordWebhookHooks();
    }

    public function tearDown(): void
    {
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Scenario: the first matching check answers
     *   Given an order with the given gateway, status and meta
     *   When orderNeedsPayment() runs
     *   Then it returns the expected answer and that check's debug line
     *
     * @test
     * @dataProvider needsPaymentBranches
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::orderNeedsPayment
     *
     * @param array<string, string> $meta
     */
    public function it_answers_whether_the_order_needs_payment_as_today(
        string $gatewayId,
        string $status,
        array $meta,
        bool $expected,
        ?string $line
    ): void {

        [$order] = $this->orderWithPayment('paid', $gatewayId);
        $order->set_status($status);
        foreach ($meta as $key => $value) {
            $order->update_meta_data($key, $value);
        }
        $order->save();
        $order = $this->fresh($order);
        $this->logger()->reset();

        $answer = $this->service()->orderNeedsPayment($order);

        $this->assertSame($expected, $answer);
        $this->assertSame(
            $line === null ? [] : [sprintf(self::NEEDS_PAYMENT_LINE, $gatewayId, $order->get_id(), $line)],
            $this->debugLinesContaining('orderNeedsPayment check')
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, string>, 3: bool, 4: string|null}>
     */
    public function needsPaymentBranches(): array
    {
        $paypal = 'mollie_wc_gateway_paypal';
        $processed = ['_mollie_paid_and_processed' => '1'];

        return [
            'REQ-121 paid by another gateway' => [
                $paypal, 'processing', ['_mollie_paid_by_other_gateway' => '1'] + $processed, false,
                'no, previously processed by other (non-Mollie) gateway.',
            ],
            'REQ-345 paid by another gateway outranks not processed by Mollie' => [
                $paypal, 'pending', ['_mollie_paid_by_other_gateway' => '1'], false,
                'no, previously processed by other (non-Mollie) gateway.',
            ],
            'REQ-345 not processed by Mollie' => [
                $paypal, 'pending', [], true,
                'yes, order not previously processed by Mollie gateway.',
            ],
            'REQ-345 processed and authorized' => [
                $paypal, 'processing', ['_mollie_authorized' => '1'] + $processed, true,
                'yes, order is authorized.',
            ],
            'REQ-345 processed but WooCommerce needs payment' => [
                $paypal, 'pending', $processed, true,
                'yes, WooCommerce thinks order needs payment.',
            ],
            'REQ-345 processed, on hold, and the method starts orders on hold' => [
                'mollie_wc_gateway_banktransfer', 'on-hold', $processed, true,
                'yes, has status On-Hold. ',
            ],
            'REQ-345 processed, on hold, but the method starts orders pending' => [
                $paypal, 'on-hold', $processed, false, null,
            ],
            'REQ-344 processed and paid' => [
                $paypal, 'processing', $processed, false, null,
            ],
        ];
    }

    /**
     * Scenario: a non-Mollie order
     *   Given an order of another gateway
     *   When doPaymentForOrder() runs
     *   Then it returns false without fetching and changes nothing
     *
     * @test
     * @dataProvider foreignGateways
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_returns_false_without_asking_mollie_for_an_order_of_another_gateway(string $paymentMethod): void
    {
        [$order, $payment] = $this->orderWithPayment('paid');
        $order->set_payment_method($paymentMethod);
        $order->save();
        $order = $this->fresh($order);
        $before = $this->freshState($order);
        $notesBefore = $this->noteIds($order);
        $fetchesBefore = $this->paymentFetches($payment['id']);

        $result = $this->service()->doPaymentForOrder($order);

        $this->assertFalse($result);
        $this->assertSame($fetchesBefore, $this->paymentFetches($payment['id']));
        $this->assertSame($before, $this->freshState($order));
        $this->assertSame([], $this->notesAddedSince($order, $notesBefore, $payment['id']));
        $this->assertSame([], $this->hooksFired);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function foreignGateways(): array
    {
        return [
            'REQ-346 a gateway of another plugin' => ['bacs'],
            'REQ-346 no gateway at all' => [''],
        ];
    }

    /**
     * Scenario: a terminal status for an untracked payment
     *   Given an order tracking a newer payment
     *   When doPaymentForOrder() runs for the older one
     *   Then it returns true, changes nothing and skips orderNeedsPayment()
     *
     * @test
     * @dataProvider supersededStatuses
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_ignores_a_terminal_webhook_for_a_payment_the_order_does_not_track(string $status, bool $alreadyPaid): void
    {
        [$order, $old] = $this->orderTrackingANewerPayment($status, $alreadyPaid);
        $before = $this->freshState($order);
        $notesBefore = $this->noteIds($order);

        $result = $this->service()->doPaymentForOrder($order, $old['id']);

        $this->assertTrue($result);
        $this->assertSame($before, $this->freshState($order));
        $this->assertSame([], $this->notesAddedSince($order, $notesBefore, $old['id']));
        $this->assertSame([], $this->hooksFired);
        $this->assertCount(1, $this->debugLinesContaining("webhook for superseded payment {$old['id']} (status {$status}) ignored"));
        $this->assertSame([], $this->debugLinesContaining('orderNeedsPayment check'));
        $this->assertSame([], $this->debugLinesContaining('does not need a payment'));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public function supersededStatuses(): array
    {
        return [
            'REQ-343 failed for an untracked payment' => ['failed', false],
            'REQ-356 canceled for an untracked payment' => ['canceled', false],
            'REQ-345 superseded outranks settled: failed for an untracked payment of a paid order' => ['failed', true],
        ];
    }

    /**
     * Scenario: expired or paid for an untracked payment
     *   Given an order tracking a newer payment
     *   When doPaymentForOrder() runs for the older one
     *   Then its handler runs between the before and after hooks
     *
     * @test
     * @dataProvider untrackedStatusesThatAreProcessed
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_hands_an_expired_or_paid_webhook_for_an_untracked_payment_to_its_handler(string $status, string $orderStatus): void
    {
        [$order, $old] = $this->orderTrackingANewerPayment($status, false);

        $result = $this->service()->doPaymentForOrder($order, $old['id']);

        $this->assertTrue($result);
        $this->assertSame($orderStatus, $this->fresh($order)->get_status());
        $this->assertSame('before_webhook_payment_action', $this->hooksFired[0] ?? null);
        $this->assertSame('after_webhook_action', $this->hooksFired[count($this->hooksFired) - 1] ?? null);
        $this->assertSame([], $this->debugLinesContaining('webhook for superseded payment'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function untrackedStatusesThatAreProcessed(): array
    {
        return [
            // onWebhookExpired handles it and adds a note.
            'REQ-357 expired for an untracked payment is left to its handler' => ['expired', 'pending'],
            'REQ-341 paid for an untracked payment is processed' => ['paid', 'processing'],
        ];
    }

    /**
     * Scenario: a redelivered paid webhook
     *   Given an order already paid by the same webhook
     *   When it is delivered again
     *   Then it answers 200 and changes nothing
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_settles_a_redelivered_paid_webhook_without_running_a_handler_again(): void
    {
        [$order, $payment] = $this->orderWithPayment('paid');
        $this->assertSame(200, $this->deliverWebhook($payment['id']));
        $this->assertSame('processing', $this->fresh($order)->get_status());
        $before = $this->freshState($order);
        $notesBefore = $this->noteIds($order);
        $this->hooksFired = [];
        $this->logger()->reset();

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $this->assertSame($before, $this->freshState($order));
        $this->assertSame([], $this->notesAddedSince($order, $notesBefore, $payment['id']));
        $this->assertSame([], $this->hooksFired);
        $this->assertCount(1, $this->debugLinesContaining("Order does not need a payment by Mollie (payment {$payment['id']})."));
    }

    /**
     * Scenario: a status without a handler
     *   Given an order tracking an open payment
     *   When doPaymentForOrder() runs
     *   Then it returns false and adds one "not processed" note
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_records_an_unhandled_status_on_the_order_and_returns_false(): void
    {
        [$order, $payment] = $this->orderWithPayment('open');
        $notesBefore = $this->noteIds($order);

        $result = $this->service()->doPaymentForOrder($order);

        $this->assertFalse($result);
        $this->assertSame('pending', $this->fresh($order)->get_status());
        $this->assertSame(
            ['Mollie - Paypal payment open ({payment}), not processed.'],
            $this->notesAddedSince($order, $notesBefore, $payment['id'])
        );
        $this->assertSame([], $this->hooksFired);
    }

    /**
     * Scenario: the verdict is logged
     *   Given an order leading to the given verdict
     *   When doPaymentForOrder() runs
     *   Then one rule.decided event holds the order, verdict and inputs
     *
     * @test
     * @dataProvider verdicts
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_writes_one_rule_decided_step_with_the_verdict_and_the_values_it_saw(
        string $scenario,
        string $verdict,
        string $inputs
    ): void {

        [$order, $paymentId] = $this->arrangeVerdict($scenario);

        $this->service()->doPaymentForOrder($order, $paymentId);

        $decided = array_values(array_filter($this->loggedEvents('rule.decided'), static function (array $record): bool {
            return ($record['context']['rule'] ?? null) === 'WebhookGuards';
        }));
        $this->assertCount(1, $decided);
        $this->assertSame($order->get_id(), $decided[0]['context']['order'] ?? null);
        $this->assertSame($verdict, $decided[0]['context']['verdict'] ?? null);
        $this->assertSame($inputs, $decided[0]['context']['inputs'] ?? null);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public function verdicts(): array
    {
        return [
            'REQ-346 not ours' => ['not_ours', 'not_ours', 'mollie=0'],
            'REQ-343 superseded' => ['superseded', 'superseded', 'mollie=1 tracks=0 status=failed'],
            'REQ-387 settled' => ['settled', 'settled', 'mollie=1 tracks=1 status=paid needsPayment=0'],
            'REQ-341 process' => ['process', 'process', 'mollie=1 tracks=1 status=paid needsPayment=1'],
        ];
    }

    /**
     * @return array{0: WC_Order, 1: string}
     */
    private function arrangeVerdict(string $scenario): array
    {
        if ($scenario === 'superseded') {
            [$order, $old] = $this->orderTrackingANewerPayment('failed', false);

            return [$order, $old['id']];
        }

        [$order, $payment] = $this->orderWithPayment('paid');
        if ($scenario === 'not_ours') {
            $order->set_payment_method('bacs');
            $order->save();
        }
        if ($scenario === 'settled') {
            $this->assertSame(200, $this->deliverWebhook($payment['id']));
        }
        $this->logger()->reset();

        return [$this->fresh($order), $payment['id']];
    }

    /**
     * @return array{0: WC_Order, 1: array<string, mixed>}
     */
    private function orderTrackingANewerPayment(string $oldStatus, bool $alreadyPaid): array
    {
        [$order] = $this->orderWithPayment('open');
        $old = $this->paymentFor($order, ['status' => $oldStatus, 'method' => 'paypal']);
        if ($alreadyPaid) {
            $order->set_status('processing');
            $order->update_meta_data('_mollie_paid_and_processed', '1');
            $order->save();
        }
        $this->logger()->reset();
        $this->hooksFired = [];

        return [$this->fresh($order), $old];
    }

    private function service(): MollieOrderService
    {
        return $this->boot()->get(MollieOrderService::class);
    }

    /**
     * @return array<int, string>
     */
    private function debugLinesContaining(string $needle): array
    {
        $lines = array_map(static function (array $record): string {
            return $record['message'];
        }, $this->logger()->records('debug'));

        return array_values(array_filter($lines, static function (string $line) use ($needle): bool {
            return strpos($line, $needle) !== false;
        }));
    }
}

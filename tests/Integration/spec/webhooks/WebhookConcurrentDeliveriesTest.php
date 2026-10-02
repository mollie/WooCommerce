<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\webhooks;

use Mockery;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\OrderLock;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;
use WC_Order;
use wpdb;

/**
 * @group integration
 * @group Webhooks
 * @group WebhookConcurrentDeliveries
 */
class WebhookConcurrentDeliveriesTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    private const CANCEL_UNPAID_ACTION = 'mollie_woocommerce_cancel_unpaid_orders';

    /**
     * Whether the site had the action scheduled before a scenario ran the plugin's init; null when none did.
     */
    private ?bool $cancelUnpaidWasScheduled = null;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->recordWebhookHooks();
    }

    public function tearDown(): void
    {
        unset($_GET['mollie_webhook_secret'], $_GET['order_id'], $_GET['key']);
        if ($this->cancelUnpaidWasScheduled === false) {
            as_unschedule_all_actions(self::CANCEL_UNPAID_ACTION);
        }
        $this->cancelUnpaidWasScheduled = null;
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Given a pending order and a payment in the given status
     * When Mollie calls the webhook once
     * Then status, notes, meta keys and hooks are the pinned ones
     *
     * @test
     * @dataProvider singleDeliveries
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     *
     * @param array<int, string> $notes
     * @param array<int, string> $metaKeys
     * @param array<int, string> $hooks
     */
    public function it_writes_the_same_status_notes_meta_and_hooks_for_a_single_webhook(
        string $paymentStatus,
        string $status,
        array $notes,
        array $metaKeys,
        array $hooks
    ): void {
        [$order, $payment] = $this->orderWithPayment($paymentStatus);
        $before = $this->freshState($order);
        $notesBefore = $this->noteIds($order);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $after = $this->freshState($order);
        $this->assertSame($status, $after['status']);
        $this->assertSame($notes, $this->notesAddedSince($order, $notesBefore, $payment['id']));
        $this->assertSame($metaKeys, $this->metaKeysChanged($before['meta'], $after['meta']));
        $this->assertSame($hooks, $this->hooksFired);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<int, string>, 4: array<int, string>}>
     */
    public function singleDeliveries(): array
    {
        return [
            'paid' => [
                'paid',
                'processing',
                ['Payment complete.', 'Order completed using Mollie - Paypal payment ({payment}).'],
                ['_mollie_paid_and_processed'],
                [
                    'before_webhook_payment_action',
                    'woocommerce_order_status_changed:pending>processing',
                    'woocommerce_payment_complete',
                    'after_webhook_action',
                ],
            ],
            'failed' => [
                'failed',
                'failed',
                ['Mollie - Paypal payment failed via Mollie ({payment}) Order status changed from Pending payment to Failed.'],
                [],
                [
                    'before_webhook_payment_action',
                    'woocommerce_order_status_changed:pending>failed',
                    'after_webhook_action',
                ],
            ],
            'canceled' => [
                'canceled',
                'pending',
                ['Mollie - Paypal payment ({payment}) cancelled .'],
                ['_mollie_cancelled_payment_id'],
                ['before_webhook_payment_action', 'after_webhook_action'],
            ],
            // cancelOrderAtMollie() reads only _mollie_order_id.
            'expired' => [
                'expired',
                'cancelled',
                [
                    'Order contains Mollie payment method, but not a valid Mollie Order ID. Canceling order failed.',
                    'Order status changed from Pending payment to Cancelled.',
                    'Mollie - Paypal payment expired ({payment}).',
                ],
                [],
                [
                    'before_webhook_payment_action',
                    'woocommerce_order_status_changed:pending>cancelled',
                    'after_webhook_action',
                ],
            ],
            'pending' => [
                'pending',
                'pending',
                ['Mollie - Paypal payment pending ({payment}).'],
                [],
                ['before_webhook_payment_action', 'after_webhook_action'],
            ],
        ];
    }

    /**
     * Given an order loaded before another request paid it
     * When doPaymentForOrder() runs with that order
     * Then the payment is completed once
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_does_not_complete_a_payment_twice_for_an_order_loaded_before_another_request_paid_it(): void
    {
        [$order, $payment] = $this->orderWithPayment('paid');
        $stale = wc_get_order($order->get_id());
        $this->assertInstanceOf(WC_Order::class, $stale);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));
        $this->assertTrue($this->fresh($order)->is_paid());
        $this->assertSame('pending', $stale->get_status(), 'The scenario needs an order loaded before the payment was processed.');

        $this->container->get(MollieOrderService::class)->doPaymentForOrder($stale);

        $this->assertCount(1, $this->notesContaining($order, 'Order completed using'));
        $this->assertSame(1, $this->fired('woocommerce_payment_complete'));
        $this->assertSame(1, $this->fired('before_webhook_payment_action'));
        $this->assertSame(1, $this->fired('after_webhook_action'));
    }

    /**
     * Given the order lock is held elsewhere
     * When Mollie calls the webhook
     * Then the payment was fetched before the 503
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_fetches_the_payment_from_mollie_before_waiting_for_the_order_lock(): void
    {
        [$order, $payment] = $this->orderWithPayment('paid');
        $fetchesBefore = $this->paymentFetches($payment['id']);

        $holder = $this->holdTheLock((string) $order->get_id());
        try {
            $status = $this->deliverWebhook($payment['id']);
        } finally {
            $this->releaseTheLock($holder, (string) $order->get_id());
        }

        $this->assertSame(503, $status);
        $this->assertGreaterThan($fetchesBefore, $this->paymentFetches($payment['id']));
    }

    /**
     * Given the order lock is held elsewhere
     * When Mollie calls the REST webhook, then retries after the release
     * Then 503 with the order untouched, then 200 and paid
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::callback
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_answers_503_on_the_rest_webhook_and_writes_nothing_while_the_order_lock_is_held(): void
    {
        [$order, $payment] = $this->orderWithPayment('paid');
        $before = $this->freshState($order);
        $notesBefore = $this->notes($order);

        $holder = $this->holdTheLock((string) $order->get_id());
        try {
            $status = $this->deliverWebhook($payment['id']);
        } finally {
            $this->releaseTheLock($holder, (string) $order->get_id());
        }

        $this->assertSame(503, $status);
        $this->assertSame($before, $this->freshState($order));
        $this->assertSame($notesBefore, $this->notes($order));
        $this->assertSame(0, $this->fired('woocommerce_payment_complete'));

        $this->assertSame(200, $this->deliverWebhook($payment['id']));
        $this->assertTrue($this->fresh($order)->is_paid());
    }

    /**
     * Given the order lock is held elsewhere
     * When Mollie calls the WC-API webhook
     * Then 503 with the order untouched
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::onWebhookAction
     */
    public function it_answers_503_on_the_wc_api_webhook_and_writes_nothing_while_the_order_lock_is_held(): void
    {
        [$order, $payment] = $this->orderWithPayment('paid');
        $before = $this->freshState($order);
        $notesBefore = $this->notes($order);
        $_GET['mollie_webhook_secret'] = CanaryData::WEBHOOK_SECRET;
        $_GET['order_id'] = (string) $order->get_id();
        $_GET['key'] = $order->get_order_key();
        $httpResponse = Mockery::spy(HttpResponse::class);
        $service = $this->legacyWebhookService($payment['id'], $httpResponse);

        $holder = $this->holdTheLock((string) $order->get_id());
        try {
            $service->onWebhookAction();
        } finally {
            $this->releaseTheLock($holder, (string) $order->get_id());
        }

        $httpResponse->shouldHaveReceived('setHttpResponseCode')->with(503)->once();
        $this->assertSame($before, $this->freshState($order));
        $this->assertSame($notesBefore, $this->notes($order));
        $this->assertSame(0, $this->fired('woocommerce_payment_complete'));
    }

    /**
     * Given a pending order past its gateway's expiry setting, whose payment is paid at Mollie
     * And the order lock is held elsewhere, as by the webhook that is paying it
     * When the cancel-unpaid action runs
     * Then the order is not cancelled: the next run looks at it again
     *
     * @test
     */
    public function it_does_not_cancel_an_unpaid_order_while_the_order_lock_is_held(): void
    {
        $order = $this->orderPastItsExpirySetting();

        $holder = $this->holdTheLock((string) $order->get_id());
        try {
            do_action(self::CANCEL_UNPAID_ACTION);
        } finally {
            $this->releaseTheLock($holder, (string) $order->get_id());
        }

        $this->assertSame('pending', $this->fresh($order)->get_status());
        $this->assertSame([], $this->notesContaining($order, 'Unpaid order cancelled'));
    }

    /**
     * Given the same order with its lock free
     * When the cancel-unpaid action runs
     * Then the order is paid, as before the lock existed
     *
     * @test
     */
    public function it_pays_an_unpaid_order_past_its_expiry_setting_when_mollie_says_paid(): void
    {
        $order = $this->orderPastItsExpirySetting();

        do_action(self::CANCEL_UNPAID_ACTION);

        $this->assertTrue($this->fresh($order)->is_paid());
    }

    /**
     * Given the order lock is held elsewhere
     * When checkPaymentForUnpaidOrder() runs
     * Then it returns true with the order untouched
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::checkPaymentForUnpaidOrder
     */
    public function it_leaves_the_order_alone_for_other_callers_while_the_order_lock_is_held(): void
    {
        [$order] = $this->orderWithPayment('paid');
        $before = $this->freshState($order);
        $notesBefore = $this->notes($order);

        $holder = $this->holdTheLock((string) $order->get_id());
        try {
            $result = $this->container->get(MollieOrderService::class)->checkPaymentForUnpaidOrder($this->fresh($order));
        } finally {
            $this->releaseTheLock($holder, (string) $order->get_id());
        }

        $this->assertTrue($result);
        $this->assertSame($before, $this->freshState($order));
        $this->assertSame($notesBefore, $this->notes($order));
    }

    /**
     * Given the order lock is held elsewhere
     * When both webhooks are called unauthenticated
     * Then 401, no Mollie call, no lock wait
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::registerRoutes
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::onWebhookAction
     */
    public function it_rejects_an_unauthenticated_webhook_before_any_lock_or_mollie_call_on_both_routes(): void
    {
        [$order, $payment] = $this->orderWithPayment('paid');
        $before = $this->freshState($order);
        $requestsBefore = count($this->fakeMollie()->requests('GET', 'payments'));
        $_GET['order_id'] = (string) $order->get_id();
        $_GET['key'] = 'wc_order_not_the_key';
        $httpResponse = Mockery::spy(HttpResponse::class);
        $service = $this->legacyWebhookService($payment['id'], $httpResponse);

        $holder = $this->holdTheLock((string) $order->get_id());
        try {
            $restStatus = $this->deliverWebhook('tr_unknownToTheShop', false);
            $service->onWebhookAction();
        } finally {
            $this->releaseTheLock($holder, (string) $order->get_id());
        }

        $this->assertSame(401, $restStatus);
        $httpResponse->shouldHaveReceived('setHttpResponseCode')->with(401)->once();
        $httpResponse->shouldNotHaveReceived('setHttpResponseCode', [503]);
        $this->assertSame($requestsBefore, count($this->fakeMollie()->requests('GET', 'payments')));
        $this->assertSame([], $this->loggedEvents('order.lock_timeout'));
        $this->assertSame($before, $this->freshState($order));
    }

    /**
     * filter_input(INPUT_POST) is empty on the CLI.
     *
     * @return MollieOrderService&Mockery\MockInterface
     */
    private function legacyWebhookService(string $paymentId, HttpResponse $httpResponse): MollieOrderService
    {
        $real = $this->boot()->get(MollieOrderService::class);
        $service = Mockery::mock(MollieOrderService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        foreach ((new \ReflectionClass(MollieOrderService::class))->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $property->setAccessible(true);
            if ($property->isInitialized($real)) {
                $property->setValue($service, $property->getValue($real));
            }
        }
        $response = new \ReflectionProperty(MollieOrderService::class, 'httpResponse');
        $response->setAccessible(true);
        $response->setValue($service, $httpResponse);
        $service->shouldReceive('getPaymentIdFromRequest')->andReturn($paymentId);

        return $service;
    }

    /**
     * A pending PayPal order the cancel-unpaid action selects, whose payment is paid at Mollie.
     */
    private function orderPastItsExpirySetting(): WC_Order
    {
        // The plugin attaches its listener to the action on init, so this boot owns both.
        $this->cancelUnpaidWasScheduled = as_next_scheduled_action(self::CANCEL_UNPAID_ACTION) !== false;
        $this->container = $this->bootExpressOwning(['init', self::CANCEL_UNPAID_ACTION]);
        [$order] = $this->orderWithPayment('paid');
        $this->setGatewaySettingsForTest('paypal', ['activate_expiry_days_setting' => 'yes', 'order_dueDate' => '10']);
        $this->lastModifiedAt($order, time() - 3600);
        do_action('init');
        $this->assertNotFalse(
            has_action(self::CANCEL_UNPAID_ACTION),
            'Nothing listens on the cancel-unpaid action, so the scenario proves nothing.'
        );

        return $order;
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

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
use Psr\Container\ContainerInterface;
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

    private const GATEWAY = 'mollie_wc_gateway_paypal';

    private const META_WRITTEN_BY_ORDER_EMAILS = '_mollie_payment_instructions';

    private ?ContainerInterface $container = null;

    /**
     * @var array<int, string>
     */
    private array $hooksFired = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->hooksFired = [];
        $this->addTestFilter(self::PLUGIN_ID . '_before_webhook_payment_action', function (): void {
            $this->hooksFired[] = 'before_webhook_payment_action';
        });
        $this->addTestFilter(self::PLUGIN_ID . '_after_webhook_action', function (): void {
            $this->hooksFired[] = 'after_webhook_action';
        });
        $this->addTestFilter('woocommerce_payment_complete', function (): void {
            $this->hooksFired[] = 'woocommerce_payment_complete';
        });
        $this->addTestFilter('woocommerce_order_status_changed', function ($orderId, $from, $to): void {
            $this->hooksFired[] = "woocommerce_order_status_changed:{$from}>{$to}";
        }, 10, 3);
    }

    public function tearDown(): void
    {
        unset($_GET['mollie_webhook_secret'], $_GET['order_id'], $_GET['key']);
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

    private function boot(): ContainerInterface
    {
        if ($this->container === null) {
            $this->removePluginListenersOfEarlierBoots();
            $this->container = $this->bootExpress();
        }

        return $this->container;
    }

    private function removePluginListenersOfEarlierBoots(): void
    {
        foreach ($GLOBALS['wp_filter'] as $hook => $listeners) {
            foreach ($listeners->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    if ($this->isPluginCallback($callback['function'])) {
                        remove_filter((string) $hook, $callback['function'], $priority);
                    }
                }
            }
        }
    }

    /**
     * @param mixed $function
     */
    private function isPluginCallback($function): bool
    {
        $owner = null;
        if (is_array($function) && is_object($function[0] ?? null)) {
            $owner = get_class($function[0]);
        } elseif ($function instanceof \Closure) {
            $closure = new \ReflectionFunction($function);
            $bound = $closure->getClosureThis();
            $scope = $closure->getClosureScopeClass();
            $owner = $bound !== null ? get_class($bound) : ($scope !== null ? $scope->getName() : null);
        }

        return $owner !== null
            && (strpos($owner, 'Mollie\\WooCommerce\\') === 0 || strpos($owner, 'Inpsyde\\PaymentGateway\\') === 0);
    }

    /**
     * @return array{0: WC_Order, 1: array<string, mixed>}
     */
    private function orderWithPayment(string $paymentStatus): array
    {
        $this->boot();
        $order = $this->pendingOrder(self::GATEWAY);
        $payment = $this->paymentFor($order, ['status' => $paymentStatus, 'method' => 'paypal']);
        $order->update_meta_data('_mollie_payment_id', $payment['id']);
        $order->set_transaction_id($payment['id']);
        $order->save();
        $this->logger()->reset();
        $this->hooksFired = [];

        return [$this->fresh($order), $payment];
    }

    /**
     * @param array<string, mixed> $outcome
     * @return array<string, mixed>
     */
    private function paymentFor(WC_Order $order, array $outcome): array
    {
        $total = $this->formattedTotal($order);
        $payload = [
            'amount' => ['currency' => 'EUR', 'value' => $total],
            'description' => 'Order ' . $order->get_id(),
            'lines' => [[
                'description' => 'Everything',
                'quantity' => 1,
                'unitPrice' => ['currency' => 'EUR', 'value' => $total],
                'totalAmount' => ['currency' => 'EUR', 'value' => $total],
            ]],
            'redirectUrl' => 'https://shop.example/checkout/order-received/',
            'payment' => ['webhookUrl' => 'https://shop.example/wp-json/mollie/v1/webhook'],
            'metadata' => ['order_id' => $order->get_id()],
        ];
        $client = $this->boot()->get('SDK.api_helper')->getApiClient(CanaryData::LIVE_API_KEY);
        $session = $client->performHttpCall('POST', 'sessions', (string) wp_json_encode($payload));

        return $this->fakeMollie()->completeSession($session->id, $outcome);
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

    private function fresh(WC_Order $order): WC_Order
    {
        clean_post_cache($order->get_id());
        wp_cache_delete(WC_Order::generate_meta_cache_key($order->get_id(), 'orders'), 'orders');
        $fresh = wc_get_order($order->get_id());
        $this->assertInstanceOf(WC_Order::class, $fresh);

        return $fresh;
    }

    /**
     * @return array{status: string, transaction_id: string, meta: array<string, array<int, mixed>>}
     */
    private function freshState(WC_Order $order): array
    {
        $fresh = $this->fresh($order);
        $meta = [];
        foreach ($fresh->get_meta_data() as $item) {
            $data = $item->get_data();
            $meta[$data['key']][] = $data['value'];
        }
        ksort($meta);

        return [
            'status' => $fresh->get_status(),
            'transaction_id' => $fresh->get_transaction_id(),
            'meta' => $meta,
        ];
    }

    /**
     * @param array<string, array<int, mixed>> $before
     * @param array<string, array<int, mixed>> $after
     * @return array<int, string>
     */
    private function metaKeysChanged(array $before, array $after): array
    {
        $changed = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            if ($key === self::META_WRITTEN_BY_ORDER_EMAILS) {
                continue;
            }
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $changed[] = (string) $key;
            }
        }
        sort($changed);

        return $changed;
    }

    /**
     * @return array<int, int>
     */
    private function noteIds(WC_Order $order): array
    {
        return array_map(static function ($note): int {
            return (int) $note->id;
        }, wc_get_order_notes(['order_id' => $order->get_id()]));
    }

    /**
     * Oldest first, the payment id as {payment}.
     *
     * @param array<int, int> $idsBefore
     * @return array<int, string>
     */
    private function notesAddedSince(WC_Order $order, array $idsBefore, string $paymentId): array
    {
        $added = array_filter(wc_get_order_notes(['order_id' => $order->get_id()]), static function ($note) use ($idsBefore): bool {
            return !in_array((int) $note->id, $idsBefore, true);
        });
        usort($added, static function ($a, $b): int {
            return (int) $a->id <=> (int) $b->id;
        });

        $notes = array_map(static function ($note) use ($paymentId): string {
            return str_replace($paymentId, '{payment}', (string) $note->content);
        }, $added);

        return array_values(array_filter($notes, static function (string $note): bool {
            return !self::isEmailSentNote($note);
        }));
    }

    private static function isEmailSentNote(string $note): bool
    {
        return preg_match('/^Email ".+" sent\.$/', $note) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function notes(WC_Order $order): array
    {
        return array_map(static function ($note): string {
            return (string) $note->content;
        }, wc_get_order_notes(['order_id' => $order->get_id()]));
    }

    /**
     * @return array<int, string>
     */
    private function notesContaining(WC_Order $order, string $needle): array
    {
        return array_values(array_filter($this->notes($order), static function (string $note) use ($needle): bool {
            return stripos($note, $needle) !== false;
        }));
    }

    private function fired(string $hook): int
    {
        return count(array_keys($this->hooksFired, $hook, true));
    }

    private function paymentFetches(string $paymentId): int
    {
        return count(array_filter($this->fakeMollie()->requests('GET', 'payments/' . $paymentId), static function (array $request) use ($paymentId): bool {
            return $request['path'] === 'payments/' . $paymentId;
        }));
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

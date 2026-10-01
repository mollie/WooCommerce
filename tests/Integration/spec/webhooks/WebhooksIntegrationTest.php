<?php

namespace Mollie\WooCommerceTests\Integration\spec\webhooks;

use Mockery;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;
use Mollie\WooCommerceTests\Integration\API\Traits\APIMockTrait;
use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\Webhooks\WebhookHandler;
use Mollie\WooCommerce\Payment\Webhooks\WebhookSecret;
use Mollie\WooCommerce\SDK\HttpResponse;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;
use Mollie\WooCommerce\Payment\PaymentFactory;

use function Brain\Monkey\Functions\when;

class WebhooksIntegrationTest extends IntegrationMockedTestCase
{
    use APIMockTrait;

    /**
     * @var MollieOrderService
     */
    private $webhookService;

    public function setUp(): void
    {
        parent::setUp();
        $this->initializeApiMock();

        // Clear any existing globals
        unset($_GET['order_id'], $_GET['key'], $_POST['id']);
    }

    /**
     * Helper method to set up webhook request environment
     */
    protected function setupWebhookRequest(int $orderId, string $orderKey, string $paymentId): void
    {
        $_GET['order_id'] = (string)$orderId;
        $_GET['key'] = $orderKey;
        $_POST['id'] = $paymentId; // This won't be used by filter_input, but keep for consistency
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    /**
     * Helper method to create mocked webhook service
     *
     * @param ContainerInterface $container
     * @param string $paymentId
     * @return \Mockery\MockInterface|MollieOrderService
     */
    protected function createMockedWebhookService(ContainerInterface $container, string $paymentId)
    {
        $webhookService = Mockery::mock(MollieOrderService::class, [
            $container->get('SDK.HttpResponse'),
            $container->get(Logger::class),
            $container->get(PaymentFactory::class),
            $container->get('settings.data_helper'),
            $container->get('shared.plugin_id'),
            $container,
            $container->get(WebhookHandler::class)
        ])->makePartial()->shouldAllowMockingProtectedMethods();

        $webhookService->shouldReceive('getPaymentIdFromRequest')
            ->andReturn($paymentId);

        return $webhookService;
    }

    /**
     * Test that webhook processes only one order when race conditions occur.
     *
     * This test verifies that when multiple webhook calls arrive simultaneously
     * for the same order, only one payment is processed and subsequent calls
     * are properly handled to prevent duplicate processing.
     *
     * @test
     * @group integration
     * @group Webhooks
     */
    public function it_processes_only_one_order_when_race_conditions()
    {
        $order = $this->getConfiguredOrder(
            1,
            'mollie_wc_gateway_ideal',
            ['simple'],
            [],
            false
        );

        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $transactionId = $order->get_transaction_id();

        $paymentData = [
            'id' => $transactionId,
        ];

        $this->mockSuccessfulPaymentGet($transactionId, 'paid', [
            'metadata' => ['order_id' => $orderId],
            'method' => 'ideal',
            'mode' => 'test'
        ]);

        $mockedServices = $this->getMockedApiServices();
        $container = $this->bootstrapModule($mockedServices);
        $this->setupWebhookRequest($orderId, $orderKey, $paymentData['id']);

        $this->webhookService = $this->createMockedWebhookService($container, $paymentData['id']);
        $this->webhookService->onWebhookAction();

        $order = wc_get_order($orderId);
        $this->assertEquals('processing', $order->get_status());

        // Verify that subsequent webhook calls with the same payment ID are handled gracefully
        // (This simulates the race condition scenario)
        $this->webhookService->onWebhookAction();

        // Order status should remain the same, not be processed again
        $order = wc_get_order($orderId);
        $this->assertEquals('processing', $order->get_status());
    }

    /**
     * Test concurrent webhook calls for the same payment (race condition simulation)
     *
     * @test
     * @group integration
     * @group Webhooks
     */
    public function it_handles_concurrent_webhook_calls_gracefully()
    {
        $order = $this->getConfiguredOrder(
            1,
            'mollie_wc_gateway_ideal',
            ['simple'],
            [],
            false
        );

        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $transactionId = $order->get_transaction_id();

        $this->mockSuccessfulPaymentGet($transactionId, 'paid', [
            'metadata' => ['order_id' => $orderId],
            'method' => 'ideal',
            'mode' => 'test'
        ]);

        $mockedServices = $this->getMockedApiServices();
        $container = $this->bootstrapModule($mockedServices);

        // Set up the webhook request parameters
        $this->setupWebhookRequest($orderId, $orderKey, $transactionId);

        // Get two instances of the webhook service to simulate concurrent requests
        $webhookService1 = $this->createMockedWebhookService($container, uniqid('ord_'));
        $webhookService2 = $this->createMockedWebhookService($container, $transactionId);

        // First webhook call should process successfully
        $webhookService1->onWebhookAction();

        $order = wc_get_order($orderId);
        $this->assertEquals('pending', $order->get_status());

        // Second webhook call should be handled gracefully (idempotency)
        // This simulates a race condition where the same webhook arrives multiple times
        $webhookService2->onWebhookAction();

        // Order status should remain the same
        $order = wc_get_order($orderId);
        $this->assertEquals('processing', $order->get_status());

        // Verify no duplicate processing occurred by checking order notes
        $notes = wc_get_order_notes(['order_id' => $orderId]);
        $paymentNotes = array_filter($notes, function ($note) {
            return strpos($note->content, 'Order completed') !== false;
        });

        // Should only have one payment started note
        $this->assertCount(1, $paymentNotes, 'Should only process payment once, even with concurrent webhooks');
    }

    /**
     * Scenario: A late paid webhook does not revert an already-refunded authorized order (PIWOO-923)
     *   Given an authorize-capture (pay-later) order that was authorized, captured and then refunded
     *     (carrying _mollie_authorized and _mollie_paid_and_processed, WooCommerce status "refunded")
     *   When a late 'paid' webhook arrives whose Mollie payment reports a non-zero amountRefunded
     *   Then the order stays "refunded" and no "Order completed" note is added
     *     (payment_complete() is not re-triggered and stock is not re-reduced)
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookPaid
     */
    public function it_does_not_revert_a_refunded_authorized_order_on_late_paid_webhook()
    {
        $order = $this->getConfiguredOrder(
            1,
            'mollie_wc_gateway_ideal',
            ['simple'],
            [],
            false
        );

        // Simulate an authorize-capture order that was authorized, captured, then refunded.
        $order->update_meta_data('_mollie_authorized', '1');
        $order->update_meta_data('_mollie_paid_and_processed', '1');
        $order->set_status('refunded');
        $order->save();

        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $transactionId = $order->get_transaction_id();

        // The late webhook: the payment is 'paid' at Mollie but already carries a refund.
        $this->mockSuccessfulPaymentGet($transactionId, 'paid', [
            'metadata' => ['order_id' => $orderId],
            'method' => 'klarna',
            'mode' => 'test',
            'amountRefunded' => (object) ['value' => '10.00', 'currency' => 'EUR'],
        ]);

        $mockedServices = $this->getMockedApiServices();
        $container = $this->bootstrapModule($mockedServices);
        $this->setupWebhookRequest($orderId, $orderKey, $transactionId);

        $this->webhookService = $this->createMockedWebhookService($container, $transactionId);
        $this->webhookService->onWebhookAction();

        $order = wc_get_order($orderId);
        $this->assertEquals(
            'refunded',
            $order->get_status(),
            'A late paid webhook for a refunded order must not flip it back to processing.'
        );

        // And no "Order completed" note should have been added by a second payment_complete().
        $notes = wc_get_order_notes(['order_id' => $orderId]);
        $completedNotes = array_filter($notes, function ($note) {
            return strpos($note->content, 'Order completed') !== false;
        });
        $this->assertCount(
            0,
            $completedNotes,
            'No "Order completed" note should be added for an already-refunded order.'
        );
    }

    /**
     * Scenario: An expired webhook still cancels an unpaid on-hold order (PIWOO-#1284 regression)
     *   Given a confirmationDelayed gateway order (banktransfer/iDEAL) sitting UNPAID at on-hold —
     *     WC_Order::needs_payment() is false there, but the order is not settled (no paid/authorized meta)
     *   When Mollie expires the tracked payment and calls the webhook
     *   Then the order is cancelled, not left stranded on-hold
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookExpired
     */
    public function it_cancels_an_unpaid_on_hold_order_when_payment_expires()
    {
        $order = $this->makeUnpaidOnHoldOrder();
        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $transactionId = $order->get_transaction_id();

        $this->mockSuccessfulPaymentGet($transactionId, 'expired', [
            'metadata' => ['order_id' => $orderId],
            'method' => 'ideal',
            'mode' => 'test',
        ]);

        $container = $this->bootstrapModule($this->getMockedApiServices());
        $this->setupWebhookRequest($orderId, $orderKey, $transactionId);

        $this->webhookService = $this->createMockedWebhookService($container, $transactionId);
        $this->webhookService->onWebhookAction();

        $order = wc_get_order($orderId);
        $this->assertEquals(
            'cancelled',
            $order->get_status(),
            'An expired webhook must cancel an unpaid on-hold order, not leave it stranded on-hold.'
        );
    }

    /**
     * Scenario: A canceled webhook still moves an unpaid on-hold order off on-hold (PIWOO-#1284 regression)
     *   Given a confirmationDelayed gateway order sitting UNPAID at on-hold
     *   When Mollie cancels the tracked payment and calls the webhook
     *   Then the order leaves on-hold (to the configured cancelled-payments status, 'pending' by default)
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookCanceled
     */
    public function it_moves_an_unpaid_on_hold_order_off_hold_when_payment_is_canceled()
    {
        $order = $this->makeUnpaidOnHoldOrder();
        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $transactionId = $order->get_transaction_id();

        $this->mockSuccessfulPaymentGet($transactionId, 'canceled', [
            'metadata' => ['order_id' => $orderId],
            'method' => 'ideal',
            'mode' => 'test',
        ]);

        $container = $this->bootstrapModule($this->getMockedApiServices());
        $this->setupWebhookRequest($orderId, $orderKey, $transactionId);

        $this->webhookService = $this->createMockedWebhookService($container, $transactionId);
        $this->webhookService->onWebhookAction();

        $order = wc_get_order($orderId);
        $this->assertNotEquals(
            'on-hold',
            $order->get_status(),
            'A canceled webhook must not leave an unpaid on-hold order stranded on-hold.'
        );
        $this->assertEquals(
            'pending',
            $order->get_status(),
            'A canceled payment returns the order to the default cancelled-payments status (pending).'
        );
    }

    /**
     * Scenario: A failed webhook still fails an unpaid on-hold order (PIWOO-#1284 regression)
     *   Given a confirmationDelayed gateway order sitting UNPAID at on-hold
     *   When Mollie marks the tracked payment failed and calls the webhook
     *   Then the order is moved to failed, not left stranded on-hold
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookFailed
     */
    public function it_fails_an_unpaid_on_hold_order_when_payment_fails()
    {
        $order = $this->makeUnpaidOnHoldOrder();
        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $transactionId = $order->get_transaction_id();

        $this->mockSuccessfulPaymentGet($transactionId, 'failed', [
            'metadata' => ['order_id' => $orderId],
            'method' => 'ideal',
            'mode' => 'test',
        ]);

        $container = $this->bootstrapModule($this->getMockedApiServices());
        $this->setupWebhookRequest($orderId, $orderKey, $transactionId);

        $this->webhookService = $this->createMockedWebhookService($container, $transactionId);
        $this->webhookService->onWebhookAction();

        $order = wc_get_order($orderId);
        $this->assertEquals(
            'failed',
            $order->get_status(),
            'A failed webhook must fail an unpaid on-hold order, not leave it stranded on-hold.'
        );
    }

    /**
     * Scenario: A late failed webhook does not fail a cancelled order that has no settled meta (PR #1284)
     *   Given an order that reached the cancelled status without ever being paid through Mollie, so it
     *     carries neither _mollie_paid_and_processed nor _mollie_authorized — the state WooCommerce's
     *     hold-stock rule (or an admin) leaves an abandoned order in
     *   When a late 'failed' webhook for that abandoned payment arrives
     *   Then the order stays cancelled: onWebhookFailed bails on the final status
     *
     * Before this change the shared guard's !needs_payment() clause covered this by accident. That
     * clause is gone (it also stranded on-hold and zero-total orders), so onWebhookFailed now owns an
     * explicit isFinalOrderStatus() check like onWebhookCanceled and onWebhookExpired.
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookFailed
     */
    public function it_does_not_fail_a_cancelled_order_without_settled_meta_on_late_failed_webhook()
    {
        $order = $this->makeFinalStatusOrderWithoutSettledMeta('cancelled');
        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $transactionId = $order->get_transaction_id();

        $this->mockSuccessfulPaymentGet($transactionId, 'failed', [
            'metadata' => ['order_id' => $orderId],
            'method' => 'ideal',
            'mode' => 'test',
        ]);

        $container = $this->bootstrapModule($this->getMockedApiServices());
        $this->setupWebhookRequest($orderId, $orderKey, $transactionId);

        $this->webhookService = $this->createMockedWebhookService($container, $transactionId);
        $this->webhookService->onWebhookAction();

        $order = wc_get_order($orderId);
        $this->assertEquals(
            'cancelled',
            $order->get_status(),
            'A late failed webhook must not move a cancelled order to failed.'
        );
    }

    /**
     * Scenario: A late failed webhook does not fail a refunded order (PIWOO-923, failed path)
     *   Given an order that is refunded and carries no Mollie settled meta
     *   When a late 'failed' webhook arrives
     *   Then the order stays refunded and its stock is not restored a second time
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookFailed
     */
    public function it_does_not_fail_a_refunded_order_on_late_failed_webhook()
    {
        $order = $this->makeFinalStatusOrderWithoutSettledMeta('refunded');
        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $transactionId = $order->get_transaction_id();

        $this->mockSuccessfulPaymentGet($transactionId, 'failed', [
            'metadata' => ['order_id' => $orderId],
            'method' => 'klarna',
            'mode' => 'test',
        ]);

        $container = $this->bootstrapModule($this->getMockedApiServices());
        $this->setupWebhookRequest($orderId, $orderKey, $transactionId);

        $this->webhookService = $this->createMockedWebhookService($container, $transactionId);
        $this->webhookService->onWebhookAction();

        $order = wc_get_order($orderId);
        $this->assertEquals(
            'refunded',
            $order->get_status(),
            'A late failed webhook must not move a refunded order to failed.'
        );
    }

    /**
     * Scenario: A webhook for a stale payment attempt that no order tracks any more exits cleanly (PIWOO-949, GH#1292)
     *   Given an order tracked to its latest payment attempt (transaction id and _mollie_payment_id)
     *   And an earlier attempt on the same order that Mollie now reports as expired
     *   When the WC-API webhook for the earlier attempt arrives with a valid order id and key
     *   Then the fallback runs once with the request's order id, key and payment id
     *   And the webhook completes without a PHP error or warning
     *   And the order is not cancelled, because a newer payment is pending
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::onWebhookAction
     */
    public function it_returns_cleanly_when_no_order_matches_a_stale_payment_id()
    {
        [$orderId, $orderKey, $stalePaymentId] = $this->makeOrderWithStalePaymentAttempt();
        $container = $this->bootstrapModule($this->getMockedApiServices());
        $this->setupWebhookRequest($orderId, $orderKey, $stalePaymentId);

        $this->webhookService = $this->create_webhook_service_with_response_spy(
            $container,
            $stalePaymentId,
            Mockery::spy(HttpResponse::class)
        );
        $this->webhookService->shouldReceive('onWebhookActionFallback')
            ->once()
            ->with((string) $orderId, $orderKey, $stalePaymentId)
            ->passthru();

        $this->webhookService->onWebhookAction();

        $this->assertEquals(
            'pending',
            wc_get_order($orderId)->get_status(),
            'A stale expired attempt must not cancel an order that has a newer pending payment.'
        );
        $staleNotes = array_filter(
            wc_get_order_notes(['order_id' => $orderId]),
            static function ($note) use ($stalePaymentId): bool {
                return strpos($note->content, $stalePaymentId) !== false
                    && strpos($note->content, 'not cancelled because of another pending payment') !== false;
            }
        );
        $this->assertCount(1, $staleNotes, 'The fallback must process the stale payment exactly once.');
    }

    /**
     * Scenario: After the fallback, the matched-order checks do not run (PIWOO-949)
     *   Given an order tracked to its latest payment attempt
     *   When the WC-API webhook for an earlier, expired attempt arrives
     *   Then the payment is processed exactly once, by the fallback, for the stale payment id
     *   And no 401 "found order is not the same as provided order" response is set
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::onWebhookAction
     */
    public function it_does_not_run_the_matched_order_checks_after_the_fallback()
    {
        [$orderId, $orderKey, $stalePaymentId] = $this->makeOrderWithStalePaymentAttempt();
        $container = $this->bootstrapModule($this->getMockedApiServices());
        $this->setupWebhookRequest($orderId, $orderKey, $stalePaymentId);

        $httpResponse = Mockery::spy(HttpResponse::class);
        $this->webhookService = $this->create_webhook_service_with_response_spy(
            $container,
            $stalePaymentId,
            $httpResponse
        );
        $this->webhookService->shouldReceive('doPaymentForOrder')
            ->once()
            ->with(Mockery::type(\WC_Order::class), $stalePaymentId)
            ->passthru();

        $this->webhookService->onWebhookAction();

        $httpResponse->shouldNotHaveReceived('setHttpResponseCode', [401]);
    }

    /**
     * Scenario: The webhook keeps the HTTP code the fallback set (PIWOO-949)
     *   Given a request authenticated with the webhook secret
     *   And a payment id that no order tracks
     *   When the fallback rejects the request (order not found, or invalid order key)
     *   Then exactly one HTTP code is set, the one the fallback chose
     *   And the webhook completes without a PHP error or warning
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::onWebhookAction
     * @dataProvider fallbackRejectionProvider
     */
    public function it_keeps_the_http_code_the_fallback_set(string $rejection, int $expectedCode)
    {
        $order = $this->getConfiguredOrder(1, 'mollie_wc_gateway_ideal', ['simple'], [], false);
        $orderId = $rejection === 'order not found' ? $order->get_id() + 100000 : $order->get_id();
        $orderKey = $rejection === 'invalid key' ? 'wc_order_notTheRealKey' : $order->get_order_key();
        $stalePaymentId = 'tr_staleAttempt949';

        $container = $this->bootstrapModule($this->getMockedApiServices());
        $this->setupWebhookRequest($orderId, $orderKey, $stalePaymentId);
        $_GET['mollie_webhook_secret'] = $container->get(WebhookSecret::class)->getOrCreate();

        $httpResponse = Mockery::spy(HttpResponse::class);
        $this->webhookService = $this->create_webhook_service_with_response_spy(
            $container,
            $stalePaymentId,
            $httpResponse
        );

        try {
            $this->webhookService->onWebhookAction();
        } finally {
            unset($_GET['mollie_webhook_secret']);
        }

        $httpResponse->shouldHaveReceived('setHttpResponseCode')->once();
        $httpResponse->shouldHaveReceived('setHttpResponseCode', [$expectedCode]);
    }

    public function fallbackRejectionProvider(): array
    {
        return [
            'order not found' => ['order not found', 404],
            'invalid key' => ['invalid key', 401],
        ];
    }

    /**
     * Scenario: A webhook whose payment id matches an order is processed as before (PIWOO-949 regression guard)
     *   Given an order that holds the webhook's payment id, as its transaction id or only in _mollie_payment_id
     *   And Mollie reports that payment as paid
     *   When the WC-API webhook arrives with the order's id and key
     *   Then the fallback is not used
     *   And the payment is processed once for the matched order
     *   And the order is processing
     *
     * @test
     * @group integration
     * @group Webhooks
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::onWebhookAction
     * @dataProvider matchedByProvider
     */
    public function it_processes_the_matched_order_without_the_fallback(string $matchedBy)
    {
        $order = $this->getConfiguredOrder(1, 'mollie_wc_gateway_ideal', ['simple'], [], false);
        $orderId = $order->get_id();
        $transactionId = $order->get_transaction_id();
        if ($matchedBy === 'Mollie meta') {
            // Only the meta lookup can find this order: its transaction id is empty.
            $order->set_transaction_id('');
            $order->update_meta_data('_mollie_payment_id', $transactionId);
            $order->save();
        }

        $this->mockSuccessfulPaymentGet($transactionId, 'paid', [
            'metadata' => ['order_id' => $orderId],
            'method' => 'ideal',
            'mode' => 'test',
        ]);
        $container = $this->bootstrapModule($this->getMockedApiServices());
        $this->setupWebhookRequest($orderId, $order->get_order_key(), $transactionId);

        $this->webhookService = $this->create_webhook_service_with_response_spy(
            $container,
            $transactionId,
            Mockery::spy(HttpResponse::class)
        );
        $this->webhookService->shouldNotReceive('onWebhookActionFallback');
        $this->webhookService->shouldReceive('doPaymentForOrder')
            ->once()
            ->with(Mockery::on(static function ($matched) use ($orderId): bool {
                return $matched instanceof \WC_Order && $matched->get_id() === $orderId;
            }))
            ->passthru();

        $this->webhookService->onWebhookAction();

        $this->assertEquals('processing', wc_get_order($orderId)->get_status());
    }

    public function matchedByProvider(): array
    {
        return [
            'matched by transaction id' => ['transaction id'],
            'matched by Mollie meta' => ['Mollie meta'],
        ];
    }

    /**
     * Same as createMockedWebhookService(), with an HttpResponse spy so the codes set can be asserted.
     *
     * @return \Mockery\MockInterface|MollieOrderService
     */
    private function create_webhook_service_with_response_spy(
        ContainerInterface $container,
        string $paymentId,
        HttpResponse $httpResponse
    ) {
        $webhookService = Mockery::mock(MollieOrderService::class, [
            $httpResponse,
            $container->get(Logger::class),
            $container->get(PaymentFactory::class),
            $container->get('settings.data_helper'),
            $container->get('shared.plugin_id'),
            $container,
            $container->get(WebhookHandler::class)
        ])->makePartial()->shouldAllowMockingProtectedMethods();

        $webhookService->shouldReceive('getPaymentIdFromRequest')
            ->andReturn($paymentId);

        return $webhookService;
    }

    /**
     * Builds a pending order tracked to its latest payment attempt, and mocks an earlier attempt on the
     * same order as expired at Mollie. No order holds the earlier attempt's id any more.
     *
     * @return array{0: int, 1: string, 2: string} order id, order key, stale payment id
     */
    private function makeOrderWithStalePaymentAttempt(): array
    {
        $order = $this->getConfiguredOrder(1, 'mollie_wc_gateway_ideal', ['simple'], [], false, 'tr_latestAttempt949');
        $order->update_meta_data('_mollie_payment_id', 'tr_latestAttempt949');
        $order->save();

        $stalePaymentId = 'tr_staleAttempt949';
        $this->mockSuccessfulPaymentGet($stalePaymentId, 'expired', [
            'metadata' => ['order_id' => $order->get_id()],
            'method' => 'ideal',
            'mode' => 'test',
        ]);

        return [$order->get_id(), $order->get_order_key(), $stalePaymentId];
    }

    /**
     * Builds a real order sitting in a final status (cancelled/refunded) with NO Mollie settled meta,
     * so the only thing that can hold it against a late webhook is isFinalOrderStatus().
     */
    private function makeFinalStatusOrderWithoutSettledMeta(string $status): \WC_Order
    {
        $order = $this->getConfiguredOrder(
            1,
            'mollie_wc_gateway_ideal',
            ['simple'],
            [],
            false
        );

        $order->update_meta_data('_mollie_payment_id', $order->get_transaction_id());
        $order->update_meta_data('_mollie_order_id', $order->get_transaction_id());
        $order->set_status($status);
        $order->save();

        return $order;
    }

    /**
     * Builds a real order for a confirmationDelayed gateway, tracked to a Mollie payment id, sitting
     * UNPAID at on-hold — the exact state banktransfer/directdebit reach at checkout and iDEAL and the
     * other delayed methods reach while awaiting confirmation.
     */
    private function makeUnpaidOnHoldOrder(): \WC_Order
    {
        $order = $this->getConfiguredOrder(
            1,
            'mollie_wc_gateway_ideal',
            ['simple'],
            [],
            false
        );

        // The order is tracked to its Mollie payment/order and is waiting for payment on-hold.
        // Set both meta keys so the expiry handler matches regardless of whether the payment object
        // resolves to a MolliePayment (_mollie_payment_id) or a MollieOrder (_mollie_order_id).
        $order->update_meta_data('_mollie_payment_id', $order->get_transaction_id());
        $order->update_meta_data('_mollie_order_id', $order->get_transaction_id());
        $order->set_status('on-hold');
        $order->save();

        return $order;
    }
}

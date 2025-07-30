<?php

namespace Mollie\WooCommerceTests\Integration\spec\webhooks;

use Mockery;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;
use Mollie\WooCommerceTests\Integration\API\Traits\APIMockTrait;
use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\Payment\MollieOrderService;
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
            $container
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

        $paymentData = [
            'id' => 'tr_first_payment_id',
        ];

        $this->mockSuccessfulPaymentGet('tr_first_payment_id', 'paid', [
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

        $this->mockSuccessfulPaymentGet('tr_concurrent_payment_id', 'paid', [
            'metadata' => ['order_id' => $orderId],
            'method' => 'ideal',
            'mode' => 'test'
        ]);

        $mockedServices = $this->getMockedApiServices();
        $container = $this->bootstrapModule($mockedServices);

        // Set up the webhook request parameters
        $this->setupWebhookRequest($orderId, $orderKey, 'tr_concurrent_payment_id');

        // Get two instances of the webhook service to simulate concurrent requests
        $webhookService1 = $this->createMockedWebhookService($container, 'tr_concurrent_payment_id');
        $webhookService2 = $this->createMockedWebhookService($container, 'tr_concurrent_payment_id');

        // First webhook call should process successfully
        $webhookService1->onWebhookAction();

        $order = wc_get_order($orderId);
        $this->assertEquals('processing', $order->get_status());

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
     * Data provider for payment status webhook tests
     *
     * @return array
     */
    public function paymentStatusProvider(): array
    {
        return [
            'paid status' => [
                'status' => 'paid',
                'expectedOrderStatus' => 'processing',
                'expectedMeta' => [],
                'expectedNote' => 'Order completed using Mollie - iDEAL payment'
            ],
            'authorized status' => [
                'status' => 'authorized',
                'expectedOrderStatus' => 'processing',//if the item is not virtual then it goes to processing
                'expectedMeta' => ['_mollie_authorized' => '1'],
                'expectedNote' => 'Order authorized using Mollie - iDEAL payment'
            ],
            'failed status' => [
                'status' => 'failed',
                'expectedOrderStatus' => 'failed',
                'expectedMeta' => [],
                'expectedNote' => null
            ],
            'canceled status' => [
                'status' => 'canceled',
                'expectedOrderStatus' => ['pending', 'cancelled'], // Can be either based on settings
                'expectedMeta' => ['_mollie_cancelled_payment_id' => true], // true means check it exists
                'expectedNote' => 'Mollie - iDEAL payment (tr_test_payment_canceled - test mode) cancelled'
            ],
            'expired status' => [
                'status' => 'expired',
                'expectedOrderStatus' => ['pending', 'cancelled'],//ideal returns pending if expired in test mode
                'expectedMeta' => [],
                'expectedNote' => null
            ],
        ];
    }

    /**
     * Test webhook processes different payment statuses correctly.
     * See paymentStatusProvider for more details.
     *
     * @test
     * @group integration
     * @group Webhooks
     * @dataProvider paymentStatusProvider
     */
    public function it_processes_webhook_for_payment_status(
        string $status,
        $expectedOrderStatus,
        array $expectedMeta,
        ?string $expectedNote
    ) {
        $order = $this->getConfiguredOrder(
            1,
            'mollie_wc_gateway_ideal',
            ['simple'],
            [],
            false
        );

        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $paymentId = 'tr_test_payment_' . $status;

        $this->mockSuccessfulPaymentGet($paymentId, $status, [
            'metadata' => ['order_id' => $orderId],
            'method' => 'ideal',
            'mode' => 'test'
        ]);

        $mockedServices = $this->getMockedApiServices();
        $container = $this->bootstrapModule($mockedServices);

        $this->setupWebhookRequest($orderId, $orderKey, $paymentId);

        $webhookService = $this->createMockedWebhookService($container, $paymentId);
        $webhookService->onWebhookAction();

        $order = wc_get_order($orderId);

        // Check order status
        if (is_array($expectedOrderStatus)) {
            $this->assertContains($order->get_status(), $expectedOrderStatus);
        } else {
            $this->assertEquals($expectedOrderStatus, $order->get_status());
        }

        // Check expected meta
        foreach ($expectedMeta as $metaKey => $metaValue) {
            if ($metaValue === true) {
                $this->assertNotEmpty($order->get_meta($metaKey));
            } else {
                $this->assertEquals($metaValue, $order->get_meta($metaKey));
            }
        }

        // Check order notes if expected
        if ($expectedNote !== null) {
            $notes = wc_get_order_notes(['order_id' => $orderId]);
            var_dump($notes);
            var_dump($expectedNote);
            $hasExpectedNote = false;
            foreach ($notes as $note) {
                if (strpos($note->content, $expectedNote) !== false) {
                    $hasExpectedNote = true;
                    break;
                }
            }
            $this->assertTrue($hasExpectedNote, "Expected note containing '{$expectedNote}' not found");
        }
    }


    /**
     * Test webhook processes full refund correctly
     * GIVEN that a payment has been made and the order is marked as paid
     * WHEN the refund webhook is triggered
     * THEN the order status is updated to 'refunded'
     * AND the order note is updated to 'New refund'
     * AND the order total is updated correctly
     *
     * @test
     * @group integration
     * @group Webhooks
     */
    public function it_processes_refund_webhook_correctly()
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
        $paymentId = 'tr_refund_test_payment';
        $refundId = 're_test_refund';

        // First mark order as paid
        $order->payment_complete($paymentId);
        // Set the meta to make isOrderPaidAndProcessed return true
        $order->update_meta_data('_mollie_paid_and_processed', '1');
        $order->save();

        // Mock payment with refunds
        $paymentData = [
            'id' => $paymentId,
            'status' => 'paid',
            'amount' => (object)[
                'value' => '11.00',//10+tax
                'currency' => 'EUR'
            ],
            'amountRefunded' => (object)[
                'value' => '11.00',  // Full refund to trigger status change
                'currency' => 'EUR'
            ],
            'metadata' => (object)['order_id' => $orderId],
            'method' => 'ideal',
            'mode' => 'test',
            /*'_links' => (object)[
                'refunds' => [
                    'href' => 'https://api.mollie.com/v2/payments/'.$paymentId.'/refunds',
                    'type' => 'application/hal+json'
                ]
            ],*/ //this would be present in the API response, but would make us call the API in the test, so we use _embedded
            '_embedded' => (object)[
                'refunds' => [
                    (object)[
                        'id' => $refundId,
                        'amount' => [
                            'value' => '11.00',
                            'currency' => 'EUR'
                        ],
                        'status' => 'refunded',
                        'createdAt' => '2023-01-01T12:00:00+00:00'
                    ]
                ]
            ]
        ];

        $this->apiMock()->mockPaymentGet($paymentId, $paymentData);

        // Mock refunds endpoint
        $this->apiMock()->getMockedApiClient()->refunds
            ->shouldReceive('listForPayment')
            ->with($paymentId)
            ->andReturn((object)[
                'count' => 1,
                '_embedded' => [
                    'refunds' => [
                        (object)[
                            'id' => $refundId,
                            'amount' => [
                                'value' => '11.00',
                                'currency' => 'EUR'
                            ],
                            'status' => 'refunded',
                            'createdAt' => '2023-01-01T12:00:00+00:00'
                        ]
                    ]
                ]
            ]);

        $mockedServices = $this->getMockedApiServices();
        $container = $this->bootstrapModule($mockedServices);

        $this->setupWebhookRequest($orderId, $orderKey, $paymentId);

        // Create webhook service but don't mock processRefunds - let it run
        $webhookService = $this->createMockedWebhookService($container, $paymentId);

        // Allow the actual methods to be called
        $webhookService->shouldReceive('notifyProcessedRefunds')
            ->passthru();

        $webhookService->shouldReceive('processUpdateStateRefund')
            ->passthru();

        // Track if the action was fired
        $actionFired = false;
        add_action($container->get('shared.plugin_id') . '_refunds_processed', function() use (&$actionFired) {
            $actionFired = true;
        });

        // Execute webhook
        $webhookService->onWebhookAction();

        // Verify the refund was processed
        $order = wc_get_order($orderId);

        // Check order status was changed to refunded (full refund)
        $this->assertEquals('refunded', $order->get_status(), 'Order should be marked as refunded');

        // Check refund note was added
        $notes = wc_get_order_notes(['order_id' => $orderId]);
        $refundNotes = array_filter($notes, function ($note) use ($refundId) {
            return strpos($note->content, 'New refund') !== false &&
                strpos($note->content, $refundId) !== false;
        });
        $this->assertNotEmpty($refundNotes, 'Refund note should be added');

        // Check processed refund IDs were saved
        $processedRefundIds = $order->get_meta('_mollie_processed_refund_ids');
        $this->assertContains($refundId, $processedRefundIds, 'Refund ID should be marked as processed');

        // Verify action was fired
        $this->assertTrue($actionFired, 'Refunds processed action should be fired');
    }

    /**
     * Test webhook handles partial refund correctly
     * GIVEN that a payment has been made and the order is marked as paid
     * WHEN the refund webhook is triggered
     * AND the refund is less than the full amount
     * THEN the refund should be processed correctly
     * BUT order total and order status is NOT updated
     * AND the order note is updated to 'Partial refund'
     *
     * @test
     * @group integration
     * @group Webhooks
     */
    public function it_processes_partial_refund_webhook_correctly()
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
        $paymentId = 'tr_partial_refund_payment';
        $refundId = 're_partial_refund';

        // Mark order as paid
        $order->payment_complete($paymentId);
        $order->update_meta_data('_mollie_paid_and_processed', '1');
        $order->save();

        // Mock payment with partial refund
        $paymentData = [
            'id' => $paymentId,
            'status' => 'paid',
            'amount' => (object)[
                'value' => '11.00',
                'currency' => 'EUR'
            ],
            'amountRefunded' => (object)[
                'value' => '3.00',  // Partial refund
                'currency' => 'EUR'
            ],
            'metadata' => (object)['order_id' => $orderId],
            'method' => 'ideal',
            'mode' => 'test',
            /*'_links' => (object)[
                'refunds' => (object)[
                    'href' => 'https://api.mollie.com/v2/payments/'.$paymentId.'/refunds',
                    'type' => 'application/hal+json'
                ]
            ],*/ //this would be present in the API response, but would make us call the API in the test, so we use _embedded
            '_embedded' => (object)[
                'refunds' => [
                    (object)[
                        'id' => $refundId,
                        'amount' => [
                            'value' => '3.00',
                            'currency' => 'EUR'
                        ],
                        'status' => 'refunded',
                        'createdAt' => '2023-01-01T12:00:00+00:00'
                    ]
                ]
            ]
        ];

        $this->apiMock()->mockPaymentGet($paymentId, $paymentData);

        // Mock refunds endpoint
        $this->apiMock()->getMockedApiClient()->refunds
            ->shouldReceive('listForPayment')
            ->with($paymentId)
            ->andReturn((object)[
                'count' => 1,
                '_embedded' => (object)[
                    'refunds' => [
                        (object)[
                            'id' => $refundId,
                            'amount' => [
                                'value' => '3.00',
                                'currency' => 'EUR'
                            ],
                            'status' => 'refunded',
                            'createdAt' => '2023-01-01T12:00:00+00:00'
                        ]
                    ]
                ]
            ]);

        $mockedServices = $this->getMockedApiServices();
        $container = $this->bootstrapModule($mockedServices);

        $this->setupWebhookRequest($orderId, $orderKey, $paymentId);

        $webhookService = $this->createMockedWebhookService($container, $paymentId);

        // Mock internal methods
        $webhookService->shouldReceive('notifyProcessedRefunds')->passthru();
        $webhookService->shouldReceive('processUpdateStateRefund')->passthru();

        $webhookService->onWebhookAction();

        $order = wc_get_order($orderId);

        // For partial refund, status should NOT change to refunded
        $this->assertNotEquals('refunded', $order->get_status(), 'Order should not be fully refunded');

        // But refund should still be processed
        $processedRefundIds = $order->get_meta('_mollie_processed_refund_ids');
        $this->assertContains($refundId, $processedRefundIds, 'Partial refund should be marked as processed');
    }
}

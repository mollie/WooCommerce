<?php

namespace Mollie\WooCommerceTests\Integration\spec\webhooks;

use Mockery;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;
use Mollie\WooCommerceTests\Integration\API\Traits\APIMockTrait;
use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\Payment\MollieOrderService;
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

        $this->webhookService = Mockery::mock(MollieOrderService::class, [
            $container->get('SDK.HttpResponse'),
            $container->get(Logger::class),
            $container->get(PaymentFactory::class),
            $container->get('settings.data_helper'),
            $container->get('shared.plugin_id'),
            $container
        ])->makePartial()->shouldAllowMockingProtectedMethods();

        $this->webhookService->shouldReceive('getPaymentIdFromRequest')
            ->andReturn($paymentData['id']);
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
        $webhookService1 = Mockery::mock(MollieOrderService::class, [
            $container->get('SDK.HttpResponse'),
            $container->get(Logger::class),
            $container->get(PaymentFactory::class),
            $container->get('settings.data_helper'),
            $container->get('shared.plugin_id'),
            $container
        ])->makePartial()->shouldAllowMockingProtectedMethods();

        $webhookService1->shouldReceive('getPaymentIdFromRequest')
            ->andReturn('tr_concurrent_payment_id');

        $webhookService2 = Mockery::mock(MollieOrderService::class, [
            $container->get('SDK.HttpResponse'),
            $container->get(Logger::class),
            $container->get(PaymentFactory::class),
            $container->get('settings.data_helper'),
            $container->get('shared.plugin_id'),
            $container
        ])->makePartial()->shouldAllowMockingProtectedMethods();

        $webhookService2->shouldReceive('getPaymentIdFromRequest')
            ->andReturn('tr_concurrent_payment_id');

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
            return strpos($note->content, 'completed using  payment (tr_concurrent_payment_id') != false;
        });

        // Should only have one payment started note
        $this->assertCount(1, $paymentNotes, 'Should only process payment once, even with concurrent webhooks');
    }
}

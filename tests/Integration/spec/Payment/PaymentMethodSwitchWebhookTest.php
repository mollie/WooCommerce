<?php

namespace Mollie\WooCommerceTests\Integration\spec\Payment;

use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;
use Mollie\WooCommerceTests\Integration\API\Traits\APIMockTrait;

/**
 * Guards the "payment method switch" regression: after a customer abandons a first,
 * non-cancellable payment and pays for the order with a different method, the webhook
 * for the abandoned attempt (going into e.g. expired state) must not terminate the order
 * that was already paid for by the other method.
 *
 * @group integration
 * @group Webhooks
 */
class PaymentMethodSwitchWebhookTest extends IntegrationMockedTestCase
{
    use APIMockTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->initializeApiMock();
    }

    /**
     * @test
     * @scenario A superseded attempt's failure does not lose the order
     *   Given a pending order linked to the new attempt (tr_NEW) the customer is completing
     *   When a webhook arrives for the earlier, superseded payment (tr_OLD) in a failed state
     *   Then the order stays pending and linked to tr_NEW, not transitioned to failed/cancelled
     */
    public function it_ignores_stale_payment_webhook_and_preserves_order_linked_to_new_attempt()
    {
        // Arrange — order linked to the new attempt (tr_NEW), still pending
        $newPaymentId = 'tr_' . uniqid();
        $stalePaymentId = 'tr_' . uniqid();

        $order = $this->getConfiguredOrder(
            1,
            'mollie_wc_gateway_ideal',
            ['simple'],
            [],
            false,
            $newPaymentId
        );
        $order->update_meta_data('_mollie_payment_id', $newPaymentId);
        $order->update_meta_data('_mollie_payment_method', 'mollie_wc_gateway_ideal');
        $order->update_status('pending');
        $order->save();

        $orderId = $order->get_id();

        // The abandoned first attempt reaches a terminal state (failed).
        $this->mockSuccessfulPaymentGet($stalePaymentId, 'failed', [
            'metadata' => ['order_id' => $orderId],
            'method' => 'ideal',
            'mode' => 'test',
        ]);

        $mockedServices = $this->getMockedApiServices();
        $container = $this->bootstrapModule($mockedServices);

        /** @var MollieOrderService $webhookService */
        $webhookService = $container->get(MollieOrderService::class);

        // When — the stale, superseded payment's expiry webhook is processed for this order
        $webhookService->doPaymentForOrder($order, $stalePaymentId);

        // Then
        $order = wc_get_order($orderId);

        // crit 4 — the stale attempt's failure must not fail the order the customer is still paying
        $this->assertNotContains($order->get_status(), ['cancelled', 'failed']);
        $this->assertEquals('pending', $order->get_status());
        // crit 5 — the order stays linked only to the new attempt
        $this->assertEquals($newPaymentId, $order->get_transaction_id());
        $this->assertEquals($newPaymentId, $order->get_meta('_mollie_payment_id'));
    }
}
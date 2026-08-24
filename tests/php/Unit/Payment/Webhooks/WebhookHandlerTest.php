<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment\Webhooks;

use Inpsyde\PaymentGateway\PaymentGateway;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\WooCommerce\Payment\MollieOrder;
use Mollie\WooCommerce\Payment\MolliePayment;
use Mollie\WooCommerce\Payment\Webhooks\WebhookHandler;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerceTests\TestCase;
use Psr\Log\LoggerInterface;
use WC_Order;

use function Brain\Monkey\Filters\expectAdded;
use function Brain\Monkey\Functions\when;

/**
 * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler
 */
class WebhookHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    /** @var LoggerInterface&\Mockery\MockInterface */
    private $logger;

    /** @var Settings&\Mockery\MockInterface */
    private $settings;

    /** @var Data&\Mockery\MockInterface */
    private $dataHelper;

    private WebhookHandler $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger     = Mockery::mock(LoggerInterface::class);
        $this->settings   = Mockery::mock(Settings::class);
        $this->dataHelper = Mockery::mock(Data::class);

        $this->logger->shouldReceive('debug')->zeroOrMoreTimes();

        $this->sut = new WebhookHandler(
            $this->logger,
            $this->settings,
            'mollie-payments-for-woocommerce',
            $this->dataHelper
        );
    }

    private function makePayment(string $id = 'tr_TEST123'): \stdClass
    {
        $payment         = new \stdClass();
        $payment->id     = $id;
        $payment->mode   = 'test';
        $payment->method = 'bancontact';
        $payment->status = 'canceled';

        return $payment;
    }

    /**
     * @return \Mockery\MockInterface
     */
    private function makeOrderApiPayment(string $id, bool $isAuthorized, bool $isCompleted)
    {
        $payment = Mockery::mock();
        $payment->shouldReceive('isAuthorized')->andReturn($isAuthorized);
        $payment->shouldReceive('isCompleted')->andReturn($isCompleted);
        $payment->id     = $id;
        $payment->mode   = 'test';
        $payment->method = 'klarnapaylater';

        return $payment;
    }

    /**
     * @scenario Given an order where '_mollie_cancelled_payment_id' already equals $payment->id,
     *           a second call to onWebhookCanceled() adds no new WC order note and does not call
     *           setCancelledMolliePaymentId() or updateOrderStatus() again.
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookCanceled
     */
    public function test_on_webhook_canceled_skips_all_processing_when_payment_already_tracked(): void
    {
        // Arrange
        $paymentId    = 'tr_ALREADY_TRACKED';
        $orderId      = 42;
        $order        = Mockery::mock(WC_Order::class);
        $mollieObject = Mockery::mock(MolliePayment::class);
        $payment      = $this->makePayment($paymentId);

        $order->shouldReceive('get_id')->andReturn($orderId);
        $mollieObject->shouldReceive('isFinalOrderStatus')->with($order)->andReturn(false);
        $mollieObject->shouldReceive('getCancelledMolliePaymentId')->with($orderId)->andReturn($paymentId);

        $order->shouldNotReceive('add_order_note');
        $mollieObject->shouldNotReceive('unsetActiveMolliePayment');
        $mollieObject->shouldNotReceive('setCancelledMolliePaymentId');
        $mollieObject->shouldNotReceive('updateOrderStatus');

        // When
        $this->sut->onWebhookCanceled($order, $payment, 'Bancontact', $mollieObject);

        // Then — Mockery verifies shouldNotReceive expectations on tearDown
    }

    /**
     * @scenario Given an order with no '_mollie_cancelled_payment_id' meta set, the first call
     *           to onWebhookCanceled() adds the cancellation note and writes
     *           '_mollie_cancelled_payment_id' = $payment->id.
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookCanceled
     */
    public function test_on_webhook_canceled_processes_fully_on_first_cancellation(): void
    {
        // Arrange
        $paymentId    = 'tr_FIRST';
        $orderId      = 7;
        $order        = Mockery::mock(WC_Order::class);
        $mollieObject = Mockery::mock(MolliePayment::class);
        $gateway      = Mockery::mock(PaymentGateway::class);
        $gateway->id  = 'mollie_wc_gateway_bancontact';
        $payment      = $this->makePayment($paymentId);

        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_status')->andReturn('pending');
        $order->shouldReceive('get_payment_method')->andReturn('mollie_wc_gateway_bancontact');

        $mollieObject->shouldReceive('isFinalOrderStatus')->with($order)->andReturn(false);
        $mollieObject->shouldReceive('getCancelledMolliePaymentId')->with($orderId)->andReturn('');
        $mollieObject->shouldReceive('unsetActiveMolliePayment')->once()->with($orderId, $paymentId);
        $mollieObject->shouldReceive('setCancelledMolliePaymentId')->once()->with($orderId, $paymentId);
        $mollieObject->shouldReceive('updateOrderStatus')->once();
        $mollieObject->shouldReceive('deleteSubscriptionFromPending')->once()->with($order);

        $this->settings->shouldReceive('getOrderStatusCancelledPayments')->andReturn('pending');

        when('wc_get_payment_gateway_by_order')->justReturn($gateway);
        when('apply_filters')->returnArg(2);

        // When
        $order->shouldReceive('add_order_note')->once();

        $this->sut->onWebhookCanceled($order, $payment, 'Bancontact', $mollieObject);

        // Then — Mockery verifies call-count expectations on tearDown
    }

    /**
     * @scenario Given an order where '_mollie_cancelled_payment_id' holds a different (older)
     *           payment ID, calling onWebhookCanceled() with a new payment ID still processes
     *           fully and overwrites '_mollie_cancelled_payment_id'.
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookCanceled
     */
    public function test_on_webhook_canceled_processes_new_payment_when_existing_cancelled_id_differs(): void
    {
        // Arrange
        $newPaymentId = 'tr_NEW';
        $orderId      = 99;
        $order        = Mockery::mock(WC_Order::class);
        $mollieObject = Mockery::mock(MolliePayment::class);
        $gateway      = Mockery::mock(PaymentGateway::class);
        $gateway->id  = 'mollie_wc_gateway_bancontact';
        $payment      = $this->makePayment($newPaymentId);

        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_status')->andReturn('pending');
        $order->shouldReceive('get_payment_method')->andReturn('mollie_wc_gateway_bancontact');

        $mollieObject->shouldReceive('isFinalOrderStatus')->with($order)->andReturn(false);
        $mollieObject->shouldReceive('getCancelledMolliePaymentId')->with($orderId)->andReturn('tr_OLD');
        $mollieObject->shouldReceive('unsetActiveMolliePayment')->once()->with($orderId, $newPaymentId);
        $mollieObject->shouldReceive('setCancelledMolliePaymentId')->once()->with($orderId, $newPaymentId);
        $mollieObject->shouldReceive('updateOrderStatus')->once();
        $mollieObject->shouldReceive('deleteSubscriptionFromPending')->once()->with($order);

        $this->settings->shouldReceive('getOrderStatusCancelledPayments')->andReturn('pending');

        when('wc_get_payment_gateway_by_order')->justReturn($gateway);
        when('apply_filters')->returnArg(2);

        // When
        $order->shouldReceive('add_order_note')->once();

        $this->sut->onWebhookCanceled($order, $payment, 'Bancontact', $mollieObject);

        // Then — Mockery verifies call-count expectations on tearDown
    }

    /**
     * @scenario For an Orders API payment method (e.g. Klarna) that is not yet authorized,
     *           onWebhookAuthorized() calls $order->payment_complete() exactly once - the
     *           single remaining site where payment_complete() fires for such orders.
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookAuthorized
     */
    public function test_on_webhook_authorized_calls_payment_complete_once(): void
    {
        // Arrange
        $paymentId    = 'ord_AUTH1';
        $orderId      = 15;
        $order        = Mockery::mock(WC_Order::class);
        $mollieObject = Mockery::mock(MollieOrder::class);
        $payment      = $this->makeOrderApiPayment($paymentId, true, false);

        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_meta')->with('_mollie_authorized')->andReturn('');
        $order->shouldReceive('add_order_note')->once();
        $order->shouldReceive('update_meta_data')->once()->with('_mollie_authorized', '1');

        $mollieObject->shouldReceive('setOrderPaidAndProcessed')->once()->with($order);
        $mollieObject->shouldReceive('unsetCancelledMolliePaymentId')->once()->with($orderId);
        $mollieObject->shouldReceive('deleteSubscriptionFromPending')->once()->with($order);

        // When
        $order->shouldReceive('payment_complete')->once()->with($paymentId);

        $this->sut->onWebhookAuthorized($order, $payment, 'Klarna', $mollieObject);

        // Then — Mockery verifies call-count expectations on tearDown
    }

    /**
     * @scenario For an Orders API payment method that was already authorized (and thus already
     *           had payment_complete() called once), onWebhookCompleted() no longer calls
     *           $order->payment_complete() a second time and no longer registers the
     *           woocommerce_valid_order_statuses_for_payment_complete or
     *           woocommerce_payment_complete_order_status filters used to force it through.
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookCompleted
     */
    public function test_on_webhook_completed_does_not_call_payment_complete_or_register_filters(): void
    {
        // Arrange
        $paymentId    = 'ord_COMPLETE1';
        $orderId      = 22;
        $order        = Mockery::mock(WC_Order::class);
        $mollieObject = Mockery::mock(MollieOrder::class);
        $payment      = $this->makeOrderApiPayment($paymentId, false, true);

        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_status')->andReturn('processing');
        $order->shouldReceive('update_status')->zeroOrMoreTimes();
        $order->shouldReceive('add_order_note')->zeroOrMoreTimes();

        $mollieObject->shouldReceive('setOrderPaidAndProcessed')->zeroOrMoreTimes();
        $mollieObject->shouldReceive('unsetCancelledMolliePaymentId')->zeroOrMoreTimes();
        $mollieObject->shouldReceive('deleteSubscriptionFromPending')->zeroOrMoreTimes();

        expectAdded('woocommerce_valid_order_statuses_for_payment_complete')->never();
        expectAdded('woocommerce_payment_complete_order_status')->never();

        // When
        $order->shouldNotReceive('payment_complete');

        $this->sut->onWebhookCompleted($order, $payment, 'Klarna', $mollieObject);

        // Then — Mockery verifies shouldNotReceive/expectAdded expectations on tearDown
    }

    /**
     * @scenario When onWebhookCompleted() runs and the order's current status is 'processing',
     *           the order transitions to 'completed' via $order->update_status().
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookCompleted
     */
    public function test_on_webhook_completed_transitions_processing_order_to_completed(): void
    {
        // Arrange
        $paymentId    = 'ord_COMPLETE2';
        $orderId      = 23;
        $order        = Mockery::mock(WC_Order::class);
        $mollieObject = Mockery::mock(MollieOrder::class);
        $payment      = $this->makeOrderApiPayment($paymentId, false, true);

        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_status')->andReturn('processing');
        $order->shouldReceive('add_order_note')->zeroOrMoreTimes();
        $order->shouldReceive('payment_complete')->zeroOrMoreTimes();

        $mollieObject->shouldReceive('setOrderPaidAndProcessed')->zeroOrMoreTimes();
        $mollieObject->shouldReceive('unsetCancelledMolliePaymentId')->zeroOrMoreTimes();
        $mollieObject->shouldReceive('deleteSubscriptionFromPending')->zeroOrMoreTimes();

        // When
        $order->shouldReceive('update_status')->once()->with('completed', Mockery::type('string'));

        $this->sut->onWebhookCompleted($order, $payment, 'Klarna', $mollieObject);

        // Then — Mockery verifies call-count expectations on tearDown
    }

    /**
     * @scenario The order note documenting Mollie's 'completed' status is still added by
     *           onWebhookCompleted(), regardless of the mechanism used to change status.
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookCompleted
     */
    public function test_on_webhook_completed_adds_order_note_after_status_change(): void
    {
        // Arrange
        $paymentId    = 'ord_COMPLETE3';
        $orderId      = 24;
        $order        = Mockery::mock(WC_Order::class);
        $mollieObject = Mockery::mock(MollieOrder::class);
        $payment      = $this->makeOrderApiPayment($paymentId, false, true);

        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_status')->andReturn('processing');
        $order->shouldReceive('update_status')->zeroOrMoreTimes();
        $order->shouldReceive('payment_complete')->zeroOrMoreTimes();

        $mollieObject->shouldReceive('setOrderPaidAndProcessed')->zeroOrMoreTimes();
        $mollieObject->shouldReceive('unsetCancelledMolliePaymentId')->zeroOrMoreTimes();
        $mollieObject->shouldReceive('deleteSubscriptionFromPending')->zeroOrMoreTimes();

        // When
        $order->shouldReceive('add_order_note')
            ->once()
            ->with(Mockery::pattern('/Order completed at Mollie/'));

        $this->sut->onWebhookCompleted($order, $payment, 'Klarna', $mollieObject);

        // Then — Mockery verifies call-count expectations on tearDown
    }

    /**
     * @scenario mollieObject->setOrderPaidAndProcessed(), unsetCancelledMolliePaymentId(), and
     *           deleteSubscriptionFromPending() still run after onWebhookCompleted() changes the
     *           order status, unchanged from current behavior.
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookCompleted
     */
    public function test_on_webhook_completed_runs_mollie_object_bookkeeping_after_status_change(): void
    {
        // Arrange
        $paymentId    = 'ord_COMPLETE4';
        $orderId      = 25;
        $order        = Mockery::mock(WC_Order::class);
        $mollieObject = Mockery::mock(MollieOrder::class);
        $payment      = $this->makeOrderApiPayment($paymentId, false, true);

        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_status')->andReturn('processing');
        $order->shouldReceive('update_status')->zeroOrMoreTimes();
        $order->shouldReceive('payment_complete')->zeroOrMoreTimes();
        $order->shouldReceive('add_order_note')->zeroOrMoreTimes();

        // When
        $mollieObject->shouldReceive('setOrderPaidAndProcessed')->once()->with($order);
        $mollieObject->shouldReceive('unsetCancelledMolliePaymentId')->once()->with($orderId);
        $mollieObject->shouldReceive('deleteSubscriptionFromPending')->once()->with($order);

        $this->sut->onWebhookCompleted($order, $payment, 'Klarna', $mollieObject);

        // Then — Mockery verifies call-count expectations on tearDown
    }
}

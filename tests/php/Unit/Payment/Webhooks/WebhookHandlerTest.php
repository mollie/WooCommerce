<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment\Webhooks;

use Inpsyde\PaymentGateway\PaymentGateway;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\WooCommerce\Payment\MolliePayment;
use Mollie\WooCommerce\Payment\Webhooks\WebhookHandler;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerceTests\TestCase;
use Psr\Log\LoggerInterface;
use WC_Order;

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
}

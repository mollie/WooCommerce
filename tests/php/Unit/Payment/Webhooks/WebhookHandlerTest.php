<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment\Webhooks;

use Inpsyde\PaymentGateway\PaymentGateway;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\WooCommerce\Payment\MollieOrder;
use Mollie\Api\Resources\Payment;
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
     * Scenario: A canceled webhook for an already-tracked payment is ignored
     *   Given an order whose '_mollie_cancelled_payment_id' already equals the payment id
     *   When onWebhookCanceled() is called again for that same payment
     *   Then no order note is added and neither setCancelledMolliePaymentId() nor updateOrderStatus() runs
     *
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
        $order->shouldReceive('get_status')->andReturn('pending');
        $order->shouldReceive('needs_payment')->andReturn(true);
        $order->shouldReceive('get_meta')->andReturn('');
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
     * Scenario: The first cancellation of a pending order is processed fully
     *   Given a non-settled pending order with no '_mollie_cancelled_payment_id' meta set
     *   When onWebhookCanceled() is called
     *   Then the cancellation note is added and '_mollie_cancelled_payment_id' is written with the payment id
     *
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
        $order->shouldReceive('needs_payment')->andReturn(true);
        $order->shouldReceive('get_meta')->andReturn('');
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
     * Scenario: A newer canceled payment overwrites an older tracked cancellation
     *   Given a pending order whose '_mollie_cancelled_payment_id' holds a different (older) payment id
     *   When onWebhookCanceled() is called with a new payment id
     *   Then the cancellation is processed fully and '_mollie_cancelled_payment_id' is overwritten
     *
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
        $order->shouldReceive('needs_payment')->andReturn(true);
        $order->shouldReceive('get_meta')->andReturn('');
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

    /**
     * A paid Mollie payment double for the Payments API (exposes isPaid() plus the public
     * amount properties onWebhookPaid inspects).
     *
     * @param object|null $amountRefunded    Non-null with a value ⇒ the order was refunded.
     * @param object|null $amountChargedBack  Non-null ⇒ the order was charged back.
     */
    private function makePaidPayment($amountRefunded, $amountChargedBack = null): \Mockery\MockInterface
    {
        $payment = Mockery::mock(Payment::class);
        $payment->shouldReceive('isPaid')->andReturn(true);
        $payment->id              = 'tr_PAID123';
        $payment->mode            = 'test';
        $payment->method          = 'klarna';
        $payment->amountChargedBack = $amountChargedBack;
        $payment->amountRefunded    = $amountRefunded;

        return $payment;
    }

    /**
     * An order that is settled by every reasonable signal (processing status, authorized,
     * paid-and-processed, no longer needs payment) — yet NOT in a WC "final" status, so the
     * current handlers do not already bail on isFinalOrderStatus().
     */
    private function makeSettledOrder(int $orderId): \Mockery\MockInterface
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_status')->andReturn('processing');
        $order->shouldReceive('needs_payment')->andReturn(false);
        $order->shouldReceive('get_payment_method')->andReturn('mollie_wc_gateway_klarna');
        // Any settled meta read (_mollie_authorized / _mollie_paid_and_processed / ids) is truthy.
        $order->shouldReceive('get_meta')->andReturn('1');
        $order->shouldReceive('add_order_note')->zeroOrMoreTimes();

        return $order;
    }

    /**
     * Scenario: A paid webhook for an already-refunded order does not reprocess the payment
     *   Given a paid Mollie payment that reports a non-zero amountRefunded
     *   When onWebhookPaid() is handled
     *   Then WC_Order::payment_complete() is not called and the order is not re-flagged as paid-and-processed
     *
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookPaid
     */
    public function test_on_webhook_paid_skips_payment_complete_when_payment_is_refunded(): void
    {
        // Arrange
        $orderId      = 501;
        $order        = Mockery::mock(WC_Order::class);
        $mollieObject = Mockery::mock(MolliePayment::class);
        $payment      = $this->makePaidPayment((object) ['value' => '10.00']);

        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_status')->andReturn('refunded');
        $order->shouldReceive('get_meta')->andReturn('1');
        $order->shouldReceive('needs_payment')->andReturn(false);
        $order->shouldReceive('add_order_note')->zeroOrMoreTimes();

        // When / Then — the refunded order must not be reprocessed as a fresh payment.
        $order->shouldReceive('payment_complete')->never();
        $mollieObject->shouldReceive('setOrderPaidAndProcessed')->never();
        $mollieObject->shouldReceive('unsetCancelledMolliePaymentId')->never();
        $mollieObject->shouldReceive('addMandateIdMetaToFirstPaymentSubscriptionOrder')->never();

        $this->sut->onWebhookPaid($order, $payment, 'Klarna', $mollieObject);
    }

    /**
     * Scenario: A clean paid webhook still completes the order
     *   Given a paid Mollie payment with no refund and no chargeback
     *   When onWebhookPaid() is handled
     *   Then WC_Order::payment_complete() is called once and the order is marked paid-and-processed
     *
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookPaid
     */
    public function test_on_webhook_paid_completes_when_not_refunded_or_charged_back(): void
    {
        // Arrange
        $orderId      = 502;
        $order        = Mockery::mock(WC_Order::class);
        $mollieObject = Mockery::mock(MolliePayment::class);
        $payment      = $this->makePaidPayment(null, null);

        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_status')->andReturn('pending');
        $order->shouldReceive('get_meta')->andReturn('');
        $order->shouldReceive('needs_payment')->andReturn(true);

        // When / Then — a genuine paid webhook completes the order exactly once.
        $order->shouldReceive('payment_complete')->once()->with($payment->id);
        $order->shouldReceive('add_order_note')->once();
        $mollieObject->shouldReceive('setOrderPaidAndProcessed')->once()->with($order);
        $mollieObject->shouldReceive('unsetCancelledMolliePaymentId')->once()->with($orderId);
        $mollieObject->shouldReceive('addMandateIdMetaToFirstPaymentSubscriptionOrder')->once();

        $this->sut->onWebhookPaid($order, $payment, 'Klarna', $mollieObject);
    }

    /**
     * Scenario: A stray canceled webhook does not cancel an already-settled order (PIWOO-927)
     *   Given an order already settled (processing, authorized, paid-and-processed) but not in a WC final status
     *   When a canceled-payment webhook is handled
     *   Then the order status is not changed and the cancelled Mollie payment id is not recorded
     *
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookCanceled
     */
    public function test_on_webhook_canceled_skips_when_order_already_settled(): void
    {
        // Arrange
        $orderId      = 601;
        $order        = $this->makeSettledOrder($orderId);
        $mollieObject = Mockery::mock(MolliePayment::class);
        $gateway      = Mockery::mock(PaymentGateway::class);
        $gateway->id  = 'mollie_wc_gateway_klarna';
        $payment      = $this->makePayment('tr_STRAY_CANCEL');

        // processing is not a final status: the current handler does NOT already bail here.
        $mollieObject->shouldReceive('isFinalOrderStatus')->with($order)->andReturn(false);
        $mollieObject->shouldReceive('getCancelledMolliePaymentId')->andReturn('');
        $mollieObject->shouldReceive('deleteSubscriptionFromPending')->zeroOrMoreTimes();

        $this->settings->shouldReceive('getOrderStatusCancelledPayments')->andReturn('pending');
        when('wc_get_payment_gateway_by_order')->justReturn($gateway);
        when('apply_filters')->returnArg(2);

        // When / Then — none of the cancellation side effects may run for a settled order.
        $mollieObject->shouldReceive('unsetActiveMolliePayment')->never();
        $mollieObject->shouldReceive('setCancelledMolliePaymentId')->never();
        $mollieObject->shouldReceive('updateOrderStatus')->never();

        $this->sut->onWebhookCanceled($order, $payment, 'Klarna', $mollieObject);
    }

    /**
     * Scenario: The settled guard does not suppress a legitimate cancellation
     *   Given a genuinely non-settled pending order with no authorized/paid meta
     *   When a canceled-payment webhook is handled
     *   Then the cancellation is processed fully and the order status is updated
     *
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookCanceled
     */
    public function test_on_webhook_canceled_still_cancels_non_settled_order(): void
    {
        // Arrange
        $orderId      = 602;
        $order        = Mockery::mock(WC_Order::class);
        $mollieObject = Mockery::mock(MolliePayment::class);
        $gateway      = Mockery::mock(PaymentGateway::class);
        $gateway->id  = 'mollie_wc_gateway_klarna';
        $payment      = $this->makePayment('tr_REAL_CANCEL');

        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_status')->andReturn('pending');
        $order->shouldReceive('needs_payment')->andReturn(true);
        $order->shouldReceive('get_payment_method')->andReturn('mollie_wc_gateway_klarna');
        $order->shouldReceive('get_meta')->andReturn(''); // no settled meta

        $mollieObject->shouldReceive('isFinalOrderStatus')->with($order)->andReturn(false);
        $mollieObject->shouldReceive('getCancelledMolliePaymentId')->andReturn('');
        $mollieObject->shouldReceive('deleteSubscriptionFromPending')->once()->with($order);

        $this->settings->shouldReceive('getOrderStatusCancelledPayments')->andReturn('pending');
        when('wc_get_payment_gateway_by_order')->justReturn($gateway);
        when('apply_filters')->returnArg(2);

        // When / Then — a real cancellation is processed exactly once.
        $order->shouldReceive('add_order_note')->once();
        $mollieObject->shouldReceive('unsetActiveMolliePayment')->once()->with($orderId, 'tr_REAL_CANCEL');
        $mollieObject->shouldReceive('setCancelledMolliePaymentId')->once()->with($orderId, 'tr_REAL_CANCEL');
        $mollieObject->shouldReceive('updateOrderStatus')->once();

        $this->sut->onWebhookCanceled($order, $payment, 'Klarna', $mollieObject);
    }

    /**
     * Scenario: A late failed webhook does not fail an already-settled order
     *   Given an order already settled (paid, processing, or authorized)
     *   When a late failed-payment webhook for an earlier attempt is handled
     *   Then the failure processing does not run and the order is not moved to failed
     *
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookFailed
     */
    public function test_on_webhook_failed_skips_when_order_already_settled(): void
    {
        // Arrange
        $orderId      = 701;
        $order        = $this->makeSettledOrder($orderId);
        $mollieObject = Mockery::mock(MolliePayment::class);
        $gateway      = Mockery::mock(PaymentGateway::class);
        $gateway->id  = 'mollie_wc_gateway_klarna';
        $payment      = $this->makePayment('tr_LATE_FAIL');
        $payment->details = new \stdClass();

        $mollieObject->shouldReceive('isFinalOrderStatus')->with($order)->andReturn(false);

        when('wc_get_payment_gateway_by_order')->justReturn($gateway);
        when('apply_filters')->returnArg(2);

        // When / Then — the failure pipeline must not run for a settled order.
        $mollieObject->shouldReceive('failedSubscriptionProcess')->never();

        $this->sut->onWebhookFailed($order, $payment, 'Klarna', $mollieObject);
    }

    /**
     * Scenario: Expired handling still bails for an already-paid order after the shared-guard refactor
     *   Given an order that is already paid, and the "already settled" check now lives in a shared guard
     *   When an expired-payment webhook is handled
     *   Then the order status is not changed (the 8.1.7 behaviour is preserved)
     *
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookExpired
     */
    public function test_on_webhook_expired_still_skips_when_order_already_paid(): void
    {
        // Arrange
        $orderId      = 801;
        $order        = $this->makeSettledOrder($orderId);
        $mollieObject = Mockery::mock(MolliePayment::class);
        $payment      = $this->makePayment('tr_LATE_EXPIRE');

        $this->logger->shouldReceive('log')->zeroOrMoreTimes();
        $mollieObject->shouldReceive('isFinalOrderStatus')->with($order)->andReturn(false);

        // When / Then — an already-paid order is never expired/cancelled.
        $mollieObject->shouldReceive('updateOrderStatus')->never();
        $mollieObject->shouldReceive('unsetCancelledMolliePaymentId')->never();

        $this->sut->onWebhookExpired($order, $payment, 'Klarna', $mollieObject);
    }
}

<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment\Webhooks;

use Inpsyde\PaymentGateway\PaymentGateway;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\Api\Resources\Payment;
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
        $order->shouldReceive('get_payment_method')->andReturn('mollie_wc_gateway_klarna');
        // Any settled meta read (_mollie_authorized / _mollie_paid_and_processed / ids) is truthy.
        $order->shouldReceive('get_meta')->andReturn('1');
        $order->shouldReceive('add_order_note')->zeroOrMoreTimes();

        return $order;
    }

    /**
     * An order that is genuinely UNPAID but sitting at on-hold — the state banktransfer and directdebit
     * reach at checkout, and every other confirmationDelayed gateway (iDEAL, Bancontact, eps, kbc,
     * belfius, trustly, multibanco, billink, wero, paybybank) reaches through the Initial order status
     * setting. It carries no paid/authorized meta, so orderIsAlreadySettled() must return false for it
     * and a late expired/canceled/failed webhook still transitions it instead of stranding it on-hold
     * forever.
     *
     * The mock deliberately does NOT stub needs_payment()/has_status(): the guard must not consult them
     * (WooCommerce reports on-hold as "does not need payment"), so a call would fail this mock loudly.
     */
    private function makeOnHoldUnpaidOrder(int $orderId): \Mockery\MockInterface
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_status')->andReturn('on-hold');
        $order->shouldReceive('get_payment_method')->andReturn('mollie_wc_gateway_banktransfer');
        $order->shouldReceive('add_order_note')->zeroOrMoreTimes();
        // Not settled: no paid-and-processed and no authorized meta.
        $order->shouldReceive('get_meta')->with('_mollie_paid_and_processed', true)->andReturn('');
        $order->shouldReceive('get_meta')->with('_mollie_authorized')->andReturn('');

        return $order;
    }

    /**
     * An order in a final status (cancelled/refunded) that carries NO Mollie settled meta, so the only
     * thing that can hold it is MollieObject::isFinalOrderStatus(). The metas are stubbed as empty on
     * purpose: if a handler falls through to orderIsAlreadySettled() the guard returns false and the
     * order is mutated, which is exactly the regression these tests lock. The order tracks a Mollie
     * payment id so the handler's stale-payment check is not what makes it bail.
     */
    private function makeFinalStatusOrder(int $orderId, string $status): \Mockery\MockInterface
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_status')->andReturn($status);
        $order->shouldReceive('get_payment_method')->andReturn('mollie_wc_gateway_klarna');
        $order->shouldReceive('add_order_note')->zeroOrMoreTimes();
        $order->shouldReceive('get_meta')->with('_mollie_paid_and_processed', true)->andReturn('');
        $order->shouldReceive('get_meta')->with('_mollie_authorized')->andReturn('');
        $order->shouldReceive('get_meta')->with('_mollie_payment_id', true)->andReturn($this->trackedPaymentIdFor($status));

        return $order;
    }

    /**
     * The payment id a final-status order is tracking. It must equal the id of the late webhook's
     * payment in those tests, so the handler bails on the final status and not on staleness.
     */
    private function trackedPaymentIdFor(string $status): string
    {
        return $status === 'cancelled' ? 'tr_LATE_FAIL_CANCELLED' : 'tr_LATE_FAIL_REFUNDED';
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

    /**
     * Scenario: A canceled webhook still cancels a genuinely unpaid on-hold order (PIWOO-#1284 regression)
     *   Given an unpaid order sitting at on-hold (as every confirmationDelayed gateway starts), no paid/
     *     authorized meta, so needs_payment() is false but the order is NOT settled
     *   When a canceled-payment webhook is handled
     *   Then the settled guard must NOT trip and the cancellation is processed
     *
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookCanceled
     */
    public function test_on_webhook_canceled_still_cancels_on_hold_unpaid_order(): void
    {
        // Arrange
        $orderId      = 611;
        $order        = $this->makeOnHoldUnpaidOrder($orderId);
        $mollieObject = Mockery::mock(MolliePayment::class);
        $gateway      = Mockery::mock(PaymentGateway::class);
        $gateway->id  = 'mollie_wc_gateway_banktransfer';
        $payment      = $this->makePayment('tr_ON_HOLD_CANCEL');

        $mollieObject->shouldReceive('isFinalOrderStatus')->with($order)->andReturn(false);
        $mollieObject->shouldReceive('getCancelledMolliePaymentId')->andReturn('');
        $mollieObject->shouldReceive('deleteSubscriptionFromPending')->once()->with($order);

        $this->settings->shouldReceive('getOrderStatusCancelledPayments')->andReturn('pending');
        when('wc_get_payment_gateway_by_order')->justReturn($gateway);
        when('apply_filters')->returnArg(2);

        // When / Then — the on-hold order is genuinely unpaid, so the cancellation runs.
        $mollieObject->shouldReceive('unsetActiveMolliePayment')->once()->with($orderId, 'tr_ON_HOLD_CANCEL');
        $mollieObject->shouldReceive('setCancelledMolliePaymentId')->once()->with($orderId, 'tr_ON_HOLD_CANCEL');
        $mollieObject->shouldReceive('updateOrderStatus')->once();

        $this->sut->onWebhookCanceled($order, $payment, 'Bank Transfer', $mollieObject);
    }

    /**
     * Scenario: A failed webhook still fails a genuinely unpaid on-hold order (PIWOO-#1284 regression)
     *   Given an unpaid on-hold order with no paid/authorized meta
     *   When a failed-payment webhook is handled
     *   Then the settled guard must NOT trip and the failure pipeline runs
     *
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookFailed
     */
    public function test_on_webhook_failed_still_fails_on_hold_unpaid_order(): void
    {
        // Arrange
        $orderId      = 711;
        $order        = $this->makeOnHoldUnpaidOrder($orderId);
        $mollieObject = Mockery::mock(MolliePayment::class);
        $gateway      = Mockery::mock(PaymentGateway::class);
        $gateway->id  = 'mollie_wc_gateway_banktransfer';
        $payment      = $this->makePayment('tr_ON_HOLD_FAIL');
        $payment->details = new \stdClass();
        // The failing payment is the one the order currently tracks, not an older attempt.
        $order->shouldReceive('get_meta')->with('_mollie_payment_id', true)->andReturn($payment->id);

        $mollieObject->shouldReceive('isFinalOrderStatus')->with($order)->andReturn(false);

        when('wc_get_payment_gateway_by_order')->justReturn($gateway);
        when('apply_filters')->returnArg(2);
        when('esc_attr')->returnArg(1);

        // When / Then — the failure pipeline runs for a genuinely unpaid on-hold order.
        $mollieObject->shouldReceive('failedSubscriptionProcess')->once();

        $this->sut->onWebhookFailed($order, $payment, 'Bank Transfer', $mollieObject);
    }

    /**
     * Scenario: A late failed webhook must not fail an order in a final status (PR #1284 follow-up)
     *   Given an order that is already cancelled and carries NO Mollie settled meta — the state a
     *     pending order reaches when WooCommerce's hold-stock rule cancels it, or an admin does
     *   When a late failed-payment webhook for the abandoned payment is handled
     *   Then the failure pipeline does not run, so the order keeps its status and its stock is not
     *     restored a second time by MollieObject::updateOrderStatus()
     *
     * This is the case the removed !needs_payment() clause covered by accident: with no paid or
     * authorized meta to read, an explicit isFinalOrderStatus() check in onWebhookFailed — matching
     * onWebhookCanceled and onWebhookExpired — is the only thing that holds the order.
     *
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookFailed
     */
    public function test_on_webhook_failed_skips_cancelled_order_without_settled_meta(): void
    {
        // Arrange
        $orderId      = 712;
        $order        = $this->makeFinalStatusOrder($orderId, 'cancelled');
        $mollieObject = Mockery::mock(MolliePayment::class);
        $gateway      = Mockery::mock(PaymentGateway::class);
        $gateway->id  = 'mollie_wc_gateway_banktransfer';
        $payment      = $this->makePayment('tr_LATE_FAIL_CANCELLED');
        $payment->details = new \stdClass();

        $mollieObject->shouldReceive('isFinalOrderStatus')->with($order)->andReturn(true);

        // Stubbed so that a handler which wrongly falls through fails on the expectations below,
        // rather than on an undefined WooCommerce function.
        when('wc_get_payment_gateway_by_order')->justReturn($gateway);
        when('apply_filters')->returnArg(2);
        when('esc_attr')->returnArg(1);

        // When / Then — a cancelled order is final: nothing in the failure pipeline may run.
        $mollieObject->shouldReceive('failedSubscriptionProcess')->never();
        $mollieObject->shouldReceive('updateOrderStatus')->never();

        $this->sut->onWebhookFailed($order, $payment, 'Bank Transfer', $mollieObject);
    }

    /**
     * Scenario: A late failed webhook must not fail an already-refunded order (PIWOO-923, failed path)
     *   Given an order that is already refunded and, like the cancelled case above, carries no Mollie
     *     settled meta — the state of an order completed internally on an existing mandate (a $0.00
     *     subscription switch calls WC_Order::payment_complete() without setOrderPaidAndProcessed())
     *     and refunded afterwards
     *   When a late failed-payment webhook is handled
     *   Then the failure pipeline does not run and the order stays refunded
     *
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookFailed
     */
    public function test_on_webhook_failed_skips_refunded_order_without_settled_meta(): void
    {
        // Arrange
        $orderId      = 714;
        $order        = $this->makeFinalStatusOrder($orderId, 'refunded');
        $mollieObject = Mockery::mock(MolliePayment::class);
        $gateway      = Mockery::mock(PaymentGateway::class);
        $gateway->id  = 'mollie_wc_gateway_klarna';
        $payment      = $this->makePayment('tr_LATE_FAIL_REFUNDED');
        $payment->details = new \stdClass();

        $mollieObject->shouldReceive('isFinalOrderStatus')->with($order)->andReturn(true);

        // Stubbed so that a handler which wrongly falls through fails on the expectations below,
        // rather than on an undefined WooCommerce function.
        when('wc_get_payment_gateway_by_order')->justReturn($gateway);
        when('apply_filters')->returnArg(2);
        when('esc_attr')->returnArg(1);

        // When / Then — a refunded order is final: its status and stock are left alone.
        $mollieObject->shouldReceive('failedSubscriptionProcess')->never();
        $mollieObject->shouldReceive('updateOrderStatus')->never();

        $this->sut->onWebhookFailed($order, $payment, 'Klarna', $mollieObject);
    }

    /**
     * Scenario: A zero-total order is not mistaken for a settled one (PR #1284 follow-up)
     *   Given an unpaid order at pending with a zero order total and no paid/authorized meta —
     *     WC_Order::needs_payment() reports false for it, because it multiplies the status check by
     *     get_total() > 0
     *   When a failed-payment webhook is handled
     *   Then the settled guard must NOT trip and the failure is processed
     *
     * The order mock does not stub needs_payment(), so this also locks in that the guard reads no
     * third-party-filterable WooCommerce state (woocommerce_valid_order_statuses_for_payment /
     * woocommerce_order_needs_payment): a call would fail the mock.
     *
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookFailed
     */
    public function test_on_webhook_failed_still_fails_zero_total_unpaid_order(): void
    {
        // Arrange
        $orderId      = 713;
        $order        = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_status')->andReturn('pending');
        $order->shouldReceive('get_payment_method')->andReturn('mollie_wc_gateway_directdebit');
        $order->shouldReceive('add_order_note')->zeroOrMoreTimes();
        $order->shouldReceive('get_meta')->with('_mollie_paid_and_processed', true)->andReturn('');
        $order->shouldReceive('get_meta')->with('_mollie_authorized')->andReturn('');
        $mollieObject = Mockery::mock(MolliePayment::class);
        $gateway      = Mockery::mock(PaymentGateway::class);
        $gateway->id  = 'mollie_wc_gateway_directdebit';
        $payment      = $this->makePayment('tr_ZERO_TOTAL_FAIL');
        $payment->details = new \stdClass();
        // The failing payment is the one the order currently tracks, not an older attempt.
        $order->shouldReceive('get_meta')->with('_mollie_payment_id', true)->andReturn($payment->id);

        $mollieObject->shouldReceive('isFinalOrderStatus')->with($order)->andReturn(false);

        when('wc_get_payment_gateway_by_order')->justReturn($gateway);
        when('apply_filters')->returnArg(2);
        when('esc_attr')->returnArg(1);

        // When / Then — the order is unpaid, whatever its total: the failure is processed.
        $mollieObject->shouldReceive('failedSubscriptionProcess')->once();

        $this->sut->onWebhookFailed($order, $payment, 'SEPA Direct Debit', $mollieObject);
    }

    /**
     * Scenario: A failed webhook for a superseded payment does not fail the order (PR #1284 follow-up)
     *   Given an unpaid pending order that is already tracking a NEWER Mollie payment, because the
     *     shopper retried checkout after abandoning the first attempt
     *   When the older attempt reports failed
     *   Then the order is left alone, so the pending retry is not destroyed by the stale failure
     *
     * onWebhookExpired has had this check since it was written; onWebhookFailed did not, and the
     * removed !needs_payment() clause never covered it (a pending order needs payment).
     *
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookFailed
     */
    public function test_on_webhook_failed_skips_stale_payment_when_a_newer_one_is_tracked(): void
    {
        // Arrange
        $orderId      = 715;
        $order        = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_status')->andReturn('pending');
        $order->shouldReceive('get_payment_method')->andReturn('mollie_wc_gateway_ideal');
        $order->shouldReceive('add_order_note')->zeroOrMoreTimes();
        $order->shouldReceive('get_meta')->with('_mollie_paid_and_processed', true)->andReturn('');
        $order->shouldReceive('get_meta')->with('_mollie_authorized')->andReturn('');
        // The order has moved on to a newer payment.
        $order->shouldReceive('get_meta')->with('_mollie_payment_id', true)->andReturn('tr_NEWER_ATTEMPT');
        $mollieObject = Mockery::mock(MolliePayment::class);
        $gateway      = Mockery::mock(PaymentGateway::class);
        $gateway->id  = 'mollie_wc_gateway_ideal';
        $payment      = $this->makePayment('tr_OLDER_ATTEMPT');
        $payment->details = new \stdClass();

        $mollieObject->shouldReceive('isFinalOrderStatus')->with($order)->andReturn(false);

        when('wc_get_payment_gateway_by_order')->justReturn($gateway);
        when('apply_filters')->returnArg(2);
        when('esc_attr')->returnArg(1);

        // When / Then — the stale failure must not touch the order.
        $mollieObject->shouldReceive('failedSubscriptionProcess')->never();
        $mollieObject->shouldReceive('updateOrderStatus')->never();

        $this->sut->onWebhookFailed($order, $payment, 'iDEAL', $mollieObject);
    }

    /**
     * Scenario: An expired webhook still cancels a genuinely unpaid on-hold order (PIWOO-#1284 regression)
     *   Given an unpaid on-hold order whose stored payment id matches the expiring payment, no settled meta
     *   When an expired-payment webhook is handled
     *   Then the settled guard must NOT trip and the order is moved to the expired/cancelled status
     *
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookExpired
     */
    public function test_on_webhook_expired_still_cancels_on_hold_unpaid_order(): void
    {
        // Arrange
        $orderId      = 811;
        $order        = $this->makeOnHoldUnpaidOrder($orderId);
        $mollieObject = Mockery::mock(MolliePayment::class);
        $gateway      = Mockery::mock(PaymentGateway::class);
        $gateway->id  = 'mollie_wc_gateway_banktransfer';
        $payment      = $this->makePayment('tr_ON_HOLD_EXPIRE');
        // The expiring payment is the one currently tracked on the order (not an older attempt).
        $order->shouldReceive('get_meta')->with('_mollie_payment_id', true)->andReturn($payment->id);

        $this->logger->shouldReceive('log')->zeroOrMoreTimes();
        $mollieObject->shouldReceive('isFinalOrderStatus')->with($order)->andReturn(false);

        when('wc_get_payment_gateway_by_order')->justReturn($gateway);
        when('apply_filters')->returnArg(2);

        // When / Then — the on-hold order is genuinely unpaid, so it is expired/cancelled.
        $mollieObject->shouldReceive('updateOrderStatus')->once();
        $mollieObject->shouldReceive('unsetCancelledMolliePaymentId')->once()->with($orderId);

        $this->sut->onWebhookExpired($order, $payment, 'Bank Transfer', $mollieObject);
    }

    /**
     * Scenario: A paid webhook still completes an on-hold order (regression lock)
     *   Given a genuine paid payment for an unpaid on-hold order (no refund, no chargeback)
     *   When a paid webhook is handled
     *   Then the order is completed — onWebhookPaid must NOT consult the settled guard, otherwise
     *     banktransfer/directdebit (on-hold at checkout) would never complete on payment
     *
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookPaid
     */
    public function test_on_webhook_paid_still_completes_on_hold_order(): void
    {
        // Arrange
        $orderId      = 512;
        $order        = Mockery::mock(WC_Order::class);
        $mollieObject = Mockery::mock(MolliePayment::class);
        $payment      = $this->makePaidPayment(null, null);

        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_status')->andReturn('on-hold');
        $order->shouldReceive('has_status')->with('on-hold')->andReturn(true);
        $order->shouldReceive('needs_payment')->andReturn(false);
        $order->shouldReceive('get_meta')->andReturn('');

        // When / Then — an on-hold order that is genuinely paid is completed exactly once.
        $order->shouldReceive('payment_complete')->once()->with($payment->id);
        $order->shouldReceive('add_order_note')->once();
        $mollieObject->shouldReceive('setOrderPaidAndProcessed')->once()->with($order);
        $mollieObject->shouldReceive('unsetCancelledMolliePaymentId')->once()->with($orderId);
        $mollieObject->shouldReceive('addMandateIdMetaToFirstPaymentSubscriptionOrder')->once();

        $this->sut->onWebhookPaid($order, $payment, 'Bank Transfer', $mollieObject);
    }
}

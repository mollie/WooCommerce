<?php

namespace Mollie\WooCommerceTests\Integration\spec\Payment;

use Mockery;
use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerceTests\Integration\Common\PaymentFlowTestCase;
use WC_Order;

/**
 * What happens when the customer is not starting from a clean slate: the order is already paid, or
 * an earlier attempt is still open at Mollie, or Mollie rejects the attempt for a reason that must
 * not be retried.
 *
 * These are the paths a customer reaches by going back and trying again — the single most common
 * thing to do after a payment did not go through — and every one of them can take money twice or
 * strand the customer if it goes wrong. PaymentProcessorTest (tests/php/Functional) already covers
 * cancelExistingMolliePaymentIfPending() against hand-built doubles; what it cannot show is what the
 * whole checkout does with the result, which is the part a merchant sees: whether a second charge is
 * created, which Mollie references survive on the order, and where the customer is sent.
 *
 * The API-selection rules live in PaymentCreationIntegrationTest.
 */
class PaymentRetryIntegrationTest extends PaymentFlowTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // PaymentProcessor::reportPaymentCreationFailure() surfaces a failed checkout by hooking a
        // closure onto before_woocommerce_pay_form, and never unhooks it. Left alone, every failure
        // in this class piles another closure onto the same hook and the notice assertions start
        // reading messages an earlier test produced.
        remove_all_actions('before_woocommerce_pay_form');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scenarios
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: An order that is already paid is never charged a second time
     *   Given an order that WooCommerce already considers paid (status "processing")
     *   When a payment is started for it again — a resubmitted checkout, a stale tab, a double click
     *   Then the checkout fails before any Mollie call is made
     *   And the order keeps its status and gains no new Mollie reference
     *
     * @test
     * @group integration
     * @group PaymentRetry
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::processPayment
     */
    public function it_does_not_charge_an_order_that_is_already_paid()
    {
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');
        $order->set_status('processing');
        $order->save();
        $order = wc_get_order($order->get_id());

        $this->recordNoCreateExpected();

        $result = $this->processPayment($order, 'mollie_wc_gateway_ideal');

        $this->assertSame('failure', $result['result'], 'A paid order must not start another payment.');
        $this->assertCount(0, $this->ordersCreateCalls, 'A paid order must not reach the Orders API.');
        $this->assertCount(0, $this->paymentsCreateCalls, 'A paid order must not reach the Payments API.');

        $order = wc_get_order($order->get_id());
        $this->assertSame('processing', $order->get_status(), 'The paid order must be left exactly as it was.');
        $this->assertEmpty($order->get_transaction_id(), 'No new Mollie reference may be written to a paid order.');
    }

    /**
     * Scenario: A still-cancellable earlier attempt is cancelled before a new one is created
     *   Given an order carrying a Mollie order from an earlier attempt that is still in status "created"
     *   When the customer starts the payment again
     *   Then the earlier Mollie order is cancelled at Mollie and the cancellation is noted on the order
     *   And a fresh Mollie order is created, which the order now points at
     *     (leaving the old one open would let the customer pay twice for one WooCommerce order)
     *
     * @test
     * @group integration
     * @group PaymentRetry
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::cancelExistingMolliePaymentIfPending
     */
    public function it_cancels_a_still_open_earlier_attempt_before_creating_a_new_one()
    {
        $this->useOrdersApi();
        $order = $this->orderWithEarlierAttempt('mollie_wc_gateway_ideal', ['_mollie_order_id' => 'ord_earlier']);

        $cancelled = false;
        $this->stubMollieOrderGet('ord_earlier', [
            'isCanceled' => false,
            'isCreated' => true,
        ], function () use (&$cancelled): void {
            $cancelled = true;
        });

        $this->recordOrdersCreate(function (): object {
            return $this->createdMollieOrder('ord_replacement', 'ideal');
        });
        $this->recordPaymentsCreate(function (): object {
            return $this->createdMolliePayment('tr_unexpected', 'ideal');
        });

        $result = $this->processPayment($order, 'mollie_wc_gateway_ideal');

        $this->assertTrue($cancelled, 'The earlier Mollie order must be cancelled at Mollie before a new one is created.');
        $this->assertSame('success', $result['result'], 'Cancelling the earlier attempt must not stop the new payment.');
        $this->assertCount(1, $this->ordersCreateCalls, 'Exactly one replacement Mollie order must be created.');

        $order = wc_get_order($order->get_id());
        $this->assertOrderHasNoteContaining($order, 'Previous pending Mollie payment ord_earlier canceled before creating a new one.');
        $this->assertSame(
            'ord_replacement',
            $order->get_meta('_mollie_order_id'),
            'The order must point at the replacement Mollie order, not the cancelled one.'
        );
    }

    /**
     * Scenario: An open payment that Mollie will not let us cancel is reused
     *   Given an order with an open Mollie payment that is not cancellable (a bank transfer awaiting
     *     the transfer, a Klarna authorisation in flight)
     *   And the customer picks the same payment method again
     *   When the customer starts the payment again
     *   Then no second payment is created and the customer is sent back to the checkout already
     *     waiting for them — creating a second one would leave two live payments on one order
     *
     * @test
     * @group integration
     * @group PaymentRetry
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::activePaymentResponseOrMethodSwitch
     */
    public function it_reuses_an_open_non_cancellable_payment_for_the_same_method()
    {
        $order = $this->orderWithEarlierAttempt('mollie_wc_gateway_ideal', [
            '_mollie_payment_id' => 'tr_stillopen',
            '_mollie_payment_method' => 'mollie_wc_gateway_ideal',
        ]);

        $this->stubOpenNonCancellablePayment('tr_stillopen');
        $this->recordNoCreateExpected();

        $result = $this->processPayment($order, 'mollie_wc_gateway_ideal');

        $this->assertSame('success', $result['result'], 'Reusing the open payment is a successful checkout, not a failure.');
        $this->assertSame(
            $this->checkoutUrl('tr_stillopen'),
            $result['redirect'],
            'The customer must be sent back to the checkout of the payment already in flight.'
        );
        $this->assertCount(0, $this->ordersCreateCalls, 'No second Mollie order may be created alongside an open payment.');
        $this->assertCount(0, $this->paymentsCreateCalls, 'No second Mollie payment may be created alongside an open payment.');

        $order = wc_get_order($order->get_id());
        $this->assertSame(
            'tr_stillopen',
            $order->get_meta('_mollie_payment_id'),
            'The order must still point at the payment it was already linked to.'
        );
    }

    /**
     * Scenario: Choosing a different method creates a new payment instead of reusing the open one
     *   Given an order with an open, non-cancellable Mollie payment created by the Credit Card gateway
     *   When the customer comes back and picks iDEAL instead
     *   Then a new Mollie payment is created for iDEAL rather than the customer being pushed back to
     *     the credit card checkout they deliberately abandoned
     *   And the order now records iDEAL as the gateway behind the linked payment
     *
     * @test
     * @group integration
     * @group PaymentRetry
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::selectedGatewayDiffersFromCreatingGateway
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::saveMollieInfo
     */
    public function it_creates_a_new_payment_when_the_customer_switches_method()
    {
        $this->usePaymentsApi();
        $order = $this->orderWithEarlierAttempt('mollie_wc_gateway_ideal', [
            '_mollie_payment_id' => 'tr_stillopen',
            '_mollie_payment_method' => 'mollie_wc_gateway_creditcard',
        ]);

        $this->stubOpenNonCancellablePayment('tr_stillopen');
        $this->recordOrdersCreate(function (): object {
            return $this->createdMollieOrder('ord_unexpected', 'ideal');
        });
        $this->recordPaymentsCreate(function (): object {
            return $this->createdMolliePayment('tr_switched', 'ideal');
        });

        $result = $this->processPayment($order, 'mollie_wc_gateway_ideal');

        $this->assertSame('success', $result['result'], 'Switching method must produce a usable checkout.');
        $this->assertCount(1, $this->paymentsCreateCalls, 'A method switch must create a new Mollie payment.');
        $this->assertSame(
            $this->checkoutUrl('tr_switched'),
            $result['redirect'],
            'The customer must go to the checkout of the newly selected method, not the abandoned one.'
        );

        $order = wc_get_order($order->get_id());
        $this->assertSame('tr_switched', $order->get_meta('_mollie_payment_id'), 'The order must point at the new payment.');
        $this->assertSame(
            'mollie_wc_gateway_ideal',
            $order->get_meta('_mollie_payment_method'),
            'The order must record the gateway that created the payment it now points at, or the next '
            . 'retry compares against a stale one.'
        );
    }

    /**
     * Scenario: A rejection Mollie classifies is never retried on the Payments API
     *   Given a pending iDEAL order and the plugin configured to use the Orders API
     *   When Mollie rejects the order because of an outage, a suspected fraud, or an invalid phone number
     *   Then the checkout fails and the customer is told, on the pay page, that the payment could not be created
     *   And the plugin does not fall back to the Payments API the way it does for an ordinary
     *     validation error — a fraud rejection retried as a payment is the same charge attempted twice,
     *     and an outage retried immediately just fails again
     *
     * Only the "could not create" half of the message is asserted: the reason itself is appended to
     * the notice only when WP_DEBUG is on, so asserting it would encode this box's wp-config into the
     * test.
     *
     * @test
     * @dataProvider classifiedMollieRejections
     * @group integration
     * @group PaymentRetry
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::handleProcessingException
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::reportPaymentCreationFailure
     */
    public function it_does_not_retry_a_rejection_mollie_classifies(string $message, int $code)
    {
        $this->useOrdersApi();
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');

        $this->recordOrdersCreate(function () use ($message, $code): object {
            throw new ApiException($message, $code);
        });
        $this->recordPaymentsCreate(function (): object {
            return $this->createdMolliePayment('tr_unexpected', 'ideal');
        });

        $result = $this->processPayment($order, 'mollie_wc_gateway_ideal');

        $this->assertSame('failure', $result['result'], 'A classified Mollie rejection must fail the checkout.');
        $this->assertCount(1, $this->ordersCreateCalls, 'The Orders API must be tried exactly once.');
        $this->assertCount(
            0,
            $this->paymentsCreateCalls,
            'A classified Mollie rejection must not be retried on the Payments API.'
        );

        $this->assertStringContainsString(
            'Could not create ideal payment.',
            $this->renderPayPageNotices(),
            'The customer must be told on the pay page that the payment could not be created.'
        );

        $order = wc_get_order($order->get_id());
        $this->assertEmpty($order->get_transaction_id(), 'A rejected payment must leave no Mollie reference on the order.');
    }

    /**
     * The three rejections Api::isMollieException() classifies, in the shape the Mollie SDK raises
     * them. See Api::isMollieOutageException(), isMollieFraudException(), isUnprocessablePhoneException().
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public function classifiedMollieRejections(): array
    {
        return [
            'outage' => ['Service temporarily unavailable.', 500],
            'fraud rejection' => ['The payment was declined due to suspected fraud', 422],
            'invalid phone number' => ['Unprocessable Entity: The phone number is invalid', 422],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Fixtures
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * A pending order that already carries the Mollie meta of an earlier payment attempt.
     *
     * @param array<string, string> $attemptMeta
     */
    private function orderWithEarlierAttempt(string $gatewayId, array $attemptMeta): WC_Order
    {
        $order = $this->pendingOrder($gatewayId);

        foreach ($attemptMeta as $key => $value) {
            $order->update_meta_data($key, $value);
        }
        $order->save();

        return wc_get_order($order->get_id());
    }

    /**
     * Stubs orders->get() for an earlier Mollie order.
     *
     * Hand-built rather than routed through MockedApi::mockOrderResourceGet(): the retry path asks
     * an existing Mollie order isCreated()/isShipping() and calls cancel() on it, and none of those
     * exist on the double MockedApi builds for the webhook tests.
     *
     * @param array<string, bool> $states the is*() answers, defaulting to false
     * @param callable|null $onCancel invoked when production calls cancel()
     */
    private function stubMollieOrderGet(string $mollieOrderId, array $states, ?callable $onCancel = null): void
    {
        $double = Mockery::mock('EarlierMollieOrder');
        $double->id = $mollieOrderId;
        $double->resource = 'order';

        foreach (['isCanceled', 'isCreated', 'isAuthorized', 'isShipping', 'isCompleted', 'isExpired'] as $state) {
            $double->shouldReceive($state)->andReturn($states[$state] ?? false);
        }
        $double->shouldReceive('getCheckoutUrl')->andReturn($this->checkoutUrl($mollieOrderId));
        $double->shouldReceive('cancel')->andReturnUsing(static function () use ($onCancel) {
            if ($onCancel !== null) {
                $onCancel();
            }
            return null;
        });

        // Match on the id alone: production calls orders->get() with and without an embed option
        // depending on the call site, and an arity mismatch would fall through to MockedApi's
        // 404 default.
        $this->apiMock()->getMockedApiClient()->orders
            ->shouldReceive('get')
            ->withArgs(static function ($id = null) use ($mollieOrderId) {
                return $id === $mollieOrderId;
            })
            ->andReturn($double);
    }

    /**
     * Stubs payments->get() for a payment that is still live at Mollie and cannot be cancelled: the
     * exact combination that makes the processor decide between reusing it and creating a new one.
     */
    private function stubOpenNonCancellablePayment(string $molliePaymentId): void
    {
        $double = Mockery::mock('OpenMolliePayment');
        $double->id = $molliePaymentId;
        $double->resource = 'payment';
        $double->mode = 'test';
        $double->method = 'ideal';
        $double->status = 'open';
        $double->isCancelable = false;

        foreach (['isCanceled', 'isPaid', 'isExpired', 'isFailed', 'isAuthorized', 'isCompleted', 'isPending'] as $state) {
            $double->shouldReceive($state)->andReturn(false);
        }
        $double->shouldReceive('getCheckoutUrl')->andReturn($this->checkoutUrl($molliePaymentId));

        $this->apiMock()->getMockedApiClient()->payments
            ->shouldReceive('get')
            ->withArgs(static function ($id = null) use ($molliePaymentId) {
                return $id === $molliePaymentId;
            })
            ->andReturn($double);
    }

    /**
     * Renders whatever the checkout queued for the "pay for this order" page.
     *
     * A failed payment has no return value a customer sees; the only customer-facing trace is a
     * notice the processor hooks onto before_woocommerce_pay_form, so the hook is fired and its
     * output captured.
     */
    private function renderPayPageNotices(): string
    {
        ob_start();
        do_action('before_woocommerce_pay_form');

        return (string) ob_get_clean();
    }
}

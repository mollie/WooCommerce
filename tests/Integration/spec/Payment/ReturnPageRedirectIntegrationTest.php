<?php

namespace Mollie\WooCommerceTests\Integration\spec\Payment;

use Mockery;
use Mollie\Api\Resources\Payment as MollieApiPayment;
use Mollie\WooCommerceTests\Integration\Common\PaymentFlowTestCase;
use WC_Order;
use WC_Payment_Gateways;

/**
 * Where the customer lands when they come back from Mollie.
 *
 * This is the last thing a customer sees, and it is decided from the order's own state rather than
 * from anything Mollie puts in the return URL — the payment status arrives separately, over the
 * webhook. So the same return URL has to resolve to "thank you", "please try another method", or
 * "your payment did not work" depending on what the order looks like at that instant, and getting it
 * wrong means either thanking someone who has not paid or sending a paying customer back to a
 * payment form.
 *
 * The tests drive MolliePaymentGatewayHandler::getReturnRedirectUrlForOrder(), which holds every one
 * of those decisions. The thin wrapper around it — PaymentModule::onMollieReturn() — is not
 * reachable from PHPUnit: it reads the order via filter_input(INPUT_GET, ...), which always returns
 * null under the CLI SAPI, and it ends in wp_safe_redirect() + die(). PaymentModuleReturnRedirectTest
 * already covers the part of that wrapper which can be observed (that the hook is registered and
 * entered). What the wrapper adds on top of the URL asserted here is a single
 * add_query_arg('utm_nooverride', 1) call.
 */
class ReturnPageRedirectIntegrationTest extends PaymentFlowTestCase
{
    /**
     * Hook names fired during the call under test, in order.
     *
     * @var array<int, string>
     */
    private array $firedReturnHooks = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->firedReturnHooks = [];

        // Notices live in the WooCommerce session, which outlives a single test. Without this, a
        // notice another test queued is still there and "the customer was told X" passes for the
        // wrong reason.
        wc_clear_notices();
    }

    public function tearDown(): void
    {
        wc_clear_notices();

        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scenarios
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: A customer returning from a payment that went through lands on order-received
     *   Given an order Mollie has already settled — paid, processed, no longer needing payment
     *   When the customer returns from Mollie
     *   Then they are sent to the order-received page
     *   And the plugin announces a successful return, so extensions listening for it fire
     *   And nothing is put in front of the customer as an error
     *
     * @test
     * @group integration
     * @group ReturnPage
     * @covers \Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler::getReturnRedirectUrlForOrder
     */
    public function it_sends_a_settled_order_to_the_order_received_page()
    {
        $order = $this->settledOrder('mollie_wc_gateway_ideal', 'tr_settled');

        $redirectUrl = $this->returnRedirectUrlFor($order, 'mollie_wc_gateway_ideal');

        $this->assertSame(
            $this->orderReceivedUrl($order, 'mollie_wc_gateway_ideal'),
            $redirectUrl,
            'A settled order must land on the order-received page.'
        );
        $this->assertSame(
            ['mollie-payments-for-woocommerce_customer_return_payment_success'],
            $this->firedReturnHooks,
            'A settled return must announce success, and only success.'
        );
        $this->assertSame([], wc_get_notices('error'), 'A settled return must not show the customer an error.');
    }

    /**
     * Scenario: A cancelled payment sends the customer back to pay with another method
     *   Given an unpaid order carrying a Mollie payment the customer cancelled
     *   And the merchant left cancelled payments on the default "pending", so the order stays payable
     *   When the customer returns from Mollie
     *   Then they are sent to the order-pay page rather than to a thank-you page for an unpaid order
     *   And they are told their payment was cancelled and to use a different method
     *
     * @test
     * @group integration
     * @group ReturnPage
     * @covers \Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler::getReturnRedirectUrlForOrder
     */
    public function it_sends_a_cancelled_payment_back_to_the_pay_page_to_retry()
    {
        $this->setCancelledPaymentsSetting('pending');
        $order = $this->unpaidOrderWithCancelledPayment('mollie_wc_gateway_ideal', 'tr_cancelled');

        $redirectUrl = $this->returnRedirectUrlFor($order, 'mollie_wc_gateway_ideal');

        $this->assertSame(
            $order->get_checkout_payment_url(false),
            $redirectUrl,
            'A cancelled payment on a still-payable order must return the customer to the pay page.'
        );
        $this->assertContains(
            'You have cancelled your payment. Please complete your order with a different payment method.',
            $this->errorNoticeMessages(),
            'The customer must be told why they are back on the pay page.'
        );
        $this->assertSame([], $this->firedReturnHooks, 'The cancelled branch returns before announcing a return outcome.');
    }

    /**
     * Scenario: A cancelled payment ends the order when the merchant configured it that way
     *   Given an unpaid order carrying a Mollie payment the customer cancelled
     *   And the merchant set cancelled payments to cancel the order outright
     *   When the customer returns from Mollie
     *   Then they are sent to the order-received page, which will show the order as cancelled —
     *     there is nothing left to pay, so offering another payment method would be a dead end
     *   And they are not invited to retry
     *
     * @test
     * @group integration
     * @group ReturnPage
     * @covers \Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler::getReturnRedirectUrlForOrder
     */
    public function it_ends_the_order_on_a_cancelled_payment_when_the_setting_says_cancelled()
    {
        $this->setCancelledPaymentsSetting('cancelled');
        $order = $this->unpaidOrderWithCancelledPayment('mollie_wc_gateway_ideal', 'tr_cancelled');

        $redirectUrl = $this->returnRedirectUrlFor($order, 'mollie_wc_gateway_ideal');

        $this->assertSame(
            $this->orderReceivedUrl($order, 'mollie_wc_gateway_ideal'),
            $redirectUrl,
            'With cancelled payments set to cancel the order, the customer must land on order-received.'
        );
        $this->assertNotContains(
            'You have cancelled your payment. Please complete your order with a different payment method.',
            $this->errorNoticeMessages(),
            'A cancelled order must not invite the customer to pay it with another method.'
        );
        $this->assertSame([], $this->firedReturnHooks, 'The cancelled branch returns before announcing a return outcome.');
    }

    /**
     * Scenario: A payment that did not succeed sends the customer back to try another method
     *   Given an unpaid order whose Mollie payment is in a state that will never settle
     *     (failed here; expired and canceled reach the same branch)
     *   When the customer returns from Mollie
     *   Then they are sent to the order-pay page and told the payment was not successful
     *
     * @test
     * @group integration
     * @group ReturnPage
     * @covers \Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler::getReturnRedirectUrlForOrder
     */
    public function it_sends_an_unsuccessful_payment_back_to_the_pay_page()
    {
        $order = $this->unpaidOrderWithPayment('mollie_wc_gateway_ideal', 'tr_failed');
        $this->stubPaymentGet('tr_failed', ['isFailed' => true]);

        $redirectUrl = $this->returnRedirectUrlFor($order, 'mollie_wc_gateway_ideal');

        $this->assertSame(
            $order->get_checkout_payment_url(false),
            $redirectUrl,
            'A payment that will never settle must return the customer to the pay page.'
        );
        $this->assertContains(
            'Your payment was not successful. Please complete your order with a different payment method.',
            $this->errorNoticeMessages(),
            'The customer must be told the payment was not successful.'
        );
        $this->assertSame([], $this->firedReturnHooks, 'The early return must not announce a return outcome at all.');
    }

    /**
     * Scenario: A return whose payment cannot be resolved fails safe
     *   Given an unpaid order pointing at a Mollie payment the API will not return
     *     (a wrong API key after a mode switch, a payment on another account, an outage)
     *   When the customer returns from Mollie
     *   Then the plugin announces a failed return rather than a successful one, so extensions
     *     listening for success do not act on an order nobody has confirmed was paid
     *   And the customer is told the payment was not successful
     *
     * The customer still lands on order-received here — the handler records the failure and falls
     * through rather than returning the pay page, unlike the branch above. Asserted as-is: that is
     * the behaviour, and pinning it down is what makes a later change to it visible.
     *
     * @test
     * @group integration
     * @group ReturnPage
     * @covers \Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler::getReturnRedirectUrlForOrder
     * @covers \Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler::activePaymentObject
     */
    public function it_announces_a_failed_return_when_the_payment_cannot_be_resolved()
    {
        // No stub for this id: MockedApi answers an unknown payment with the 404 the real API gives,
        // which is what makes the active payment unresolvable.
        $order = $this->unpaidOrderWithPayment('mollie_wc_gateway_ideal', 'tr_vanished');

        $redirectUrl = $this->returnRedirectUrlFor($order, 'mollie_wc_gateway_ideal');

        $this->assertSame(
            ['mollie-payments-for-woocommerce_customer_return_payment_failed'],
            $this->firedReturnHooks,
            'An unresolvable payment must announce a failed return, never a successful one.'
        );
        $this->assertContains(
            'Your payment was not successful. Please complete your order with a different payment method.',
            $this->errorNoticeMessages(),
            'The customer must be told the payment was not successful.'
        );
        $this->assertSame(
            $this->orderReceivedUrl($order, 'mollie_wc_gateway_ideal'),
            $redirectUrl,
            'The handler falls through to order-received after recording the failure.'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Driving the return handler
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Boots the plugin against the mocked Mollie API and asks the gateway handler where this order's
     * customer should be sent, recording the return hooks it fires on the way.
     *
     * The handler comes from the container's gateway helpers, which is exactly where
     * PaymentModule::onMollieReturn() gets it.
     */
    private function returnRedirectUrlFor(WC_Order $order, string $gatewayId): string
    {
        $container = $this->bootstrapModule($this->getMockedApiServices());
        WC_Payment_Gateways::instance()->init();

        $this->recordReturnHooks();

        $helpers = $container->get('__deprecated.gateway_helpers');
        $this->assertArrayHasKey($gatewayId, $helpers, "Gateway helper {$gatewayId} is missing; the test cannot drive it.");

        return $helpers[$gatewayId]->getReturnRedirectUrlForOrder($order);
    }

    /**
     * Listens for the two hooks the handler uses to announce the outcome of a return.
     *
     * They are the plugin's public contract with extensions — the only signal an extension gets that
     * a customer came back and whether it went well — so which one fires is worth asserting on its
     * own, separately from the URL.
     */
    private function recordReturnHooks(): void
    {
        foreach (['success', 'failed'] as $outcome) {
            $hook = 'mollie-payments-for-woocommerce_customer_return_payment_' . $outcome;
            remove_all_actions($hook);
            add_action($hook, function () use ($hook): void {
                $this->firedReturnHooks[] = $hook;
            });
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Fixtures
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * An order Mollie has settled: paid at WooCommerce and marked processed by the plugin, which is
     * the combination that makes MollieOrderService::orderNeedsPayment() answer no.
     */
    private function settledOrder(string $gatewayId, string $molliePaymentId): WC_Order
    {
        $order = $this->pendingOrder($gatewayId);
        $order->update_meta_data('_mollie_payment_id', $molliePaymentId);
        $order->update_meta_data('_mollie_payment_mode', 'test');
        $order->update_meta_data('_mollie_paid_and_processed', '1');
        $order->set_transaction_id($molliePaymentId);
        $order->set_status('processing');
        $order->save();

        $this->stubPaymentGet($molliePaymentId, ['isPaid' => true]);

        return wc_get_order($order->get_id());
    }

    /**
     * An unpaid order linked to a Mollie payment.
     */
    private function unpaidOrderWithPayment(string $gatewayId, string $molliePaymentId): WC_Order
    {
        $order = $this->pendingOrder($gatewayId);
        $order->update_meta_data('_mollie_payment_id', $molliePaymentId);
        $order->update_meta_data('_mollie_payment_mode', 'test');
        $order->save();

        return wc_get_order($order->get_id());
    }

    /**
     * An unpaid order whose linked payment the customer cancelled — the state
     * WebhookHandler::onWebhookCanceled() leaves behind.
     */
    private function unpaidOrderWithCancelledPayment(string $gatewayId, string $molliePaymentId): WC_Order
    {
        $order = $this->unpaidOrderWithPayment($gatewayId, $molliePaymentId);
        $order->update_meta_data('_mollie_cancelled_payment_id', $molliePaymentId);
        $order->save();

        return wc_get_order($order->get_id());
    }

    /**
     * Stubs payments->get() for one payment id.
     *
     * Hand-built rather than routed through MockedApi::mockSuccessfulPaymentGet(), for two reasons.
     * The return handler asks the payment isOpen(), which is not one of the states MockedApi's
     * double answers, so that double raises a Mockery BadMethodCallException from inside the
     * handler. And MolliePaymentGatewayHandler::activePaymentObject() declares a `: Payment` return
     * type, so the double has to be a Payment, not a loose named mock.
     *
     * @param array<string, bool> $states the is*() answers, defaulting to false
     */
    private function stubPaymentGet(string $molliePaymentId, array $states): void
    {
        $double = Mockery::mock(MollieApiPayment::class);
        $double->id = $molliePaymentId;
        $double->resource = 'payment';
        $double->mode = 'test';
        $double->method = 'ideal';

        foreach (['isOpen', 'isPending', 'isPaid', 'isAuthorized', 'isFailed', 'isCanceled', 'isExpired'] as $state) {
            $double->shouldReceive($state)->andReturn($states[$state] ?? false);
        }

        $this->apiMock()->getMockedApiClient()->payments
            ->shouldReceive('get')
            ->withArgs(static function ($id = null) use ($molliePaymentId) {
                return $id === $molliePaymentId;
            })
            ->andReturn($double);
    }

    private function setCancelledPaymentsSetting(string $value): void
    {
        $this->setOptionForTest('mollie-payments-for-woocommerce_order_status_cancelled_payments', $value);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Assertions
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The order-received URL WooCommerce itself would build for this order, so the assertion does not
     * depend on the permalink structure of the box the suite runs on.
     */
    private function orderReceivedUrl(WC_Order $order, string $gatewayId): string
    {
        return WC()->payment_gateways()->payment_gateways()[$gatewayId]->get_return_url($order);
    }

    /**
     * @return array<int, string>
     */
    private function errorNoticeMessages(): array
    {
        return array_map(static function ($notice) {
            return is_array($notice) ? ($notice['notice'] ?? '') : (string) $notice;
        }, wc_get_notices('error'));
    }
}

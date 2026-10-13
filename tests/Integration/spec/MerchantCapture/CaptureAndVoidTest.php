<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\MerchantCapture;

use Mockery;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;
use WC_Order;

/**
 * "Authorize now, capture later" for card payments: the authorisation Mollie reports puts the
 * order on hold, the merchant captures it by moving the order on, or releases it by cancelling.
 *
 * No e2e case covers this; tests/Integration/e2e-equivalence.md says so.
 *
 * @group integration
 * @group MerchantCapture
 */
class CaptureAndVoidTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    private const STATE = '_mollie_payment_status';

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->recordWebhookHooks();
        $this->setOptionForTest(self::PLUGIN_ID . '_place_payment_onhold', 'later_capture');
        $this->setOptionForTest(self::PLUGIN_ID . '_capture_or_void', 'yes');
    }

    public function tearDown(): void
    {
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Scenario: an authorised card payment waits on hold for the merchant
     *   Given capture later is switched on
     *   And an order whose card payment Mollie authorised
     *   When Mollie calls the webhook
     *   Then the order is on hold and marked as authorised
     *   And nothing was captured
     *
     * @test
     * @covers \Mollie\WooCommerce\MerchantCapture\MerchantCaptureModule::run
     */
    public function it_puts_an_authorised_card_payment_on_hold_for_the_merchant(): void
    {
        [$order] = $this->authorisedOrder();

        $this->assertSame('on-hold', $order->get_status());
        $this->assertSame('authorized', $order->get_meta(self::STATE));
        $this->assertSame([], $this->fakeMollie()->captures());
    }

    /**
     * Scenario: moving the order on captures the authorisation
     *   Given an authorised order on hold, and capture on status change switched on
     *   When the merchant sets the order to processing, or to completed
     *   Then Mollie is asked once to capture the order total on that payment
     *   And the order is marked as waiting for the capture, with a note
     *   When Mollie reports the payment paid
     *   Then the order is marked as captured
     *
     * @test
     * @dataProvider statusesThatCapture
     * @covers \Mollie\WooCommerce\MerchantCapture\Capture\Type\StateChangeCapture::orderStatusChange
     */
    public function it_captures_the_authorisation_when_the_merchant_moves_the_order_on(string $newStatus): void
    {
        [$order, $paymentId] = $this->authorisedOrder();

        $order->update_status($newStatus);

        $captures = array_values($this->fakeMollie()->captures());
        $this->assertCount(1, $captures);
        $this->assertSame($paymentId, $captures[0]['paymentId']);
        $this->assertSame(['currency' => 'EUR', 'value' => $this->formattedTotal($order)], $captures[0]['amount']);
        $waiting = $this->fresh($order);
        $this->assertSame('waiting', $waiting->get_meta(self::STATE));
        $this->assertCount(1, $this->notesContaining($waiting, 'has been sent successfully, and we are currently awaiting confirmation.'));

        $this->fakeMollie()->setPaymentStatus($paymentId, 'paid');
        $this->newRequest();
        $this->assertSame(200, $this->deliverWebhook($paymentId));

        $captured = $this->fresh($order);
        $this->assertSame('captured', $captured->get_meta(self::STATE));
        $this->assertSame($newStatus, $captured->get_status());
        $this->assertCount(1, $this->fakeMollie()->captures(), 'The webhook captures nothing again.');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function statusesThatCapture(): array
    {
        return [
            'processing' => ['processing'],
            'completed' => ['completed'],
        ];
    }

    /**
     * Scenario: cancelling the order releases the authorisation
     *   Given an authorised order on hold, and capture on status change switched on
     *   When the merchant cancels the order
     *   Then the payment is cancelled at Mollie and the order is marked as voided
     *   And nothing was captured
     *
     * @test
     * @covers \Mollie\WooCommerce\MerchantCapture\Capture\Action\VoidPayment::__invoke
     */
    public function it_releases_the_authorisation_when_the_merchant_cancels_the_order(): void
    {
        [$order, $paymentId] = $this->authorisedOrder();

        $order->update_status('cancelled');

        $this->assertSame('canceled', $this->fakeMollie()->payments()[$paymentId]['status']);
        $this->assertSame('voided', $this->fresh($order)->get_meta(self::STATE));
        $this->assertSame([], $this->fakeMollie()->captures());
    }

    /**
     * Scenario: with capture on status change switched off, moving the order on captures nothing
     *   Given an authorised order on hold
     *   And capture on status change switched off
     *   When the merchant sets the order to processing
     *   Then Mollie is not asked to capture, and the order stays marked as authorised
     *
     * @test
     * @covers \Mollie\WooCommerce\MerchantCapture\Capture\Type\StateChangeCapture::orderStatusChange
     */
    public function it_captures_nothing_on_a_status_change_when_that_is_switched_off(): void
    {
        $this->setOptionForTest(self::PLUGIN_ID . '_capture_or_void', 'no');
        [$order] = $this->authorisedOrder();

        $order->update_status('processing');

        $this->assertSame([], $this->fakeMollie()->captures());
        $this->assertSame('authorized', $this->fresh($order)->get_meta(self::STATE));
    }

    /**
     * Scenario: a capture Mollie refuses is noted, and the order stays authorised
     *   Given an authorised order on hold
     *   When the merchant moves it on and Mollie refuses the capture
     *   Then a note says the capture failed and the order is still marked as authorised
     *
     * @test
     * @covers \Mollie\WooCommerce\MerchantCapture\Capture\Action\CapturePayment::__invoke
     */
    public function it_notes_a_capture_mollie_refuses(): void
    {
        [$order, $paymentId] = $this->authorisedOrder();
        $this->fakeMollie()->failNext('POST', "payments/{$paymentId}/captures", 422, 'The payment cannot be captured.');

        $order->update_status('processing');

        $after = $this->fresh($order);
        $this->assertSame([], $this->fakeMollie()->captures());
        $this->assertSame('authorized', $after->get_meta(self::STATE));
        $this->assertCount(1, $this->notesContaining($after, 'Payment Capture Failed.'));
    }

    /**
     * Scenario: the capture action of the order screen is offered only for an order that can be captured
     *   Given an authorised order on hold
     *   Then the order actions offer "Capture authorized Mollie payment"
     *   And an order of another method is offered no such action
     *
     * @test
     * @covers \Mollie\WooCommerce\MerchantCapture\Capture\Type\ManualCapture::enableOrderCaptureButton
     */
    public function it_offers_the_capture_action_only_for_an_order_that_can_be_captured(): void
    {
        [$order] = $this->authorisedOrder();
        $other = $this->pendingOrder('mollie_wc_gateway_ideal');

        $this->assertArrayHasKey('mollie_capture_authorized_payment', apply_filters('woocommerce_order_actions', [], $order));
        $this->assertArrayNotHasKey('mollie_capture_authorized_payment', apply_filters('woocommerce_order_actions', [], $other));
    }

    /**
     * A card order whose authorisation the webhook already reported.
     *
     * @return array{0: WC_Order, 1: string}
     */
    private function authorisedOrder(): array
    {
        $this->newRequest();
        $order = $this->pendingOrder('mollie_wc_gateway_creditcard');
        $payment = $this->fakeMollie()->seedPayment([
            'amount' => ['currency' => 'EUR', 'value' => $this->formattedTotal($order)],
            'method' => 'creditcard',
            'status' => 'authorized',
            'captureMode' => 'manual',
            'metadata' => ['order_id' => $order->get_id()],
        ]);
        $order->update_meta_data('_mollie_payment_id', $payment['id']);
        $order->set_transaction_id($payment['id']);
        $order->save();
        $this->assertSame(200, $this->deliverWebhook($payment['id']));
        // The merchant acts in a request of their own, after the webhook's.
        $this->newRequest();

        return [$this->fresh($order), $payment['id']];
    }

    /**
     * Boots the plugin as a new request does. The capture listeners are attached on init, and the
     * webhook switches the status listener off for the rest of its own request.
     */
    private function newRequest(): void
    {
        $this->removePluginListenersOfEarlierBoots();
        $this->container = $this->bootExpressOwning(['init']);
        do_action('init');
    }
}

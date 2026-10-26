<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Payment;

use Mockery;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;
use WC_Order;

/**
 * What the merchant does to an order in WooCommerce and Mollie has to hear of: cancelling it,
 * and completing it, which for pay-later methods ships the Mollie order or captures the payment.
 *
 * No e2e case covers this; tests/Integration/e2e-equivalence.md says so.
 *
 * @group integration
 * @group OrderStatusToMollie
 */
class OrderStatusToMollieTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->recordWebhookHooks();
        $this->fakeMollie()->setMethods(['ideal', 'klarna', 'creditcard']);
    }

    public function tearDown(): void
    {
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Scenario: cancelling an order in WooCommerce cancels its Mollie order
     *   Given an order created through the Orders API whose Mollie order can still be cancelled
     *   When the merchant cancels the order in WooCommerce
     *   Then the Mollie order is cancelled, and a note says so
     *
     * @test
     * @dataProvider cancellableMollieOrders
     * @covers \Mollie\WooCommerce\Payment\PaymentModule::cancelOrderAtMollie
     */
    public function it_cancels_the_mollie_order_when_the_order_is_cancelled(string $mollieOrderStatus): void
    {
        [$order, $mollieOrderId] = $this->orderWithMollieOrder($mollieOrderStatus);

        $order->update_status('cancelled');

        $this->assertSame('canceled', $this->fakeMollie()->orders()[$mollieOrderId]['status']);
        $this->assertCount(1, $this->notesContaining($this->fresh($order), 'Order also cancelled at Mollie.'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function cancellableMollieOrders(): array
    {
        return [
            'created' => ['created'],
            'authorized' => ['authorized'],
        ];
    }

    /**
     * Scenario: a Mollie order that is already paid is left alone when the order is cancelled
     *   Given an order created through the Orders API whose Mollie order is paid
     *   When the merchant cancels the order in WooCommerce
     *   Then the Mollie order keeps its status, and a note says why
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\PaymentModule::cancelOrderAtMollie
     */
    public function it_leaves_a_paid_mollie_order_alone_when_the_order_is_cancelled(): void
    {
        [$order, $mollieOrderId] = $this->orderWithMollieOrder('paid');

        $order->update_status('cancelled');

        $this->assertSame('paid', $this->fakeMollie()->orders()[$mollieOrderId]['status']);
        $this->assertSame([], $this->fakeMollie()->requests('DELETE', 'orders/' . $mollieOrderId));
        $this->assertCount(1, $this->notesContaining($this->fresh($order), 'Order could not be canceled at Mollie, because order status is paid.'));
    }

    /**
     * Scenario: with the option to leave Mollie alone, cancelling asks nothing of Mollie
     *   Given cancelling at Mollie is switched off
     *   When the merchant cancels an order whose Mollie order could be cancelled
     *   Then Mollie is not asked anything
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\PaymentModule::cancelOrderAtMollie
     */
    public function it_asks_nothing_of_mollie_on_cancel_when_that_is_switched_off(): void
    {
        $this->setOptionForTest(self::PLUGIN_ID . '_disableCancelOrderAtMollie', '1');
        [$order, $mollieOrderId] = $this->orderWithMollieOrder('authorized');
        $requestsBefore = count($this->fakeMollie()->requests());

        $order->update_status('cancelled');

        $this->assertSame('authorized', $this->fakeMollie()->orders()[$mollieOrderId]['status']);
        $this->assertCount($requestsBefore, $this->fakeMollie()->requests());
    }

    /**
     * Scenario: completing an order ships its Mollie order, which starts the capture
     *   Given an order created through the Orders API whose Mollie order is authorised
     *   When the merchant completes the order in WooCommerce
     *   Then the Mollie order is shipped in full, and a note says so
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\PaymentModule::shipAndCaptureOrderAtMollie
     */
    public function it_ships_the_mollie_order_when_the_order_is_completed(): void
    {
        [$order, $mollieOrderId] = $this->orderWithMollieOrder('authorized');

        $order->update_status('completed');

        $shipments = array_values($this->fakeMollie()->shipments());
        $this->assertCount(1, $shipments);
        $this->assertSame($mollieOrderId, $shipments[0]['orderId']);
        $this->assertCount(1, $this->notesContaining($this->fresh($order), 'Order successfully updated to shipped at Mollie, capture of funds underway.'));
    }

    /**
     * Scenario: completing an order captures an authorised payment
     *   Given an order paid through the Payments API with a pay-later method, authorised
     *   When the merchant completes the order in WooCommerce
     *   Then Mollie is asked once to capture the order total on that payment
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\PaymentModule::shipAndCaptureOrderAtMollie
     */
    public function it_captures_an_authorised_payment_when_the_order_is_completed(): void
    {
        [$order, $paymentId] = $this->orderWithPaymentIn('klarna', 'authorized');

        $order->update_status('completed');

        $captures = array_values($this->fakeMollie()->captures());
        $this->assertCount(1, $captures);
        $this->assertSame($paymentId, $captures[0]['paymentId']);
        $this->assertSame(['currency' => 'EUR', 'value' => $this->formattedTotal($order)], $captures[0]['amount']);
    }

    /**
     * Scenario: completing an order whose payment is already paid captures nothing
     *   Given an order paid through the Payments API, paid
     *   When the merchant completes the order in WooCommerce
     *   Then nothing is captured or shipped, and a note says the payment is already paid
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\PaymentModule::shipAndCaptureOrderAtMollie
     */
    public function it_captures_nothing_for_a_payment_that_is_already_paid(): void
    {
        [$order] = $this->orderWithPaymentIn('ideal', 'paid');

        $order->update_status('completed');

        $this->assertSame([], $this->fakeMollie()->captures());
        $this->assertSame([], $this->fakeMollie()->shipments());
        $this->assertCount(1, $this->notesContaining($this->fresh($order), 'Payment status is already paid at Mollie, can not be captured.'));
    }

    /**
     * Scenario: with the option to leave Mollie alone, completing asks nothing of Mollie
     *   Given shipping at Mollie is switched off
     *   When the merchant completes an order whose Mollie order is authorised
     *   Then nothing is shipped
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\PaymentModule::shipAndCaptureOrderAtMollie
     */
    public function it_ships_nothing_on_complete_when_that_is_switched_off(): void
    {
        $this->setOptionForTest(self::PLUGIN_ID . '_disableShipOrderAtMollie', '1');
        [$order, $mollieOrderId] = $this->orderWithMollieOrder('authorized');

        $order->update_status('completed');

        $this->assertSame([], $this->fakeMollie()->shipments());
        $this->assertSame('authorized', $this->fakeMollie()->orders()[$mollieOrderId]['status']);
    }

    /**
     * An order the webhook already processed, linked to a Mollie order in the given status.
     *
     * @return array{0: WC_Order, 1: string}
     */
    private function orderWithMollieOrder(string $mollieOrderStatus): array
    {
        $this->newRequest();
        $order = $this->pendingOrder('mollie_wc_gateway_klarna');
        $mollieOrder = $this->fakeMollie()->seedOrder([
            'amount' => ['currency' => 'EUR', 'value' => $this->formattedTotal($order)],
            'method' => 'klarna',
            'status' => $mollieOrderStatus,
            'paymentStatus' => $mollieOrderStatus === 'created' ? 'open' : $mollieOrderStatus,
            'metadata' => ['order_id' => $order->get_id()],
        ]);
        $order->set_transaction_id($mollieOrder['id']);
        $order->update_meta_data('_mollie_order_id', $mollieOrder['id']);
        $order->update_meta_data('_mollie_payment_id', $mollieOrder['paymentId']);
        $order->save();
        $this->assertSame(200, $this->deliverWebhook($mollieOrder['id']));
        $this->newRequest();

        return [$this->fresh($order), $mollieOrder['id']];
    }

    /**
     * An order the webhook already processed, linked to a payment in the given status.
     *
     * @return array{0: WC_Order, 1: string}
     */
    private function orderWithPaymentIn(string $method, string $paymentStatus): array
    {
        $this->newRequest();
        $order = $this->pendingOrder('mollie_wc_gateway_' . $method);
        $payment = $this->fakeMollie()->seedPayment([
            'amount' => ['currency' => 'EUR', 'value' => $this->formattedTotal($order)],
            'method' => $method,
            'status' => $paymentStatus,
            'metadata' => ['order_id' => $order->get_id()],
        ]);
        $order->update_meta_data('_mollie_payment_id', $payment['id']);
        $order->set_transaction_id($payment['id']);
        $order->save();
        $this->assertSame(200, $this->deliverWebhook($payment['id']));
        $this->newRequest();

        return [$this->fresh($order), $payment['id']];
    }

    /**
     * The merchant acts in a request of their own, after the webhook's: one boot, heard alone.
     */
    private function newRequest(): void
    {
        $this->removePluginListenersOfEarlierBoots();
        $this->container = $this->bootExpressOwning(['init']);
        do_action('init');
    }
}

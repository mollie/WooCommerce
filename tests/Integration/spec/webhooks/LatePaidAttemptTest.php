<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\webhooks;

use Mockery;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;
use WC_Order;

/**
 * An earlier payment attempt of an order that another payment already paid.
 *
 * @group integration
 * @group Webhooks
 */
class LatePaidAttemptTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->recordWebhookHooks();
    }

    public function tearDown(): void
    {
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Scenario: an earlier attempt is paid after another payment paid the order
     *   Given an order paid by a second payment
     *   When the first attempt is paid at Mollie and its webhook arrives
     *   Then the order stays processing, keeps the second payment as its transaction
     *   And one note tells the merchant to refund the first attempt, also after a redelivery
     *
     * @test
     */
    public function it_tells_the_merchant_once_when_an_earlier_attempt_is_paid_after_the_order_was_paid(): void
    {
        [$order, $first, $second] = $this->orderPaidBySecondAttempt();

        $this->payLate($order, $first);
        $this->assertSame(200, $this->deliverWebhook($first['id']));
        $this->assertSame(200, $this->deliverWebhook($first['id']));

        $fresh = $this->fresh($order);
        $this->assertSame('processing', $fresh->get_status());
        $this->assertSame($second['id'], $fresh->get_transaction_id());
        $this->assertCount(1, $this->notesContaining($fresh, $first['id']), 'One note names the late payment.');
    }

    /**
     * Scenario: the merchant refunds the late attempt in the Mollie dashboard
     *   Given an order paid by a second payment, and a first attempt paid late
     *   When the first attempt is fully refunded and its webhook arrives
     *   Then the order stays processing: the refund is not the order's money
     *
     * @test
     */
    public function it_keeps_the_order_paid_when_the_late_attempt_is_refunded(): void
    {
        [$order, $first] = $this->orderPaidBySecondAttempt();
        $this->payLate($order, $first);
        $this->assertSame(200, $this->deliverWebhook($first['id']));

        $this->fakeMollie()->refundFromDashboard($first['id'], $this->formattedTotal($order));
        $this->assertSame(200, $this->deliverWebhook($first['id']));

        $this->assertSame('processing', $this->fresh($order)->get_status());
    }

    /**
     * Scenario: a later payment of the Mollie order the order is linked to (Orders API)
     *   Given an order paid through the Orders API, linked to its Mollie order and that order's first payment
     *   When a second payment of the same Mollie order is paid and its webhook arrives
     *   Then the order stays processing and gets no note asking for a refund
     *
     * @test
     */
    public function it_leaves_a_paid_payment_of_the_linked_mollie_order_alone(): void
    {
        $this->boot();
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');
        $mollieOrder = $this->fakeMollie()->seedOrder([
            'amount' => ['currency' => 'EUR', 'value' => $this->formattedTotal($order)],
            'method' => 'ideal',
            'status' => 'paid',
            'paymentStatus' => 'failed',
            'orderNumber' => (string) $order->get_id(),
            'metadata' => ['order_id' => $order->get_id()],
        ]);
        $order->set_transaction_id($mollieOrder['id']);
        $order->update_meta_data('_mollie_order_id', $mollieOrder['id']);
        $order->update_meta_data('_mollie_payment_id', $mollieOrder['paymentId']);
        $order->save();
        $this->assertSame(200, $this->deliverWebhook($mollieOrder['id']));
        $this->assertSame('processing', $this->fresh($order)->get_status());
        $second = $this->fakeMollie()->seedPayment([
            'orderId' => $mollieOrder['id'],
            'status' => 'paid',
            'amount' => ['currency' => 'EUR', 'value' => $this->formattedTotal($order)],
            'metadata' => ['order_id' => $order->get_id()],
        ]);
        $this->payLate($this->fresh($order), $second);

        $this->assertSame(200, $this->deliverWebhook($second['id']));

        $fresh = $this->fresh($order);
        $this->assertSame('processing', $fresh->get_status());
        $this->assertSame([], $this->notesContaining($fresh, 'was not applied to this order'));
    }

    /**
     * The webhook finds an attempt the order no longer tracks through its redirect URL, as the plugin builds it.
     *
     * @param array<string, mixed> $attempt
     */
    private function payLate(WC_Order $order, array $attempt): void
    {
        $redirectUrl = add_query_arg(['order_id' => $order->get_id(), 'key' => $order->get_order_key()], home_url('/'));
        $this->fakeMollie()->setPaymentStatus($attempt['id'], 'paid', ['redirectUrl' => $redirectUrl]);
    }

    /**
     * @return array{0: WC_Order, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function orderPaidBySecondAttempt(): array
    {
        [$order, $first] = $this->orderWithPayment('open', 'mollie_wc_gateway_creditcard');
        $second = $this->paymentFor($order, ['status' => 'open', 'method' => 'paypal']);
        $order = $this->fresh($order);
        $order->set_payment_method('mollie_wc_gateway_paypal');
        $order->update_meta_data('_mollie_payment_id', $second['id']);
        $order->set_transaction_id('');
        $order->save();

        $this->fakeMollie()->setPaymentStatus($second['id'], 'paid');
        $this->assertSame(200, $this->deliverWebhook($second['id']));
        $this->assertSame('processing', $this->fresh($order)->get_status());

        return [$this->fresh($order), $first, $second];
    }
}

<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Refund;

use Mockery;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;
use WC_Order;
use WC_Order_Refund;

/**
 * A refund the merchant makes from the WooCommerce order screen, "refund via Mollie", on an order
 * paid at the ordinary checkout.
 *
 * The e2e suite covers it in tests/qa/tests/06-refund ("refundViaWooCommerce");
 * tests/Integration/e2e-equivalence.md has the list. ExpressRefundTest does the same for express orders.
 *
 * @group integration
 * @group Refund
 */
class CheckoutOrderRefundTest extends ExpressFlowTestCase
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
     * Scenario: a refund from WooCommerce reaches Mollie and is recorded on the order
     *   Given an order paid through the Payments API
     *   When the merchant refunds the whole total, or half of it, with "refund via Mollie"
     *   Then Mollie holds one refund on that payment, for that amount and currency
     *   And the order records the refund with a note naming the payment and the refund
     *   And a full refund leaves the order refunded, a partial one being processed
     *
     * @test
     * @dataProvider refunds
     * @covers \Mollie\WooCommerce\Payment\MolliePayment::refund
     */
    public function it_refunds_a_paid_checkout_order_at_mollie(string $method, bool $full, string $expectedStatus): void
    {
        [$order, $paymentId] = $this->paidOrder($method);
        $amount = $full ? $this->formattedTotal($order) : number_format((float) $order->get_total() / 2, 2, '.', '');

        $refund = wc_create_refund([
            'order_id' => $order->get_id(),
            'amount' => $amount,
            'reason' => 'Returned',
            'refund_payment' => true,
        ]);

        $this->assertInstanceOf(WC_Order_Refund::class, $refund, is_wp_error($refund) ? $refund->get_error_message() : '');
        $atMollie = array_values($this->fakeMollie()->refunds());
        $this->assertCount(1, $atMollie);
        $this->assertSame($paymentId, $atMollie[0]['paymentId']);
        $this->assertSame(['currency' => 'EUR', 'value' => $amount], $atMollie[0]['amount']);
        $this->assertSame('Returned', $atMollie[0]['description']);

        $after = $this->fresh($order);
        $this->assertSame($expectedStatus, $after->get_status());
        $this->assertSame((float) $amount, (float) $after->get_total_refunded());
        // The note carries the amount as WooCommerce hands it over, a float: "EUR5.5".
        $this->assertCount(
            1,
            $this->notesContaining($after, 'Refunded EUR' . (float) $amount . " (reason: Returned) - Payment: {$paymentId}, Refund: {$atMollie[0]['id']}")
        );
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: string}>
     */
    public function refunds(): array
    {
        return [
            'C3458 ideal, full' => ['ideal', true, 'refunded'],
            'C3493 ideal, half' => ['ideal', false, 'processing'],
            'C3459 creditcard, full' => ['creditcard', true, 'refunded'],
            'creditcard, half' => ['creditcard', false, 'processing'],
        ];
    }

    /**
     * Scenario: a refund request Mollie does not accept
     *   Given a paid order
     *   And Mollie will not accept a refund for it: the payment is no longer paid there, or the
     *       amount is more than is left
     *   When the merchant asks for a refund with "refund via Mollie"
     *   Then the outcome on both sides is the one pinned here
     *
     * A characterisation: it holds the current outcome still while the code around it moves.
     *
     * @test
     * @dataProvider refundsMollieDoesNotAccept
     * @covers \Mollie\WooCommerce\Gateway\Refund\RefundProcessor::refundOrderPayment
     */
    public function it_keeps_the_outcome_of_a_refund_request_mollie_does_not_accept(bool $notPaidAnyMore): void
    {
        [$order, $paymentId] = $this->paidOrder('ideal');
        if ($notPaidAnyMore) {
            $this->fakeMollie()->setPaymentStatus($paymentId, 'open');
        } else {
            $this->fakeMollie()->failNext('POST', "payments/{$paymentId}/refunds", 422, 'The amount is higher than the remaining amount.', 'amount');
        }

        $refund = wc_create_refund([
            'order_id' => $order->get_id(),
            'amount' => '1.00',
            'refund_payment' => true,
        ]);

        $this->assertSame([], $this->fakeMollie()->refunds());
        $this->assertCount(1, $this->fakeMollie()->requests('POST', "payments/{$paymentId}/refunds"));
        $this->assertInstanceOf(WC_Order_Refund::class, $refund);
        $after = $this->fresh($order);
        $this->assertSame(1.0, (float) $after->get_total_refunded());
        $this->assertSame([], $this->notesContaining($after, '- Payment: ' . $paymentId));
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public function refundsMollieDoesNotAccept(): array
    {
        return [
            'the payment is not paid any more' => [true],
            'the amount is more than is left' => [false],
        ];
    }

    /**
     * Scenario: the webhook that follows a refund from WooCommerce does not refund the order again
     *   Given a paid order the merchant refunded in part from WooCommerce
     *   When Mollie calls the webhook of the payment, as it does after a refund
     *   Then WooCommerce still holds that one refund, for that amount
     *   And the refund is remembered as processed
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::processRefunds
     */
    public function it_does_not_refund_again_when_mollie_calls_the_webhook_after_the_refund(): void
    {
        [$order, $paymentId] = $this->paidOrder('ideal');
        wc_create_refund(['order_id' => $order->get_id(), 'amount' => '1.00', 'refund_payment' => true]);
        $atMollie = array_values($this->fakeMollie()->refunds());

        $this->assertSame(200, $this->deliverWebhook($paymentId));

        $after = $this->fresh($order);
        $this->assertSame('processing', $after->get_status());
        $this->assertSame(1.0, (float) $after->get_total_refunded());
        $this->assertCount(1, $after->get_refunds());
        $this->assertCount(1, $this->fakeMollie()->refunds());
        $this->assertSame([$atMollie[0]['id']], array_values((array) $after->get_meta('_mollie_processed_refund_ids')));
    }

    /**
     * Scenario: an amount refund of an order created through the Orders API goes to its payment
     *   Given a paid order created through the Orders API
     *   When the merchant refunds an amount with "refund via Mollie", naming no order line
     *   Then Mollie holds one refund on the payment of that Mollie order, for that amount
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\MollieOrder::refund_amount
     */
    public function it_refunds_an_amount_of_an_orders_api_order_on_its_payment(): void
    {
        $this->boot();
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');
        $mollieOrder = $this->fakeMollie()->seedOrder([
            'amount' => ['currency' => 'EUR', 'value' => $this->formattedTotal($order)],
            'method' => 'ideal',
            'status' => 'paid',
            'paymentStatus' => 'paid',
            'metadata' => ['order_id' => $order->get_id()],
        ]);
        $order->set_transaction_id($mollieOrder['id']);
        $order->update_meta_data('_mollie_order_id', $mollieOrder['id']);
        $order->update_meta_data('_mollie_payment_id', $mollieOrder['paymentId']);
        $order->save();
        $this->assertSame(200, $this->deliverWebhook($mollieOrder['id']));

        $refund = wc_create_refund([
            'order_id' => $order->get_id(),
            'amount' => '1.00',
            'reason' => 'Returned',
            'refund_payment' => true,
        ]);

        $this->assertInstanceOf(WC_Order_Refund::class, $refund, is_wp_error($refund) ? $refund->get_error_message() : '');
        $atMollie = array_values($this->fakeMollie()->refunds());
        $this->assertCount(1, $atMollie);
        $this->assertSame($mollieOrder['paymentId'], $atMollie[0]['paymentId']);
        $this->assertSame(['currency' => 'EUR', 'value' => '1.00'], $atMollie[0]['amount']);
        $this->assertSame(1.0, (float) $this->fresh($order)->get_total_refunded());
    }

    /**
     * An order the paid webhook already moved to processing.
     *
     * @return array{0: WC_Order, 1: string}
     */
    private function paidOrder(string $method): array
    {
        $this->boot();
        $order = $this->pendingOrder('mollie_wc_gateway_' . $method);
        $payment = $this->fakeMollie()->seedPayment([
            'amount' => ['currency' => 'EUR', 'value' => $this->formattedTotal($order)],
            'method' => $method,
            'status' => 'paid',
            'metadata' => ['order_id' => $order->get_id()],
        ]);
        $order->update_meta_data('_mollie_payment_id', $payment['id']);
        $order->set_transaction_id($payment['id']);
        $order->save();
        $this->assertSame(200, $this->deliverWebhook($payment['id']));
        $paid = $this->fresh($order);
        $this->assertSame('processing', $paid->get_status(), 'The order must be paid before it is refunded.');

        return [$paid, $payment['id']];
    }
}

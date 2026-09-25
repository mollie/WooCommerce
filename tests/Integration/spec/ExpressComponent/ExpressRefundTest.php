<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use WC_Order;

/**
 * Refunding a paid express order from WooCommerce.
 *
 * The existing refund rails read _mollie_payment_id and throw for an order that never got one. The
 * first sight of an express payment writes it, with the wallet's payment method, so a refund needs
 * nothing special: WooCommerce calls the gateway, RefundProcessor finds the payment, and the refund
 * reaches Mollie. Observed at the fake Mollie and on the order; RefundProcessor is not changed.
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressWebhookResolution
 */
class ExpressRefundTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
    }

    public function tearDown(): void
    {
        $this->tearDownExpressCheckout();

        parent::tearDown();
    }

    /**
     * Scenario: a full or partial refund of a paid express order reaches Mollie and is recorded on the order
     *   Given an express order that the webhook of its paid Apple Pay payment resolved and paid
     *   When support refunds the whole total, or part of it, from WooCommerce with "refund via Mollie"
     *   Then the fake Mollie holds one refund on that payment for that amount
     *   And the order records the refund, with a note naming the payment and the refund
     *
     * @test
     * @dataProvider refundAmounts
     */
    public function it_refunds_a_paid_express_order_at_mollie(bool $full): void
    {
        $order = $this->paidExpressOrder();
        $paymentId = (string) $order->get_meta('_mollie_payment_id');
        $this->assertNotSame('', $paymentId, 'The webhook must have recorded the payment on the order.');
        $amount = $full ? $this->formattedTotal($order) : '5.00';

        $refund = wc_create_refund([
            'order_id' => $order->get_id(),
            'amount' => $amount,
            'reason' => 'Returned',
            'refund_payment' => true,
        ]);

        $this->assertInstanceOf(\WC_Order_Refund::class, $refund, is_wp_error($refund) ? $refund->get_error_message() : '');
        $refunds = array_values($this->fakeMollie()->refunds());
        $this->assertCount(1, $refunds);
        $this->assertSame($paymentId, $refunds[0]['paymentId']);
        $this->assertSame($amount, $refunds[0]['amount']['value']);
        $this->assertSame($order->get_currency(), $refunds[0]['amount']['currency']);

        $after = $this->fresh($order);
        $this->assertSame((float) $amount, (float) $after->get_total_refunded());
        $notes = array_map(static function ($note): string {
            return (string) $note->content;
        }, wc_get_order_notes(['order_id' => $order->get_id()]));
        $this->assertNotSame([], array_filter($notes, static function (string $note) use ($paymentId, $refunds): bool {
            return strpos($note, $paymentId) !== false && strpos($note, (string) $refunds[0]['id']) !== false;
        }), 'The order must record the refund made at Mollie.');
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public function refundAmounts(): array
    {
        return [
            'full refund' => [true],
            'partial refund' => [false],
        ];
    }

    /**
     * An express order created at submit and paid by the webhook of its Apple Pay payment.
     */
    private function paidExpressOrder(): WC_Order
    {
        $this->readyGuestCheckout();
        $session = $this->startedSession();
        $this->assertAnsweredOk($this->startOrder());
        $order = $this->onlyOrderFor($session['ref']);
        $payment = $this->fakeMollie()->completeSession($session['id'], ['status' => 'paid', 'method' => 'applepay']);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));
        $paid = $this->fresh($order);
        $this->assertTrue($paid->is_paid(), 'The express order must be paid before it can be refunded.');

        return $paid;
    }

    private function fresh(WC_Order $order): WC_Order
    {
        clean_post_cache($order->get_id());
        wp_cache_delete(WC_Order::generate_meta_cache_key($order->get_id(), 'orders'), 'orders');
        $fresh = wc_get_order($order->get_id());
        $this->assertInstanceOf(WC_Order::class, $fresh);

        return $fresh;
    }
}
